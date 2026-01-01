<?php

/**
 * Get content handler for a given type
 * @param string $type
 * @return BaseContentHandler
 */
function _get_content_handler($type) {
  static $handlers = [];
  
  if (isset($handlers[$type])) {
    return $handlers[$type];
  }
  
  $handler_class = ucfirst($type) . 'Handler';
  $handler_file = BASE_PATH . '/handlers/' . $handler_class . '.php';
  
  if (file_exists($handler_file)) {
    require_once $handler_file;
    if (class_exists($handler_class)) {
      $handlers[$type] = new $handler_class();
      return $handlers[$type];
    }
  }
  
  // Return base handler as fallback
  require_once BASE_PATH . '/handlers/BaseContentHandler.php';
  $handlers[$type] = new BaseContentHandler($type);
  return $handlers[$type];
}
