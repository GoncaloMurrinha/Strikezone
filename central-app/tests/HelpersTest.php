<?php
declare(strict_types=1);

require_once __DIR__.'/_framework.php';
require dirname(__DIR__).'/src/helpers.php';

register_test('require_auth_header extracts Bearer token', function(){
  $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ABC.DEF.GHI';
  $tok = require_auth_header();
  assert_eq('ABC.DEF.GHI', $tok);
});
