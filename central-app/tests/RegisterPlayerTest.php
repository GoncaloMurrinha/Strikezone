<?php
declare(strict_types=1);

require_once __DIR__.'/_framework.php';
require_once dirname(__DIR__).'/src/helpers.php';
require_once dirname(__DIR__).'/src/FloorEngine.php';
require_once __DIR__.'/RepositoryStub.php';
require_once dirname(__DIR__).'/src/ApiController.php';
require_once dirname(__DIR__).'/src/Jwt.php';

class RepoRegisterPlayer extends Repository {
  public array $members = [];
  public array $users = [88=>['id'=>88,'display_name'=>'Existing Player']];
  public array $created = [];
  public array $ensureCalls = [];
  public array $matches = [];
  public function __construct() {
    parent::__construct(null);
    $this->matches = [
      12 => [
        'id'=>12,
        'arena_id'=>5,
        'team_a_code'=>'AAAAAA',
        'team_b_code'=>'BBBBBB',
        'team_a_name'=>'Alpha',
        'team_b_name'=>'Bravo',
      ],
      15 => [
        'id'=>15,
        'arena_id'=>5,
        'team_a_code'=>'CCCCCC',
        'team_b_code'=>'DDDDDD',
        'team_a_name'=>'Charlie',
        'team_b_name'=>'Delta',
      ],
    ];
  }
  public function listArenasByOwner(int $ownerId): array { return [['id'=>5]]; }
  public function getMatchById(int $matchId): ?array {
    return $this->matches[$matchId] ?? null;
  }
  public function findUserById(int $userId): ?array { return $this->users[$userId] ?? null; }
  public function createGuestUser(string $name): int {
    $id = 500 + count($this->created);
    $this->users[$id] = ['id'=>$id,'display_name'=>$name];
    $this->created[] = ['id'=>$id,'name'=>$name];
    return $id;
  }
  public function addMemberToMatch(int $matchId, int $userId, string $side): void {
    $this->members[] = ['match_id'=>$matchId,'user_id'=>$userId,'side'=>$side];
  }
  public function ensurePlayer(int $userId, int $teamId): int {
    $this->ensureCalls[] = ['user_id'=>$userId,'team_id'=>$teamId];
    return 1000 + $teamId;
  }
}

function makeApi(RepoRegisterPlayer $repo, ?string $authToken = null): ApiController {
  $cfg = ['api'=>['jwt_secret'=>'secret','jwt_issuer'=>'owner','token_ttl'=>3600]];
  $api = new ApiController($repo, new FloorEngine(), null, $cfg);
  if ($authToken === null) {
    $authToken = Jwt::sign(['uid'=>99,'name'=>'Owner','email'=>'owner@test'], 'secret', 'owner', 3600);
  }
  $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$authToken;
  return $api;
}

function matchTokenForTest(int $matchId, string $side, int $arenaId, string $code): string {
  $payload = implode(':', [$matchId, $side, $arenaId, strtoupper($code)]);
  $raw = hash_hmac('sha256', $payload, 'secret', true);
  return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

register_test('matchRegisterPlayer attaches existing user', function(){
  $repo = new RepoRegisterPlayer();
  $api = makeApi($repo);
  $GLOBALS['__test_json_input'] = ['match_id'=>12,'user_id'=>88,'side'=>'B'];
  ob_start();
  $api->matchRegisterPlayer();
  $raw = ob_get_clean();
  if (!preg_match('/\{.*\}/s', $raw, $m)) { $data = null; } else { $data = json_decode($m[0], true); }
  assert_eq(true, $data['ok'] ?? null);
  assert_eq(88, $data['user_id'] ?? null);
  assert_eq('Existing Player', $data['display_name'] ?? null);
  assert_eq(1000 + (12*10+2), $data['player_id'] ?? null);
  assert_eq([['match_id'=>12,'user_id'=>88,'side'=>'B']], $repo->members);
});

register_test('matchRegisterPlayer creates guest when only name provided', function(){
  $repo = new RepoRegisterPlayer();
  $api = makeApi($repo);
  $GLOBALS['__test_json_input'] = ['match_id'=>15,'side'=>'A','display_name'=>'Carlos'];
  ob_start();
  $api->matchRegisterPlayer();
  $raw = ob_get_clean();
  if (!preg_match('/\{.*\}/s', $raw, $m)) { $data = null; } else { $data = json_decode($m[0], true); }
  assert_eq(true, $data['ok'] ?? null);
  assert_eq('Carlos', $data['display_name'] ?? null);
  assert_eq('A', $data['side'] ?? null);
  assert_true(($data['user_id'] ?? 0) >= 500);
  assert_eq([['match_id'=>15,'user_id'=>$data['user_id'],'side'=>'A']], $repo->members);
});

register_test('matchRegisterPlayer accepts match token for player self-registration', function(){
  $repo = new RepoRegisterPlayer();
  $match = $repo->getMatchById(15);
  $token = matchTokenForTest($match['id'], 'A', $match['arena_id'], $match['team_a_code']);
  $api = makeApi($repo, $token);
  $GLOBALS['__test_json_input'] = ['match_id'=>15,'side'=>'A','display_name'=>'Nuno'];
  ob_start();
  $api->matchRegisterPlayer();
  $raw = ob_get_clean();
  if (!preg_match('/\{.*\}/s', $raw, $m)) { $data = null; } else { $data = json_decode($m[0], true); }
  assert_eq(true, $data['ok'] ?? null);
  assert_eq('A', $data['side'] ?? null);
  assert_eq('Nuno', $data['display_name'] ?? null);
  assert_true(($data['user_id'] ?? 0) >= 500);
  assert_eq([['match_id'=>15,'user_id'=>$data['user_id'],'side'=>'A']], $repo->members);
});
