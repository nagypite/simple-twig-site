<?php

// Ensure required modules are loaded
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/processor.php';
require_once __DIR__ . '/filter.php';
require_once __DIR__ . '/handlers.php';
require_once __DIR__ . '/save.php';

/**
 * Preprocess variables for content page
 */
function content_serve_preprocess(&$path_data, &$variables) {
  if (empty($path_data['menu_item']['content_type'])) {
    log_debug('content_preprocess', 'content type not found', $path_data['path']);
    $path_data['error'] = 'content_not_found';
    $variables['message'] = $GLOBALS['config']['lang']['content_not_found'];
    return false;
  }

  // Check if this is an edit, add, or delete action
  if (!empty($path_data['menu_item']['action']) && in_array($path_data['menu_item']['action'], ['edit', 'add'])) {
    return content_serve_preprocess_edit($path_data, $variables);
  }
  
  if (!empty($path_data['menu_item']['action']) && $path_data['menu_item']['action'] === 'delete') {
    return content_serve_preprocess_delete($path_data, $variables);
  }

  $content_type = $path_data['menu_item']['content_type'];
  $stub = empty($path_data['menu_item']['stub']) ? NULL : $path_data['menu_item']['stub'];

  $variables['content_type'] = $content_type;

  // try to serve single content by stub
  if ($stub) {
    $content = get_content($content_type, $stub, true);
    if (!empty($content)) {
      log_debug('content_preprocess', 'content found', $content_type, $stub, $content['path'], $content['title']);
      _content_process_siblings($content);

      $variables['content'] = $content;
      return true;
    }
    else {
      log_debug('content_preprocess', 'content not found', $content_type, $stub);
      $variables['message'] = $GLOBALS['config']['lang']['content_not_found'];
    }
  }

  // fallback to list content
  $content_list = list_content($content_type);

  // apply filtering if needed
  if (!empty($variables['request']['filter'])) {
    $content_list['content'] = _content_filter($content_list['content'], $variables['request']['filter']);
  }

  // Try to use handler for type-specific processing
  $handler = _get_content_handler($content_type);
  if ($handler->processList($content_list, $path_data, $variables)) {
    // Handler processed the list, return early
    return true;
  }

  // Standard processing for non-handled content types
  foreach ($content_list['content'] as &$content) {
    _content_process_urls($content, $path_data['menu_item']);
    _content_process_relations($content, 1);
  }

  if (empty($content_list)) {
    log_debug('content_preprocess', 'content list empty', $content_type);
    $variables['message'] = $GLOBALS['config']['lang']['content_list_empty'];
    return false;
  }

  $variables['content_list'] = $content_list;
  return true;
}

/**
 * Preprocess variables for edit/add pages
 */
function content_serve_preprocess_edit(&$path_data, &$variables) {
  $content_type = $path_data['menu_item']['content_type'];
  $action = $path_data['menu_item']['action'] ?? 'edit';
  $variables['content_type'] = $content_type;
  $variables['action'] = $action;
  
  // Pass content type config to template
  $variables['config'] = $GLOBALS['config'];

  // Extract error from query string if present
  if (!empty($variables['request']['error'])) {
    $variables['error'] = $variables['request']['error'];
  }

  if ($action === 'add') {
    // Use handler to get default structure
    $handler = _get_content_handler($content_type);
    $variables['content'] = $handler->getDefaultStructure();
    return true;
  }

  // Edit action: load existing content
  $content_id = $path_data['menu_item']['content_id'] ?? null;
  if (empty($content_id)) {
    log_debug('content_serve_preprocess_edit', 'content id not found', $path_data['path']);
    $path_data['error'] = 'content_not_found';
    $variables['message'] = $GLOBALS['config']['lang']['content_not_found'];
    return false;
  }

  $content = get_content($content_type, $content_id, true);
  if (empty($content)) {
    log_debug('content_serve_preprocess_edit', 'content not found', $content_type, $content_id);
    $path_data['error'] = 'content_not_found';
    $variables['message'] = $GLOBALS['config']['lang']['content_not_found'];
    return false;
  }

  // Let handler process content for edit form
  $handler = _get_content_handler($content_type);
  $handler->processForEdit($content, $content_type);

  $variables['content'] = $content;
  return true;
}

/**
 * Preprocess variables for delete page
 */
function content_serve_preprocess_delete(&$path_data, &$variables) {
  $content_type = $path_data['menu_item']['content_type'];
  $variables['content_type'] = $content_type;
  $variables['action'] = 'delete';
  
  // Extract error from query string if present
  if (!empty($variables['request']['error'])) {
    $variables['error'] = $variables['request']['error'];
  }

  // Load existing content for confirmation
  $content_id = $path_data['menu_item']['content_id'] ?? null;
  if (empty($content_id)) {
    log_debug('content_serve_preprocess_delete', 'content id not found', $path_data['path']);
    $path_data['error'] = 'content_not_found';
    $variables['message'] = $GLOBALS['config']['lang']['content_not_found'];
    return false;
  }

  $content = get_content($content_type, $content_id, true);
  if (empty($content)) {
    log_debug('content_serve_preprocess_delete', 'content not found', $content_type, $content_id);
    $path_data['error'] = 'content_not_found';
    $variables['message'] = $GLOBALS['config']['lang']['content_not_found'];
    return false;
  }

  $variables['content'] = $content;
  return true;
}
