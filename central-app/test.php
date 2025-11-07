<?php
declare(strict_types=1);

// Simple test runner: php central-app/test.php
error_reporting(E_ALL);

// Load all *Test.php files
$dir = __DIR__.'/tests';
require_once $dir.'/_framework.php';

foreach (glob($dir.'/*Test.php') as $f) {
  require $f;
}

exit(run_all_tests());
