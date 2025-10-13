<?php
declare(strict_types=1);

require_once __DIR__ . '/MiniRedis.php';

final class Realtime {
  private MiniRedis $r; private string $prefix;
  public function __construct(array $cfg) {
    $this->r = new MiniRedis($cfg['host'] ?? '127.0.0.1', (int)($cfg['port'] ?? 6379), (float)($cfg['timeout'] ?? 1.0));
    $this->prefix = (string)($cfg['prefix'] ?? 'airsoft:');
  }
  public function chanTeam(int $teamId): string { return $this->prefix."team:$teamId"; }
  public function chanMatch(int $matchId): string { return $this->prefix."match:$matchId"; }
  public function publishTeamPosition(int $teamId, array $msg): void {
    $this->r->publish($this->chanTeam($teamId), json_encode($msg, JSON_UNESCAPED_UNICODE));
  }
  public function publishMatchPosition(int $matchId, array $msg): void {
    $this->r->publish($this->chanMatch($matchId), json_encode($msg, JSON_UNESCAPED_UNICODE));
  }
}
