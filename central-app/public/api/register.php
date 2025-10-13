<?php
declare(strict_types=1);

// Cabeçalhos CORS e tipo de resposta
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Configuração da base de dados
$config = [
  'db' => [
    'dsn'  => 'mysql:host=localhost;dbname=airsoft_central;charset=utf8mb4',
    'user' => 'root',
    'pass' => ''
  ]
];

function bad(int $code, string $msg): void {
  http_response_code($code);
  echo json_encode(['status'=>'error','message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// Lê o JSON recebido
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) bad(400, 'JSON inválido');

$display_name   = trim($data['name'] ?? '');
$email          = trim($data['email'] ?? '');
$password       = (string)($data['password'] ?? '');
$field_name     = trim($data['field_name'] ?? '');
$field_location = trim($data['field_location'] ?? ''); // ainda não usado, mas podes adicionar mais tarde

if ($display_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || $field_name === '') {
  bad(422, 'Campos obrigatórios em falta ou inválidos');
}

try {
  $pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );

  // Verifica se já existe utilizador
  $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $st->execute([$email]);
  if ($st->fetch()) bad(409, 'Email já registado');

  // Cria o utilizador
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $st = $pdo->prepare('INSERT INTO users (email, pass_hash, display_name) VALUES (?, ?, ?)');
  $st->execute([$email, $hash, $display_name]);
  $user_id = (int)$pdo->lastInsertId();

  // Cria a arena associada ao dono
  $st = $pdo->prepare('INSERT INTO arenas (name, owner_user_id) VALUES (?, ?)');
  $st->execute([$field_name, $user_id]);
  $arena_id = (int)$pdo->lastInsertId();

  echo json_encode([
    'status'    => 'success',
    'message'   => 'Conta criada com sucesso',
    'user_id'   => $user_id,
    'arena_id'  => $arena_id
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  bad(500, 'Erro interno: '.$e->getMessage());
}
