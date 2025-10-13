<?php
declare(strict_types=1);

final class DB {
  private PDO $pdo;
  public function __construct(array $cfg) {
    $this->pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], $cfg['opt']);
  }
  public function pdo(): PDO { return $this->pdo; }
}
