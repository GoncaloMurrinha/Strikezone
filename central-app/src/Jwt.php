<?php
declare(strict_types=1);

final class Jwt {
  public static function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
  public static function base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
  }
  public static function sign(array $payload, string $secret, string $iss, int $ttl): string {
    $header = ['typ'=>'JWT','alg'=>'HS256'];
    $now = time();
    $payload['iss'] = $iss;
    $payload['iat'] = $now;
    $payload['exp'] = $now + $ttl;
    $h = self::base64UrlEncode(json_encode($header));
    $p = self::base64UrlEncode(json_encode($payload));
    $sig = hash_hmac('sha256', "$h.$p", $secret, true);
    $s = self::base64UrlEncode($sig);
    return "$h.$p.$s";
  }
  public static function verify(string $token, string $secret, string $iss): array {
    $parts = explode('.', $token);
    if (count($parts)!==3) throw new RuntimeException('bad token');
    [$h,$p,$s] = $parts;
    $calc = self::base64UrlEncode(hash_hmac('sha256', "$h.$p", $secret, true));
    if (!hash_equals($calc, $s)) throw new RuntimeException('bad sig');
    $payload = json_decode(self::base64UrlDecode($p), true);
    if (!is_array($payload)) throw new RuntimeException('bad payload');
    if (($payload['iss'] ?? '') !== $iss) throw new RuntimeException('bad iss');
    if (($payload['exp'] ?? 0) < time()) throw new RuntimeException('expired');
    return $payload;
  }
}
