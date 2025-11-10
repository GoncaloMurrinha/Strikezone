<?php
declare(strict_types=1);
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/Jwt.php';
require __DIR__ . '/../src/MiniRedis.php';

$config = require __DIR__ . '/../src/config.php';

$hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s+(\S+)/i', $hdr, $m)) { http_response_code(401); exit('unauthorized'); }
try { Jwt::verify($m[1], $config['api']['jwt_secret'], $config['api']['jwt_issuer']); }
catch (\Throwable $e) { http_response_code(401); exit('unauthorized'); }

$teamId = (int)($_GET['team_id'] ?? 0);
if ($teamId<=0){ http_response_code(422); exit('bad team'); }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
ignore_user_abort(false);
// Cap connection lifetime to avoid tying up workers indefinitely
@set_time_limit(120);

$mr = new MiniRedis($config['redis']['host'], (int)$config['redis']['port'], (float)$config['redis']['timeout']);
$chan = ($config['redis']['prefix'] ?? 'airsoft:') . "team:$teamId";
$matchId = intdiv($teamId, 10);
$stopKey = ($config['redis']['prefix'] ?? 'airsoft:') . "match:$matchId:stopped";

// Advise client to wait before reconnecting
echo "retry: 10000\n\n"; @ob_flush(); @flush();
if ($mr->get($stopKey)!==null) {
  echo "event: stopped\ndata: {\"team_id\":$teamId,\"match_id\":$matchId}\n\n"; @ob_flush(); @flush();
  exit;
}
echo "event: hello\ndata: ".json_encode(['ok'=>true,'team_id'=>$teamId])."\n\n"; @ob_flush(); @flush();

$mr->subscribeLoop($chan, function(string $payload){
  echo "event: pos\ndata: $payload\n\n";
  @ob_flush(); @flush();
  if (function_exists('connection_aborted') && connection_aborted()) { exit; }
});
