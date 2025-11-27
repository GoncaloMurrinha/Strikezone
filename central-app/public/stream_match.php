<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/MiniRedis.php';

$config = require __DIR__ . '/../src/config.php';
$matchId = (int)($_GET['match_id'] ?? 0);
if ($matchId<=0){ http_response_code(422); exit('bad match'); }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
ignore_user_abort(true);
// Cap connection lifetime to avoid tying up workers indefinitely
@set_time_limit(30);

$mr = new MiniRedis($config['redis']['host'], (int)$config['redis']['port'], (float)$config['redis']['timeout']);
$chan = ($config['redis']['prefix'] ?? 'airsoft:') . "match:$matchId";
$stopKey = ($config['redis']['prefix'] ?? 'airsoft:') . "match:$matchId:stopped";

register_shutdown_function(static function () use ($mr, $chan) {
  try {
    $mr->publish($chan, json_encode(['ctrl'=>'stop']));
    if (function_exists('fastcgi_finish_request')) {
      fastcgi_finish_request();
    }
  } catch (\Throwable $e) {
  }
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['halt'])) {
  $mr->set($stopKey, '1', 5);
  $mr->publish($chan, json_encode(['ctrl'=>'stop']));
  echo json_encode(['ok'=>true]);
  exit;
}

// Advise client to wait before reconnecting
if ($mr->get($stopKey)!==null) {
  echo "event: stopped\ndata: {\"match_id\":$matchId}\n\n"; @ob_flush(); @flush();
  exit;
}
echo "retry: 10000\n\n"; @ob_flush(); @flush();
echo "event: hello\ndata: ".json_encode(['ok'=>true,'match_id'=>$matchId])."\n\n"; @ob_flush(); @flush();

$start = microtime(true);
$mr->subscribeLoop(
  $chan,
  function(string $payload) use ($matchId, $stopKey, $mr){
  $t = ltrim($payload);
  if ($t !== '' && $t[0] === '{') {
    $j = json_decode($payload, true);
    if (is_array($j) && isset($j['ctrl']) && $j['ctrl']==='stop') {
      echo "event: stopped\ndata: {\"match_id\":$matchId}\n\n";
      @ob_flush(); @flush();
      exit;
    }
  }
  if ($mr->get($stopKey)!==null) {
    echo "event: stopped\ndata: {\"match_id\":$matchId}\n\n";
    @ob_flush(); @flush();
    exit;
  }
  echo "event: pos\ndata: $payload\n\n";
  @ob_flush(); @flush();
  if (function_exists('connection_aborted') && connection_aborted()) { exit; }
  },
  function() use ($start) {
    return (microtime(true) - $start) >= 110;
  }
);
