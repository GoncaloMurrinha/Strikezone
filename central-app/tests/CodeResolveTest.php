<?php
declare(strict_types=1);

require_once __DIR__.'/_framework.php';
require_once dirname(__DIR__).'/src/helpers.php';
require_once dirname(__DIR__).'/src/FloorEngine.php';
require_once dirname(__DIR__).'/src/ApiController.php';

// Use in-memory SQLite with real Repository so we don't touch MySQL
if (!class_exists('Repository')) {
  class Repository {
    public function __construct($pdo = null) {}
    public function resolveMatchByJoinCode(string $code): ?array {
      if ($code==='AAAAAA' || $code==='BBBBBB') {
        return [
          'id'=>42,
          'arena_id'=>7,
          'name'=>'Teste',
          'starts_at'=>'2025-01-01 10:00:00',
          'team_a_code'=>'AAAAAA',
          'team_b_code'=>'BBBBBB',
          'team_a_name'=>'A Team',
          'team_b_name'=>'B Team',
        ];
      }
      return null;
    }
  }
}
function makeRepoWithSample(): Repository { return new Repository(); }

register_test('ApiController::codeResolve returns team=A', function(){
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_GET['code'] = 'AAAAAA';
  $api = new ApiController(makeRepoWithSample(), new FloorEngine(), null, ['api'=>['jwt_secret'=>'x','jwt_issuer'=>'y','token_ttl'=>3600]]);
  ob_start();
  $api->codeResolve();
  $out = ob_get_clean();
  if (!preg_match('/\{.*\}/s', $out, $m)) { $data = null; } else { $data = json_decode($m[0], true); }
  assert_eq(['status'=>'ok','team'=>'A'], $data);
});

register_test('ApiController::codeResolve returns team=B', function(){
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_GET['code'] = 'BBBBBB';
  $api = new ApiController(makeRepoWithSample(), new FloorEngine(), null, ['api'=>['jwt_secret'=>'x','jwt_issuer'=>'y','token_ttl'=>3600]]);
  ob_start();
  $api->codeResolve();
  $out = ob_get_clean();
  if (!preg_match('/\{.*\}/s', $out, $m)) { $data = null; } else { $data = json_decode($m[0], true); }
  assert_eq(['status'=>'ok','team'=>'B'], $data);
});
