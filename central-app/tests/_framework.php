<?php
declare(strict_types=1);

// Prevent double-loading when both runner and tests include this file
if (defined('SZ_TEST_FRAMEWORK')) { return; }
define('SZ_TEST_FRAMEWORK', 1);

// Minimal test framework for this project (no external deps)

$__tests = [];

if (!function_exists('register_test')) {
  function register_test(string $name, callable $fn): void {
    global $__tests; $__tests[] = [$name, $fn];
  }
}

if (!function_exists('assert_true')) {
  function assert_true(bool $cond, string $msg=''): void {
    if (!$cond) throw new RuntimeException($msg !== '' ? $msg : 'assert_true failed');
  }
}

if (!function_exists('assert_eq')) {
  function assert_eq($expected, $actual, string $msg=''): void {
    if ($expected != $actual) {
      $e = var_export($expected, true); $a = var_export($actual, true);
      throw new RuntimeException(($msg ? $msg.' — ' : '')."expected $e, got $a");
    }
  }
}

if (!function_exists('assert_same')) {
  function assert_same($expected, $actual, string $msg=''): void {
    if ($expected !== $actual) {
      $e = var_export($expected, true); $a = var_export($actual, true);
      throw new RuntimeException(($msg ? $msg.' — ' : '')."expected(same) $e, got $a");
    }
  }
}

if (!function_exists('run_all_tests')) {
  function run_all_tests(): int {
    global $__tests; $ok=0; $fail=0; $i=0;
    $start = microtime(true);
    foreach ($__tests as [$name,$fn]) {
      $i++;
      try { $fn(); $ok++; echo "✔ $name\n"; }
      catch (Throwable $e) { $fail++; echo "✘ $name\n   → ".$e->getMessage()."\n"; }
    }
    $dur = (microtime(true)-$start)*1000;
    echo "\n$ok passed, $fail failed in ".number_format($dur,2)." ms\n";
    return $fail===0 ? 0 : 1;
  }
}
