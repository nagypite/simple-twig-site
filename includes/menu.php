<?php

function build_menu($path = NULL) {
  return array_map(function($item) use ($path) {
    $item['classes'] = isset($item['classes']) ? (array)$item['classes'] : [];

    $item['active'] = $item['path'] === $path || (isset($item['path_aliases']) && in_array($path, $item['path_aliases']));
    if ($item['active']) $item['classes'][] = 'active';

    $item['url'] = preg_match('/^(https?:\/)?\//', $item['path']) ? $item['path'] : '/'.$item['path'];

    return $item;
  }, array_filter($GLOBALS['config']['menu'], function($item) use ($path) {
    return empty($item['hide_on_paths']) || !in_array($path, $item['hide_on_paths']);
  }));
}
