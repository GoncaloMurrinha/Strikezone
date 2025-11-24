<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
$envFile = $baseDir . '/.env';

if (is_readable($envFile)) {
    $vars = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($vars !== false) {
        foreach ($vars as $key => $value) {
            $_ENV[$key] = $value;
            putenv($key . '=' . (string)$value);
        }
    }
}

$env = static function (string $key, $default = null) {
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $value = getenv($key);
    return $value === false ? $default : $value;
};

$redisPrefix = (string)$env('REDIS_PREFIX', 'airsoft');
$redisPrefix = rtrim($redisPrefix, ':') . ':';

$dbSocket = $env('DB_SOCKET');
$hasSocket = is_string($dbSocket) && $dbSocket !== '';

$dsn = $hasSocket
    ? sprintf(
        'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
        $dbSocket,
        $env('DB_DATABASE', 'airsoft_central')
    )
    : sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env('DB_HOST', '127.0.0.1'),
        $env('DB_PORT', '3306'),
        $env('DB_DATABASE', 'airsoft_central')
    );

return [
  'db' => [
    'dsn' => $dsn,
    'user' => (string)$env('DB_USERNAME', 'root'),
    'pass' => (string)$env('DB_PASSWORD', ''),
    'opt'  => [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
      PDO::ATTR_PERSISTENT => true,
    ],
  ],
  'api' => [
    'jwt_secret' => (string)$env('JWT_SECRET', 'change-me-super-secret'),
    'jwt_issuer' => (string)$env('JWT_ISSUER', 'strikezone'),
    'token_ttl'  => (int)$env('TOKEN_TTL', 60*60*24*7),
  ],
  'redis' => [
    'host' => (string)$env('REDIS_HOST', '127.0.0.1'),
    'port' => (int)$env('REDIS_PORT', 6379),
    'timeout' => (float)$env('REDIS_TIMEOUT', 1.0),
    'prefix' => $redisPrefix,
  ],
  'uploads' => [
    'maps_dir' => (string)$env('UPLOADS_MAPS_DIR', __DIR__ . '/../public/uploads/maps'),
    'maps_url' => (string)$env('UPLOADS_MAPS_URL', '/uploads/maps'),
    'max_mb'   => (int)$env('UPLOAD_MAX_MB', 15),
  ],
  'qr' => [
    'dir' => (string)$env('QR_OUTPUT_DIR', __DIR__ . '/../public/uploads/qrcodes'),
    'url' => (string)$env('QR_BASE_URL', '/uploads/qrcodes'),
    'size' => (int)$env('QR_SIZE', 220),
  ],
];
