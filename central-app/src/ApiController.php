<?php
declare(strict_types=1);

final class ApiController {
  public function __construct(
    private Repository $repo,
    private FloorEngine $fe,
    private ?Realtime $rt,
    private array $cfg
  ) {}
  private function ownerOwnsArena(int $ownerId, int $arenaId): bool {
    foreach ($this->repo->listArenasByOwner($ownerId) as $arena) {
      if ((int)$arena['id'] === $arenaId) {
        return true;
      }
    }
    return false;
  }

  // Util
  public static function randomCode(int $len=6): string {
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s=''; for($i=0;$i<$len;$i++) $s .= $alpha[random_int(0, strlen($alpha)-1)];
    return $s;
  }
  private function tokenForUser(array $u): string {
    return Jwt::sign(['uid'=>(int)$u['id'],'name'=>$u['display_name']], $this->cfg['api']['jwt_secret'], $this->cfg['api']['jwt_issuer'], $this->cfg['api']['token_ttl']);
  }
  private function matchToken(array $match, string $side): string {
    $secret = (string)($this->cfg['api']['match_token_secret'] ?? $this->cfg['api']['jwt_secret']);
    $code = strtoupper($side === 'A' ? (string)$match['team_a_code'] : (string)$match['team_b_code']);
    $payload = implode(':', [
      (int)$match['id'],
      $side,
      (int)$match['arena_id'],
      $code
    ]);
    $raw = hash_hmac('sha256', $payload, $secret, true);
    // Base64-url encoding keeps the token compact and URL safe.
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
  }
  private function matchTokenValid(?string $token, array $match, string $side): bool {
    if ($token === null) return false;
    if ($side !== 'A' && $side !== 'B') return false;
    $expected = $this->matchToken($match, $side);
    return hash_equals($expected, $token);
  }
  private function authenticateMatchRequest(array $match, string $side): array {
    $token = require_auth_header();
    if (!$token) { json_out(['error'=>'unauthorized'],401); exit; }
    try {
      $payload = Jwt::verify($token, $this->cfg['api']['jwt_secret'], $this->cfg['api']['jwt_issuer']);
      return ['mode'=>'owner','payload'=>$payload];
    } catch (\Throwable $e) {
      if ($this->matchTokenValid($token, $match, $side)) {
        return ['mode'=>'match','side'=>$side];
      }
    }
    json_out(['error'=>'unauthorized'],401); exit;
  }
  private static function sideFromTeamId(int $matchId, int $teamId): ?string {
    if ($teamId === ($matchId*10 + 1)) return 'A';
    if ($teamId === ($matchId*10 + 2)) return 'B';
    return null;
  }
  private function requireUser(): array {
    $tok = require_auth_header(); if (!$tok) { json_out(['error'=>'unauthorized'],401); exit; }
    try { $pl = Jwt::verify($tok, $this->cfg['api']['jwt_secret'], $this->cfg['api']['jwt_issuer']); }
    catch(\Throwable $e){ json_out(['error'=>'unauthorized'],401); exit; }
    $u = $this->repo->findUserByEmail($pl['email'] ?? ''); // opcional
    return ['id'=>$pl['uid'], 'display_name'=>$pl['name']];
  }

  // ---- AUTH ----
  // POST /api/register {email,password,display_name}
  public function register(): void {
    $in = json_input();
    $email = trim((string)($in['email'] ?? ''));
    $pass  = (string)($in['password'] ?? '');
    $name  = trim((string)($in['display_name'] ?? ''));
    if ($email==='' || $pass==='' || $name==='') { json_out(['error'=>'invalid_input'],422); return; }
    if ($this->repo->findUserByEmail($email)) { json_out(['error'=>'email_in_use'],409); return; }
    $id = $this->repo->createUser($email,$pass,$name);
    $token = Jwt::sign(['uid'=>$id,'name'=>$name,'email'=>$email], $this->cfg['api']['jwt_secret'], $this->cfg['api']['jwt_issuer'], $this->cfg['api']['token_ttl']);
    json_out(['ok'=>true,'token'=>$token]);
  }
  // POST /api/login {email,password}
  public function login(): void {
    $in = json_input();
    $email = trim((string)($in['email'] ?? ''));
    $pass  = (string)($in['password'] ?? '');
    $u = $this->repo->findUserByEmail($email);
    if (!$u || !password_verify($pass,$u['pass_hash'])) { json_out(['error'=>'bad_auth'],401); return; }
    $token = Jwt::sign(['uid'=>$u['id'],'name'=>$u['display_name'],'email'=>$u['email']], $this->cfg['api']['jwt_secret'], $this->cfg['api']['jwt_issuer'], $this->cfg['api']['token_ttl']);
    json_out(['ok'=>true,'token'=>$token]);
  }

