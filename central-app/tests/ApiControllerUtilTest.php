<?php
declare(strict_types=1);

require_once __DIR__.'/_framework.php';
require dirname(__DIR__).'/src/ApiController.php';

register_test('ApiController::randomCode length and charset', function(){
  $code = ApiController::randomCode(8);
  assert_eq(8, strlen($code));
  assert_true((bool)preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]+$/', $code), 'charset mismatch');
});
