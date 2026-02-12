<?php
declare(strict_types=1);

final class Repository {
  private bool $locationsReady = false;
  private bool $playerStateReady = false;
  public function __construct(private PDO $pdo) {}

  // ----- USERS -----
  public function findUserByEmail(string $email): ?array {
    $st = $this->pdo->prepare('SELECT * FROM users WHERE email=?');
    $st->execute([strtolower($email)]);
    $u = $st->fetch(); return $u ?: null;
  }
  public function findUserById(int $id): ?array {
    $st = $this->pdo->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([$id]);
    $u = $st->fetch(); return $u ?: null;
  }
  public function createUser(string $email, string $pass, string $name): int {
    $st = $this->pdo->prepare('INSERT INTO users (email, pass_hash, display_name) VALUES (?,?,?)');
    $st->execute([strtolower($email), password_hash($pass, PASSWORD_BCRYPT), $name]);
    return (int)$this->pdo->lastInsertId();
  }
  public function createGuestUser(string $name): int {
    $slug = strtolower(preg_replace('~[^a-z0-9]+~i', '', $name));
    if ($slug === '') $slug = 'player';
    $email = sprintf('%s.%s@guest.local', $slug, bin2hex(random_bytes(4)));
    $pass  = bin2hex(random_bytes(8));
    return $this->createUser($email, $pass, $name);
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
  public function upsertMap(int $arenaId, int $floor, string $name, string $url, ?float $width, ?float $height): void {
    $st = $this->pdo->prepare('
      INSERT INTO maps (arena_id,floor,name,map_url,width,height) VALUES (?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE name=VALUES(name), map_url=VALUES(map_url), width=VALUES(width), height=VALUES(height)
    ');
    $st->execute([$arenaId,$floor,$name,$url,$width,$height]);
  }
  public function updateMapDimensions(int $arenaId, int $floor, ?float $width, ?float $height): void {
    $st = $this->pdo->prepare('UPDATE maps SET width=?, height=? WHERE arena_id=? AND floor=?');
    $st->execute([$width,$height,$arenaId,$floor]);
  }
  public function listMapsByArena(int $arenaId): array {
    $st = $this->pdo->prepare('SELECT * FROM maps WHERE arena_id=? ORDER BY floor ASC');
    $st->execute([$arenaId]); return $st->fetchAll();
  }

  // ----- BEACONS -----
  public function upsertBeacon(
    int $arenaId,
    string $uuid,
    int $major,
    int $minor,
    int $floor,
    int $txPower,
    ?string $label,
    ?float $x,
    ?float $y
  ): void {
    $st = $this->pdo->prepare('
      INSERT INTO beacons (arena_id,uuid,major,minor,floor,tx_power,label,x,y) VALUES (?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE floor=VALUES(floor), tx_power=VALUES(tx_power), label=VALUES(label), x=VALUES(x), y=VALUES(y)
    ');
    $st->execute([$arenaId,$uuid,$major,$minor,$floor,$txPower,$label,$x,$y]);
  }
  public function findBeaconsByArena(int $arenaId): array {
    $st = $this->pdo->prepare('SELECT * FROM beacons WHERE arena_id=?');
    $st->execute([$arenaId]); return $st->fetchAll();
  }
  public function deleteBeacon(int $arenaId, string $uuid, int $major, int $minor): void {
    $st = $this->pdo->prepare('DELETE FROM beacons WHERE arena_id=? AND uuid=? AND major=? AND minor=?');
    $st->execute([$arenaId,$uuid,$major,$minor]);
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
    string $teamA, string $teamB, string $codeA, string $codeB,
    string $codeMode = 'text'
  ): int {
    $st = $this->pdo->prepare('
      INSERT INTO matches (arena_id,name,starts_at,team_a_name,team_b_name,team_a_code,team_b_code,code_display_mode)
      VALUES (?,?,?,?,?,?,?,?)
    ');
    $st->execute([$arenaId,$name,$startsAt,$teamA,$teamB,$codeA,$codeB,$codeMode]);
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
    $this->ensurePlayerStateTable();
    $this->ensurePlayerStatePositionColumns();
    $st = $this->pdo->prepare('SELECT * FROM player_state WHERE player_id=?');
    $st->execute([$playerId]);
    $row = $st->fetch();
    return $row ?: [
      'player_id'=>$playerId,
      'last_floor'=>null,
      'last_change_at'=>null,
      'avg_rssi'=>null,
      'x'=>null,
      'y'=>null
    ];
  }
  public function setPlayerState(int $playerId, int $floor, float $avgRssi, ?float $x = null, ?float $y = null): void {
    $this->ensurePlayerStateTable();
    $this->ensurePlayerStatePositionColumns();
    $st = $this->pdo->prepare('
      INSERT INTO player_state (player_id,last_floor,last_change_at,avg_rssi,x,y)
      VALUES (?,?,NOW(),?,?,?)
      ON DUPLICATE KEY UPDATE
        last_floor=VALUES(last_floor),
        last_change_at=VALUES(last_change_at),
        avg_rssi=VALUES(avg_rssi),
        x=VALUES(x),
        y=VALUES(y)
    ');
    $st->execute([$playerId,$floor,$avgRssi,$x,$y]);
  }

  private function ensurePlayerStateTable(): void {
    if ($this->playerStateReady) return;
    $this->pdo->exec('
      CREATE TABLE IF NOT EXISTS player_state (
        player_id INT PRIMARY KEY,
        last_floor INT NULL,
        last_change_at TIMESTAMP NULL,
        avg_rssi FLOAT NULL
      ) ENGINE=InnoDB
    ');
    $this->playerStateReady = true;
  }
  private function ensurePlayerStatePositionColumns(): void {
    $cols = [];
    $st = $this->pdo->query('SHOW COLUMNS FROM player_state');
    if ($st) {
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cols[strtolower((string)($row['Field'] ?? ''))] = true;
      }
    }
    if (!isset($cols['x'])) {
      $this->pdo->exec('ALTER TABLE player_state ADD COLUMN x DOUBLE NULL');
    }
    if (!isset($cols['y'])) {
      $this->pdo->exec('ALTER TABLE player_state ADD COLUMN y DOUBLE NULL');
    }
  }

  // ----- SCANS -----
  public function insertScan(int $matchId,int $teamId,int $playerId,int $floor,array $payload): void {
    $st = $this->pdo->prepare('INSERT INTO scans (match_id,team_id,player_id,floor,payload) VALUES (?,?,?,?,?)');
    $st->execute([$matchId,$teamId,$playerId,$floor,json_encode($payload, JSON_UNESCAPED_UNICODE)]);
  }

  // ----- LOCATIONS -----
  private function ensureLocationsTable(): void {
    if ($this->locationsReady) return;
    $this->pdo->exec('
      CREATE TABLE IF NOT EXISTS locations (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        match_id INT NOT NULL,
        team_id INT NOT NULL,
        player_id INT NOT NULL,
        arena_id INT NOT NULL,
        lat DOUBLE NOT NULL,
        lon DOUBLE NOT NULL,
        accuracy FLOAT NULL,
        speed FLOAT NULL,
        heading FLOAT NULL,
        altitude FLOAT NULL,
        device_ts BIGINT NULL,
        INDEX idx_match (match_id),
        INDEX idx_player (player_id),
        INDEX idx_arena (arena_id),
        FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
      ) ENGINE=InnoDB
    ');
    $this->locationsReady = true;
  }
  public function insertLocation(
    int $matchId,
    int $teamId,
    int $playerId,
    int $arenaId,
    float $lat,
    float $lon,
    ?float $accuracy,
    ?float $speed,
    ?float $heading,
    ?float $altitude,
    ?int $deviceTs
  ): void {
    $this->ensureLocationsTable();
    $st = $this->pdo->prepare('
      INSERT INTO locations
        (match_id,team_id,player_id,arena_id,lat,lon,accuracy,speed,heading,altitude,device_ts)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ');
    $st->execute([
      $matchId,
      $teamId,
      $playerId,
      $arenaId,
      $lat,
      $lon,
      $accuracy,
      $speed,
      $heading,
      $altitude,
      $deviceTs
    ]);
  }
}
