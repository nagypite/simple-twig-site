<?php

function serve($path, $variables = [], $status = 200) {
  $path_files = resolve_path($path);

  $variables = array_merge([
    'path' => $path,
    'sitename' => $GLOBALS['config']['sitename'],
    'meta' => $GLOBALS['config']['meta'],
    'menu' => build_menu($path),
  ], $variables);

  if (empty($path_files)) {
    if ($path == $GLOBALS['config']['error_path']) {
      die('Cannot serve error_path.');
    }

    $variables['message'] = $GLOBALS['config']['lang']['page_not_found'];
    return serve($GLOBALS['config']['error_path'], $variables, 404);
  }

  if ($status !== 200) {
    http_response_code($status);
  }

  if (isset($path_files['content'])) {
    echo $GLOBALS['twig']->render($path_files['content'], $variables);
    return true;
  }

  die('Nothing to serve.');
}

function check_path($path) {
  return resolve_path($path) ? true : false;
}

function resolve_path($path) {
  $path_dir = PAGES_PATH.'/'.$path;
  if (is_dir($path_dir)) {
    $path_files = [];
    if (file_exists($path_dir.'/content.html')) {
      $path_files['content'] = $path.'/content.html';
    }

    return empty($path_files) ? false : $path_files;
  }

  return false;
}

