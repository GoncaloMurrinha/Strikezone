<?php
declare(strict_types=1);

require_once __DIR__.'/_framework.php';
require_once dirname(__DIR__).'/src/helpers.php';
require_once dirname(__DIR__).'/src/FloorEngine.php';
require_once __DIR__.'/RepositoryStub.php';
require_once dirname(__DIR__).'/src/ApiController.php';
function makeRepoWithSample(): Repository { return new Repository(); }
function expectedMatchToken(string $secret, int $matchId, string $side, int $arenaId, string $code): string {
  $payload = implode(':', [$matchId, $side, $arenaId, strtoupper($code)]);
  $raw = hash_hmac('sha256', $payload, $secret, true);
  return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

register_test('ApiController::codeResolve returns team=A', function(){
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_GET['code'] = 'AAAAAA';
  $secret = 'x';
  $api = new ApiController(makeRepoWithSample(), new FloorEngine(), null, null, ['api'=>['jwt_secret'=>$secret,'jwt_issuer'=>'y','token_ttl'=>3600]]);
  ob_start();
  $api->codeResolve();
  $out = ob_get_clean();
  if (!preg_match('/\{.*\}/s', $out, $m)) { $data = null; } else { $data = json_decode($m[0], true); }
  assert_eq([
    'status'=>'ok',
    'team'=>'A',
    'token'=>expectedMatchToken($secret, 42, 'A', 7, 'AAAAAA'),
    'team_name'=>'A Team',
    'match_id'=>42,
    'arena_id'=>7
  ], $data);
});

register_test('ApiController::codeResolve returns team=B', function(){
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_GET['code'] = 'BBBBBB';
  $secret = 'x';
  $api = new ApiController(makeRepoWithSample(), new FloorEngine(), null, null, ['api'=>['jwt_secret'=>$secret,'jwt_issuer'=>'y','token_ttl'=>3600]]);
  ob_start();
  $api->codeResolve();
  $out = ob_get_clean();
  if (!preg_match('/\{.*\}/s', $out, $m)) { $data = null; } else { $data = json_decode($m[0], true); }
  assert_eq([
    'status'=>'ok',
    'team'=>'B',
    'token'=>expectedMatchToken($secret, 42, 'B', 7, 'BBBBBB'),
    'team_name'=>'B Team',
    'match_id'=>42,
    'arena_id'=>7
  ], $data);
});
