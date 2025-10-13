<?php
declare(strict_types=1);
require __DIR__ . '/../src/MiniRedis.php';

$config = require __DIR__ . '/../src/config.php';
$matchId = (int)($_GET['match_id'] ?? 0);
if ($matchId<=0){ http_response_code(422); exit('bad match'); }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$mr = new MiniRedis($config['redis']['host'], (int)$config['redis']['port'], (float)$config['redis']['timeout']);
$chan = ($config['redis']['prefix'] ?? 'airsoft:') . "match:$matchId";

echo "event: hello\ndata: ".json_encode(['ok'=>true,'match_id'=>$matchId])."\n\n"; @ob_flush(); @flush();

$mr->subscribeLoop($chan, function(string $payload){
  echo "event: pos\ndata: $payload\n\n";
  @ob_flush(); @flush();
});
