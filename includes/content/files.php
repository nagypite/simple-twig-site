<?php

/**
 * Resolve a file path in the content directory
 * @param string $path
 * @return string|false
 */
function content_resolve_file($path) {
  foreach ($GLOBALS['config']['content_types'] as $content_type => $content_type_config) {
    if (isset($content_type_config['serve_files'])) {
      foreach ($content_type_config['serve_files'] as $pattern => $replacement) {
        if (preg_match($pattern, $path, $matches)) {
          $file_path = preg_replace($pattern, $replacement, $path);
          log_debug('content_resolve_file', 'file path', $path, $file_path);
          return $file_path;
        }
      }
    }
  }
  return false;
}
