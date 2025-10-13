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
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$mr = new MiniRedis($config['redis']['host'], (int)$config['redis']['port'], (float)$config['redis']['timeout']);
$chan = ($config['redis']['prefix'] ?? 'airsoft:') . "team:$teamId";

echo "event: hello\ndata: ".json_encode(['ok'=>true,'team_id'=>$teamId])."\n\n"; @ob_flush(); @flush();

$mr->subscribeLoop($chan, function(string $payload){
  echo "event: pos\ndata: $payload\n\n";
  @ob_flush(); @flush();
});
