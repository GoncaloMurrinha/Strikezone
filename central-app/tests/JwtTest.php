<?php
declare(strict_types=1);

require_once __DIR__.'/_framework.php';
require dirname(__DIR__).'/src/Jwt.php';

register_test('JWT sign and verify roundtrip', function(){
  $payload = ['uid'=>123,'name'=>'Ana'];
  $tok = Jwt::sign($payload, 'secret', 'issuer', 3600);
  $out = Jwt::verify($tok, 'secret', 'issuer');
  assert_eq(123, $out['uid']);
  assert_eq('Ana', $out['name']);
  assert_eq('issuer', $out['iss']);
});

register_test('JWT verify fails with wrong secret', function(){
  $payload = ['uid'=>1];
  $tok = Jwt::sign($payload, 's1', 'iss', 3600);
  $thrown = false;
  try { Jwt::verify($tok, 's2', 'iss'); } catch (Throwable $e) { $thrown=true; }
  assert_true($thrown, 'Expected verification failure with wrong secret');
});
