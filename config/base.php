<?php

$config = array();
$config['sitename'] = 'Simple Twig Site';

$config['meta'] = [
  'author' => 'simple-twig-site',
  'description' => 'This is a simple website built with the Twig templating engine.',
];

$config['error_path'] = 'index';

$config['default_template'] = 'default';
$config['lang'] = include('en.php');

$config['debug'] = TRUE;

$config['menu'] = include('menu.php');

return $config;
