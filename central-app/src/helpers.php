<?php
declare(strict_types=1);

function json_input(): array {
  if (isset($GLOBALS['__test_json_input'])) {
    $fake = $GLOBALS['__test_json_input'];
    unset($GLOBALS['__test_json_input']);
    return is_array($fake) ? $fake : [];
  }
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function json_out($data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
function require_auth_header(): ?string {
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (preg_match('/Bearer\s+(\S+)/i', $hdr, $m)) return $m[1];
  return null;
}

// Base URL helpers so assets work under subfolders (e.g., /strikezone/public)
if (!function_exists('base_path')) {
  function base_path(): string {
    $sn = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(str_replace('\\', '/', dirname($sn)), '/');
    if ($dir === '/' || $dir === '.') return '';
    return $dir;
  }
}
if (!function_exists('asset_url')) {
  function asset_url(string $rel): string {
    return base_path().'/'.ltrim($rel, '/');
  }
}
