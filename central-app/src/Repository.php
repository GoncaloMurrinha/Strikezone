<?php
declare(strict_types=1);

final class Repository {
  public function __construct(private PDO $pdo) {}

  // ----- USERS -----
  public function findUserByEmail(string $email): ?array {
    $st = $this->pdo->prepare('SELECT * FROM users WHERE email=?');
    $st->execute([strtolower($email)]);
    $u = $st->fetch(); return $u ?: null;
  }
  public function createUser(string $email, string $pass, string $name): int {
    $st = $this->pdo->prepare('INSERT INTO users (email, pass_hash, display_name) VALUES (?,?,?)');
    $st->execute([strtolower($email), password_hash($pass, PASSWORD_BCRYPT), $name]);
    return (int)$this->pdo->lastInsertId();
  }

  // ----- ARENAS -----
  public function createArena(int $ownerUserId, string $name): int {
    $st = $this->pdo->prepare('INSERT INTO arenas (name, owner_user_id) VALUES (?,?)');
    $st->execute([$name, $ownerUserId]); return (int)$this->pdo->lastInsertId();
  }
  public function listArenasByOwner(int $ownerUserId): array {
    $st = $this->pdo->prepare('SELECT * FROM arenas WHERE owner_user_id=? ORDER BY id DESC');
    $st->execute([$ownerUserId]); return $st->fetchAll();
  }

  // ----- MAPS -----
  public function upsertMap(int $arenaId, int $floor, string $name, string $url): void {
    $st = $this->pdo->prepare('
      INSERT INTO maps (arena_id,floor,name,map_url) VALUES (?,?,?,?)
      ON DUPLICATE KEY UPDATE name=VALUES(name), map_url=VALUES(map_url)
    ');
    $st->execute([$arenaId,$floor,$name,$url]);
  }
  public function listMapsByArena(int $arenaId): array {
    $st = $this->pdo->prepare('SELECT * FROM maps WHERE arena_id=? ORDER BY floor ASC');
    $st->execute([$arenaId]); return $st->fetchAll();
  }

  // ----- BEACONS -----
  public function upsertBeacon(int $arenaId, string $uuid, int $major, int $minor, int $floor, int $txPower, ?string $label): void {
    $st = $this->pdo->prepare('
      INSERT INTO beacons (arena_id,uuid,major,minor,floor,tx_power,label) VALUES (?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE floor=VALUES(floor), tx_power=VALUES(tx_power), label=VALUES(label)
    ');
    $st->execute([$arenaId,$uuid,$major,$minor,$floor,$txPower,$label]);
  }
  public function findBeaconsByArena(int $arenaId): array {
    $st = $this->pdo->prepare('SELECT * FROM beacons WHERE arena_id=?');
    $st->execute([$arenaId]); return $st->fetchAll();
  }
  public function getBeaconFloorsMap(int $arenaId): array {
    $rows = $this->findBeaconsByArena($arenaId);
    $map = [];
    foreach ($rows as $row) {
      $key = strtolower($row['uuid']).':'.(int)$row['major'].':'.(int)$row['minor'];
      $map[$key] = (int)$row['floor'];
    }
    return $map;
  }
  public function beaconFloorLookup(int $arenaId, string $uuid, int $major, int $minor): ?int {
    $st = $this->pdo->prepare('SELECT floor FROM beacons WHERE arena_id=? AND uuid=? AND major=? AND minor=?');
    $st->execute([$arenaId,$uuid,$major,$minor]);
    $row = $st->fetch(); return $row ? (int)$row['floor'] : null;
  }

  // ----- MATCHES -----
  public function createMatch(
    int $arenaId, string $name, string $startsAt,
    string $teamA, string $teamB, string $codeA, string $codeB
  ): int {
    $st = $this->pdo->prepare('
      INSERT INTO matches (arena_id,name,starts_at,team_a_name,team_b_name,team_a_code,team_b_code)
      VALUES (?,?,?,?,?,?,?)
    ');
    $st->execute([$arenaId,$name,$startsAt,$teamA,$teamB,$codeA,$codeB]);
    return (int)$this->pdo->lastInsertId();
  }
  public function listMatchesByArena(int $arenaId): array {
    $st = $this->pdo->prepare('SELECT * FROM matches WHERE arena_id=? ORDER BY id DESC');
    $st->execute([$arenaId]); return $st->fetchAll();
  }
  public function getMatchById(int $matchId): ?array {
    $st = $this->pdo->prepare('SELECT * FROM matches WHERE id=?');
    $st->execute([$matchId]); $m=$st->fetch(); return $m?:null;
  }
  public function resolveMatchByJoinCode(string $code): ?array {
    $st = $this->pdo->prepare('SELECT * FROM matches WHERE team_a_code=? OR team_b_code=?');
    $st->execute([$code,$code]); $m=$st->fetch(); return $m?:null;
  }

  // ----- MEMBERS/PLAYERS -----
  public function addMemberToMatch(int $matchId, int $userId, string $side): void {
    $st = $this->pdo->prepare('INSERT IGNORE INTO match_members (match_id,user_id,side) VALUES (?,?,?)');
    $st->execute([$matchId,$userId,$side]);
  }
  public function listMembersByMatch(int $matchId): array {
    $st = $this->pdo->prepare('
      SELECT mm.*, u.display_name
      FROM match_members mm JOIN users u ON u.id=mm.user_id
      WHERE mm.match_id=?
    ');
    $st->execute([$matchId]); return $st->fetchAll();
  }
  public function ensurePlayer(int $userId, int $teamId): int {
    $st = $this->pdo->prepare('SELECT id FROM players WHERE user_id=? AND team_id=?');
    $st->execute([$userId,$teamId]);
    $row = $st->fetch();
    if ($row) return (int)$row['id'];
    $st = $this->pdo->prepare('INSERT INTO players (user_id, team_id) VALUES (?,?)');
    $st->execute([$userId,$teamId]); return (int)$this->pdo->lastInsertId();
  }
  public function getPlayerState(int $playerId): array {
    $st = $this->pdo->prepare('SELECT * FROM player_state WHERE player_id=?');
    $st->execute([$playerId]);
    $row = $st->fetch();
    return $row ?: ['player_id'=>$playerId,'last_floor'=>null,'last_change_at'=>null,'avg_rssi'=>null];
  }
  public function setPlayerState(int $playerId, int $floor, float $avgRssi): void {
    $st = $this->pdo->prepare('
      INSERT INTO player_state (player_id,last_floor,last_change_at,avg_rssi)
      VALUES (?,?,NOW(),?)
      ON DUPLICATE KEY UPDATE last_floor=VALUES(last_floor), last_change_at=VALUES(last_change_at), avg_rssi=VALUES(avg_rssi)
    ');
    $st->execute([$playerId,$floor,$avgRssi]);
  }

  // ----- SCANS -----
  public function insertScan(int $matchId,int $teamId,int $playerId,int $floor,array $payload): void {
    $st = $this->pdo->prepare('INSERT INTO scans (match_id,team_id,player_id,floor,payload) VALUES (?,?,?,?,?)');
    $st->execute([$matchId,$teamId,$playerId,$floor,json_encode($payload, JSON_UNESCAPED_UNICODE)]);
  }
}
