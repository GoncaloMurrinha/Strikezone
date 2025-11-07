<?php
declare(strict_types=1);

require_once __DIR__.'/_framework.php';
require dirname(__DIR__).'/src/FloorEngine.php';

register_test('FloorEngine picks strongest floor by weighted RSSI', function(){
  $fe = new FloorEngine();
  $reads = [
    ['uuid'=>'u','major'=>1,'minor'=>1,'rssi'=>-80,'floor'=>1],
    ['uuid'=>'u','major'=>1,'minor'=>2,'rssi'=>-60,'floor'=>2],
  ];
  $out = $fe->decide($reads, null);
  assert_eq(2, $out['floor'], 'Should pick floor 2');
  assert_true($out['confidence'] > 0.5);
});

register_test('FloorEngine returns lastFloor when low confidence (hysteresis)', function(){
  $fe = new FloorEngine();
  // craft readings so candidate != last but confidence low
  $reads = [
    ['uuid'=>'a','major'=>1,'minor'=>1,'rssi'=>-70,'floor'=>2],
    ['uuid'=>'b','major'=>1,'minor'=>2,'rssi'=>-72,'floor'=>1],
  ];
  $last = 1;
  $out = $fe->decide($reads, $last);
  // If candidate is 2 but confidence < 0.6, keep last
  assert_eq(1, $out['floor']);
});

register_test('FloorEngine no readings -> returns last or 0', function(){
  $fe = new FloorEngine();
  $out = $fe->decide([], null);
  assert_eq(0, $out['floor']);
  $out2 = $fe->decide([], 3);
  assert_eq(3, $out2['floor']);
});