  // ---- CODE RESOLVE ----
  // GET /api/code/resolve?code=XXXXXX  or POST {code}
  // Returns which team (A/B) a join code belongs to, with basic match info.
  public function codeResolve(): void {
    $code = '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
      $code = strtoupper(trim((string)($_GET['code'] ?? '')));
    } else {
      $in = json_input();
      $code = strtoupper(trim((string)($in['code'] ?? '')));
    }
    error_log('[codeResolve] method='.($method).' code='.(string)$code);
    if ($code===''){ json_out(['error'=>'invalid_code'],422); return; }
    $m = $this->repo->resolveMatchByJoinCode($code);
    if (!$m){ error_log('[codeResolve] not_found for code='.$code); json_out(['error'=>'not_found'],404); return; }
    // Active match check: started and not ended
    $now = time();
    $starts = strtotime((string)$m['starts_at']);
    $endsRaw = $m['ends_at'] ?? null;
    $ends = $endsRaw ? strtotime((string)$endsRaw) : null;
    $active = ($starts !== false && $starts <= $now) && ($ends === null || $ends >= $now);
    if (!$active) { error_log('[codeResolve] inactive for code='.$code); json_out(['error'=>'inactive'], 409); return; }
    $side = ($code === $m['team_a_code']) ? 'A' : 'B';
    error_log('[codeResolve] resolved side='.$side.' match_id='.(int)$m['id']);
    $teamName = $side==='A' ? (string)$m['team_a_name'] : (string)$m['team_b_name'];
    $token = $this->matchToken($m, $side);
    json_out([
      'status'=>'ok',
      'team'=>$side,
      'token'=>$token,
      'team_name'=>$teamName,
      'match_id'=>(int)$m['id'],
      'arena_id'=>(int)$m['arena_id']
    ]);
  }

  // ---- ARENA ----
  // POST /api/arena/create {name}
  public function arenaCreate(): void {
    $uTok = require_auth_header(); if (!$uTok){ json_out(['error'=>'unauthorized'],401); return; }
    $pl = Jwt::verify($uTok,$this->cfg['api']['jwt_secret'],$this->cfg['api']['jwt_issuer']);
    $ownerId = (int)$pl['uid'];

    $in = json_input(); $name = trim((string)($in['name'] ?? ''));
    if ($name===''){ json_out(['error'=>'invalid_name'],422); return; }
    $id = $this->repo->createArena($ownerId,$name);
    json_out(['ok'=>true,'arena_id'=>$id]);
  }
  // GET /api/arena/list
  public function arenaList(): void {
    $uTok = require_auth_header(); if (!$uTok){ json_out(['error'=>'unauthorized'],401); return; }
    $pl = Jwt::verify($uTok,$this->cfg['api']['jwt_secret'],$this->cfg['api']['jwt_issuer']);
    $rows = $this->repo->listArenasByOwner((int)$pl['uid']);
    json_out(['ok'=>true,'arenas'=>$rows]);
  }

  // ---- MATCH ----
  // POST /api/match/create {arena_id,name,starts_at,team_a_name,team_b_name}
  public function matchCreate(): void {
    $uTok = require_auth_header(); if (!$uTok){ json_out(['error'=>'unauthorized'],401); return; }
    $pl = Jwt::verify($uTok,$this->cfg['api']['jwt_secret'],$this->cfg['api']['jwt_issuer']);
    $ownerId = (int)$pl['uid'];

    $in = json_input();
    $arenaId = (int)($in['arena_id'] ?? 0);
    $name    = trim((string)($in['name'] ?? ''));
    $starts  = trim((string)($in['starts_at'] ?? ''));
    $teamA   = trim((string)($in['team_a_name'] ?? 'Alpha'));
    $teamB   = trim((string)($in['team_b_name'] ?? 'Bravo'));
    if ($arenaId<=0 || $name==='' || $starts===''){ json_out(['error'=>'invalid_input'],422); return; }

    // (opcional) validar dono do arena
    $arenaIds = array_column($this->repo->listArenasByOwner($ownerId),'id');
    if (!in_array($arenaId, array_map('intval',$arenaIds), true)) { json_out(['error'=>'forbidden'],403); return; }

    $codeA = self::randomCode(6);
    $codeB = self::randomCode(6);
    $mid = $this->repo->createMatch($arenaId,$name,$starts,$teamA,$teamB,$codeA,$codeB);
    json_out(['ok'=>true,'match_id'=>$mid,'codes'=>['A'=>$codeA,'B'=>$codeB]]);
  }

  // GET /api/match/list?arena_id=...
  public function matchList(): void {
    $tok = require_auth_header(); if (!$tok){ json_out(['error'=>'unauthorized'],401); return; }
    $pl = Jwt::verify($tok,$this->cfg['api']['jwt_secret'],$this->cfg['api']['jwt_issuer']);
    $arenaId = (int)($_GET['arena_id'] ?? 0);
    if ($arenaId<=0){ json_out(['error'=>'invalid_arena'],422); return; }
    $rows = $this->repo->listMatchesByArena($arenaId);
    json_out(['ok'=>true,'matches'=>$rows]);
  }

  // POST /api/match/join {join_code}
  public function matchJoin(): void {
    $tok = require_auth_header(); if (!$tok){ json_out(['error'=>'unauthorized'],401); return; }
    $pl = Jwt::verify($tok,$this->cfg['api']['jwt_secret'],$this->cfg['api']['jwt_issuer']);
    $uId = (int)$pl['uid'];
    $in = json_input();
    $code = strtoupper(trim((string)($in['join_code'] ?? '')));
    if ($code===''){ json_out(['error'=>'invalid_code'],422); return; }
    $m = $this->repo->resolveMatchByJoinCode($code);
    if (!$m){ json_out(['error'=>'not_found'],404); return; }

    $side = ($code===$m['team_a_code']) ? 'A' : 'B';
    $this->repo->addMemberToMatch((int)$m['id'],$uId,$side);

    // team_id “lógico”: matchId*10 + (A=1 | B=2)
    $teamId = ((int)$m['id'])*10 + ($side==='A'?1:2);
    $playerId = $this->repo->ensurePlayer($uId,$teamId);

    json_out(['ok'=>true,'match_id'=>(int)$m['id'],'side'=>$side,'team_id'=>$teamId,'player_id'=>$playerId]);
  }

  // GET /api/match/roster?match_id=...
  public function matchRoster(): void {
    $tok = require_auth_header(); if (!$tok){ json_out(['error'=>'unauthorized'],401); return; }
    $pl = Jwt::verify($tok,$this->cfg['api']['jwt_secret'],$this->cfg['api']['jwt_issuer']);
    $ownerId = (int)$pl['uid'];

    $matchId = (int)($_GET['match_id'] ?? 0);
    if ($matchId<=0){ json_out(['error'=>'invalid_match'],422); return; }
    $match = $this->repo->getMatchById($matchId);
    if (!$match){ json_out(['error'=>'not_found'],404); return; }

    // valida dono da arena
    if (!$this->ownerOwnsArena($ownerId,(int)$match['arena_id'])) { json_out(['error'=>'forbidden'],403); return; }

    $members = $this->repo->listMembersByMatch($matchId);
    $out = [];
    foreach ($members as $mbr) {
      $side = $mbr['side'];
      $teamId = $matchId*10 + ($side==='A'?1:2);
      $playerId = $this->repo->ensurePlayer((int)$mbr['user_id'],$teamId);
      $st = $this->repo->getPlayerState($playerId);
      $out[] = [
        'user_id'=>(int)$mbr['user_id'],
        'name'=>$mbr['display_name'],
        'side'=>$side,
        'floor'=>$st['last_floor']!==null ? (int)$st['last_floor'] : null,
        'last_change_at'=>$st['last_change_at']
      ];
    }
    json_out(['ok'=>true,'match'=>$match,'members'=>$out]);
  }
  // POST /api/match/register-player {match_id,user_id,side}
  public function matchRegisterPlayer(): void {
    $in = json_input();
    $matchId = (int)($in['match_id'] ?? 0);
    $userId  = (int)($in['user_id'] ?? 0);
    $side    = strtoupper(trim((string)($in['side'] ?? '')));
    $display = trim((string)($in['display_name'] ?? ''));
    if ($matchId<=0 || !in_array($side,['A','B'], true)) {
      json_out(['error'=>'invalid_input'],422); return;
    }
    $match = $this->repo->getMatchById($matchId);
    if (!$match){ json_out(['error'=>'not_found'],404); return; }
    $auth = $this->authenticateMatchRequest($match, $side);
    $ownerId = null;
    if ($auth['mode']==='owner') {
      $ownerPayload = $auth['payload'];
      $ownerId = (int)$ownerPayload['uid'];
      if (!$this->ownerOwnsArena($ownerId,(int)$match['arena_id'])) { json_out(['error'=>'forbidden'],403); return; }
    } else {
      $userId = 0; // players using match tokens can only create themselves
    }
    $playerName = $display;
    if ($userId>0) {
      $user = $this->repo->findUserById($userId);
      if (!$user) { json_out(['error'=>'user_not_found'],404); return; }
      $playerName = (string)$user['display_name'];
    } else {
      if ($playerName===''){ json_out(['error'=>'invalid_input'],422); return; }
      $userId = $this->repo->createGuestUser($playerName);
    }

    $this->repo->addMemberToMatch($matchId,$userId,$side);
    $teamId = $matchId*10 + ($side==='A'?1:2);
    $playerId = $this->repo->ensurePlayer($userId,$teamId);

    json_out([
      'ok'=>true,
      'match_id'=>$matchId,
      'team_id'=>$teamId,
      'player_id'=>$playerId,
      'user_id'=>$userId,
      'side'=>$side,
      'display_name'=>$playerName
    ]);
  }

  // ---- SCAN ----
  // POST /api/scan { match_id, team_id, player_id, arena_id, readings:[{uuid,major,minor,rssi}], last_floor? }
  public function submitScan(): void {
    $in = json_input();
    $matchId = (int)($in['match_id'] ?? 0);
    $teamId  = (int)($in['team_id']  ?? 0);
    $playerId= (int)($in['player_id']?? 0);
    $arenaId = (int)($in['arena_id'] ?? 0);
    $reads   = (array)($in['readings'] ?? []);
    $lastFloor = isset($in['last_floor']) ? (int)$in['last_floor'] : null;

    if ($matchId<=0 || $teamId<=0 || $playerId<=0 || $arenaId<=0 || !$reads) {
      json_out(['error'=>'invalid_input'],422); return;
    }
    $match = $this->repo->getMatchById($matchId);
    if (!$match){ json_out(['error'=>'not_found'],404); return; }
    $side = self::sideFromTeamId($matchId, $teamId);
    if ($side === null) { json_out(['error'=>'invalid_team'],422); return; }
    $auth = $this->authenticateMatchRequest($match, $side);
    $userMeta = ['id'=>0,'name'=>''];
    if ($auth['mode']==='owner') {
      $payload = $auth['payload'];
      $userMeta['id'] = (int)$payload['uid'];
      $userMeta['name'] = (string)($payload['name'] ?? '');
    } else {
      $userMeta['id'] = $playerId;
      $userMeta['name'] = 'player';
    }
    // Lookup de floors por beacon (1 query por arena em vez de N por reading)
    $floorsMap = $this->repo->getBeaconFloorsMap($arenaId);
    $mapped = [];
    $rssiSum=0; $rssiCnt=0;
    foreach ($reads as $r) {
      $uuid = strtolower((string)$r['uuid']);
      $major=(int)$r['major'];
      $minor=(int)$r['minor'];
      $rssi=(int)$r['rssi'];
      $key = $uuid.':'.$major.':'.$minor;
      if (!array_key_exists($key, $floorsMap)) continue;
      $floor = (int)$floorsMap[$key];
      $mapped[] = ['uuid'=>$uuid,'major'=>$major,'minor'=>$minor,'rssi'=>$rssi,'floor'=>$floor];
      $rssiSum += $rssi; $rssiCnt++;
    }
    if (!$mapped) { json_out(['error'=>'no_known_beacons'],422); return; }

    $decision = $this->fe->decide($mapped, $lastFloor);
    $floor = (int)$decision['floor'];
    $avgRssi = $rssiCnt ? ($rssiSum/$rssiCnt) : -80;

    // Persistência e estado
    $this->repo->setPlayerState($playerId, $floor, $avgRssi);
    $this->repo->insertScan($matchId,$teamId,$playerId,$floor,$mapped);

    // Broadcasting (team + match)
    $msg = [
      'ts'=>time(),
      'match_id'=>$matchId,
      'team_id'=>$teamId,
      'user'=>['id'=>$userMeta['id'], 'name'=>$userMeta['name']],
      'pos'=>['floor'=>$floor, 'conf'=>$decision['confidence']]
    ];
    $this->rt->publishTeamPosition($teamId, $msg);
    $this->rt->publishMatchPosition($matchId, $msg);

    json_out(['ok'=>true,'floor'=>$floor,'confidence'=>$decision['confidence']]);
  }
}
