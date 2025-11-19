<?php
declare(strict_types=1);

if (!class_exists('Repository')) {
  class Repository {
    protected array $matchSamples;
    public function __construct($pdo = null) {
      $this->matchSamples = [
        [
          'id'=>42,
          'arena_id'=>7,
          'name'=>'Teste',
          'starts_at'=>'2025-01-01 10:00:00',
          'team_a_code'=>'AAAAAA',
          'team_b_code'=>'BBBBBB',
          'team_a_name'=>'A Team',
          'team_b_name'=>'B Team',
        ],
      ];
    }
    public function resolveMatchByJoinCode(string $code): ?array {
      $code = strtoupper($code);
      foreach ($this->matchSamples as $match) {
        if ($match['team_a_code'] === $code || $match['team_b_code'] === $code) {
          return $match;
        }
      }
      return null;
    }
    public function getMatchById(int $matchId): ?array {
      foreach ($this->matchSamples as $match) {
        if ((int)$match['id'] === $matchId) {
          return $match;
        }
      }
      return null;
    }
    public function listArenasByOwner(int $ownerId): array { return []; }
    public function findUserById(int $userId): ?array { return null; }
    public function createGuestUser(string $name): int { return 0; }
    public function addMemberToMatch(int $matchId, int $userId, string $side): void {}
    public function ensurePlayer(int $userId, int $teamId): int { return 0; }
    public function getBeaconFloorsMap(int $arenaId): array { return []; }
    public function setPlayerState(int $playerId, int $floor, float $avgRssi): void {}
    public function insertScan(int $matchId,int $teamId,int $playerId,int $floor,array $payload): void {}
  }
}
