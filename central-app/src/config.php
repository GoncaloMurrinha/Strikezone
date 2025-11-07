<?php
declare(strict_types=1);

return [
  'db' => [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=airsoft_central;charset=utf8mb4',
    'user' => 'root',
    'pass' => '',
    'opt'  => [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
      PDO::ATTR_PERSISTENT => true,
    ],
  ],
  'api' => [
    'jwt_secret' => 'change-me-super-secret',
    'jwt_issuer' => 'strikezone',
    'token_ttl'  => 60*60*24*7 // 7d
  ],
  'redis' => [
    'host' => '127.0.0.1',
    'port' => 6379,
    'timeout' => 1.0,
    'prefix' => 'airsoft:'
  ],
  'uploads' => [
    'maps_dir' => __DIR__ . '/../public/uploads/maps',
    'maps_url' => '/uploads/maps',
    'max_mb'   => 15
  ],
];
