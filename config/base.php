<?php

$config = array();
$config['sitename'] = 'Simple Twig Site';
$config['siteurl'] = 'https://site.local';

$config['meta'] = [
  'author' => 'simple-twig-site',
  'description' => 'This is a simple website built with the Twig templating engine.',
];

$config['error_path'] = 'index';

$config['default_template'] = 'default';
$config['lang'] = include('en.php');

$config['debug'] = TRUE;

// Load secrets if available
$secrets_file = __DIR__ . '/secrets.php';
$secrets = file_exists($secrets_file) ? include $secrets_file : [];

// Merge secrets into config (secrets override base config)
if (!empty($secrets) && is_array($secrets)) {
    $config = array_replace_recursive($config, $secrets);
}

$config['menu'] = include('menu.php');

$config['content_types'] = include('content_types.php');

// Markdown processing configuration
$config['markdown'] = [
  'image_class' => 'img-fluid rounded d-block mx-auto',
  'table_class' => 'table',
  'table_container_class' => 'table-container table-responsive',
  'figure_class' => 'figure d-block',
  'figure_img_class' => 'figure-img img-fluid rounded d-block mx-auto',
  'figure_caption_class' => 'figure-caption text-center',
  'cache_enabled' => true,
];

return $config;
