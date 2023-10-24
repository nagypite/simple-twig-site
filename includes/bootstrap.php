<?php

include BASE_PATH.'/config.php';
require_once BASE_PATH.'/vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader($config['basepath'].'/templates');
$twig = new \Twig\Environment($loader, [
//  'cache' => $config['basepath'].'/cache',
  'debug' => TRUE,
  'charset' => 'utf-8',
  'auto_reload' => TRUE,
  'autoescape' => FALSE,
]);
