<?php

// Set upload limits FIRST, before any other processing
// Note: ini_set may not work for upload_max_filesize/post_max_size in all PHP configs
// .htaccess directives are also set as a fallback
@ini_set('upload_max_filesize', '5M');
@ini_set('post_max_size', '5M');

error_reporting(E_ALL ^ E_DEPRECATED);

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
