<?php

error_reporting(E_ALL);

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);

define('BASE_PATH', realpath(__DIR__.'/..'));
require_once BASE_PATH.'/includes/bootstrap.php';

$path = NULL;

if (isset($_REQUEST['path'])) {
  $path = $_REQUEST['path'];
}
else if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/') {
  $path = substr($_SERVER['REQUEST_URI'], 1);
  // remove query string
  $pos = strpos($path, '?');
  if ($pos !== false) {
    $path = substr($path, 0, $pos);
  }
}

if (empty($path) || in_array($path, ['index.php'])) {
  $path = 'index';
}

serve($path);
