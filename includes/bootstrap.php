<?php

define('TEMPLATE_PATH', BASE_PATH.'/templates');
define('PAGES_PATH', BASE_PATH.'/pages');
define('CACHE_PATH', BASE_PATH.'/cache');

include BASE_PATH.'/includes/serve.php';
include BASE_PATH.'/includes/menu.php';
require_once BASE_PATH.'/vendor/autoload.php';

include BASE_PATH.'/config/base.php';

$loader = new \Twig\Loader\FilesystemLoader([TEMPLATE_PATH, PAGES_PATH]);
$twig = new \Twig\Environment($loader, [
  'cache' => CACHE_PATH,
  'debug' => $config['debug'],
  'charset' => 'utf-8',
  'auto_reload' => TRUE,
  'autoescape' => FALSE,
]);
