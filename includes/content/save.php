<?php

// Ensure required modules are loaded
require_once __DIR__ . '/yaml.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/handlers.php';

/**
 * Prepare metadata from post data
 * @param string $type
 * @param array $post_data
 * @param array $content_type_config
 * @return array
 */
function _prepare_metadata($type, $post_data, $content_type_config) {
  $required_fields = $content_type_config['required_fields'] ?? [];
  $meta = [];
  
  // Start with required fields (excluding 'content' which goes in body)
  foreach ($required_fields as $field) {
    if ($field === 'content') {
      continue;
    }
    $meta[$field] = $post_data[$field];
  }
  
  // Let handler process optional metadata fields
  $handler = _get_content_handler($type);
  $handler->processOptionalMetadata($meta, $post_data);
  
  // Optional fields for all content types
  if (!empty($post_data['image'])) {
    $meta['image'] = $post_data['image'];
  }
  if (!empty($post_data['abstract_cn'])) {
    $meta['abstract_cn'] = $post_data['abstract_cn'];
  }

  // Save abstract if it's set or generate it from content if abstract_length is set
  if (!empty($post_data['abstract'])) {
    $meta['abstract'] = $post_data['abstract'];
  } else if (!empty($content_type_config['abstract_length'])) {
    $meta['abstract'] = _generate_markdown_safe_abstract($post_data['content'] ?? '', $content_type_config['abstract_length']);
  }

  if (isset($post_data['sticky'])) {
    $meta['sticky'] = !empty($post_data['sticky']);
  }
  if (isset($post_data['location'])) {
    $meta['location'] = trim($post_data['location']);
  }
  
  // Let handler preprocess if needed (includes gallery processing)
  $handler = _get_content_handler($type);
  $handler->preprocessSave($meta, $post_data, $content_type_config);
  
  return $meta;
}

/**
 * Generate new content ID
 * @param string $type
 * @return int
 */
function _generate_new_content_id($type) {
  $content_list = list_content($type, false);
  $ids = [];
  foreach ($content_list['content'] as $item) {
    if (isset($item['id'])) {
      $item_id = (int)$item['id'];
      if ($item_id > 0) {
        $ids[] = $item_id;
      }
    }
  }
  return empty($ids) ? 1 : max($ids) + 1;
}

/**
 * Update image URLs in content: replace _new with actual content ID
 * @param string $content_body
 * @param string $type
 * @param int $new_id
 * @return string
 */
function _update_image_urls_in_content($content_body, $type, $new_id) {
  $pattern = '/\/content\/' . preg_quote($type, '/') . '\/images\/_new\//';
  $replacement = '/content/' . $type . '/images/' . $new_id . '/';
  return preg_replace($pattern, $replacement, $content_body);
}

/**
 * Move images from _new directory to actual ID directory
 * @param string $type
 * @param int $new_id
 * @return void
 */
function _move_images_from_new($type, $new_id) {
  $new_images_dir = CONTENT_PATH . '/' . $type . '/images/' . $new_id . '/';
  $temp_images_dir = CONTENT_PATH . '/' . $type . '/images/_new/';
  
  if (!is_dir($temp_images_dir)) {
    return;
  }
  
  // Create target directory if it doesn't exist
  if (!is_dir($new_images_dir)) {
    if (!mkdir($new_images_dir, 0755, true)) {
      log_debug('content_save', 'Failed to create images directory', $new_images_dir);
      return;
    }
  }
  
  // Move all files from _new to actual ID directory
  if (is_dir($new_images_dir) && is_readable($temp_images_dir)) {
    $files = scandir($temp_images_dir);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }
      
      $source_path = $temp_images_dir . $file;
      $target_path = $new_images_dir . $file;
      
      if (is_file($source_path)) {
        if (rename($source_path, $target_path)) {
          log_debug('content_save', 'Moved image from _new', $file, 'to', $new_id);
        } else {
          log_debug('content_save', 'Failed to move image', $file);
        }
      }
    }
    
    // Try to remove _new directory if it's empty
    $remaining_files = array_diff(scandir($temp_images_dir), ['.', '..']);
    if (empty($remaining_files)) {
      rmdir($temp_images_dir);
      log_debug('content_save', 'Removed empty _new directory');
    }
  }
}

/**
 * Invalidate content cache
 * @param string $type
 * @return void
 */
function _invalidate_content_cache($type) {
  $cache_file = CONTENT_CACHE_PATH.'/'.$type.'.php';
  if (file_exists($cache_file)) {
    unlink($cache_file);
  }
}

/**
 * Save content (create or update)
 * @param string $type
 * @param array $post_data
 * @return array|false
 */
function content_save($type, $post_data) {
  // Get required fields from config
  $content_type_config = $GLOBALS['config']['content_types'][$type] ?? [];
  $required_fields = $content_type_config['required_fields'] ?? [];
  
  // Validate required fields
  if (!empty($required_fields)) {
    foreach ($required_fields as $field) {
      if (!isset($post_data[$field]) || $post_data[$field] === '' || $post_data[$field] === null) {
        log_debug('content_save', 'Validation failed - missing required field', $field, 'post_data keys:', array_keys($post_data), 'required_fields:', $required_fields);
        return false;
      }
    }
  }
  
  $is_new = empty($post_data['content_id']);
  log_debug('content_save', 'Save operation', $is_new ? 'create' : 'update', 'content_id', $post_data['content_id'] ?? 'none');
  
  // Ensure CONTENT_PATH directory exists
  if (!is_dir(CONTENT_PATH)) {
    if (!mkdir(CONTENT_PATH, 0755, true)) {
      log_debug('content_save', 'Failed to create CONTENT_PATH directory', CONTENT_PATH);
      return false;
    }
    log_debug('content_save', 'Created CONTENT_PATH directory', CONTENT_PATH);
  }
  
  // Ensure content type directory exists
  $content_dir = CONTENT_PATH.'/'.$type;
  if (!is_dir($content_dir)) {
    if (!mkdir($content_dir, 0755, true)) {
      log_debug('content_save', 'Failed to create content directory', $content_dir);
      return false;
    }
    log_debug('content_save', 'Created content directory', $content_dir);
  }
  
  // Check if directory is writable
  if (!is_writable($content_dir)) {
    log_debug('content_save', 'Directory not writable', $content_dir);
    return false;
  }
  
  // Prepare metadata
  $meta = _prepare_metadata($type, $post_data, $content_type_config);
  
  // Get content body (only for content types that require it)
  $content_body = '';
  if (in_array('content', $required_fields)) {
    $content_body = $post_data['content'] ?? '';
  }
  
  if ($is_new) {
    // Generate new ID
    $new_id = _generate_new_content_id($type);
    $meta['id'] = $new_id;
    
    // Generate filename with ID: {id}-{stub}.md
    $filename = $new_id . '-' . $post_data['stub'] . '.md';
    $file_path = CONTENT_PATH.'/'.$type.'/'.$filename;
    
    // Check if file already exists
    if (file_exists($file_path)) {
      return false; // File conflict
    }
    
    // Update image URLs in content body: replace _new with actual content ID
    if (!empty($content_body)) {
      $content_body = _update_image_urls_in_content($content_body, $type, $new_id);
      log_debug('content_save', 'Updated image URLs in content', 'replaced _new with', $new_id);
    }
    
    // Update image URLs in gallery JSON if it exists (via handler)
    $handler = _get_content_handler($type);
    $handler->updateGalleryImageUrls($meta, $type, $new_id);
  } else {
    // Update existing content
    $content_id = (int)$post_data['content_id'];
    $meta['id'] = $content_id;
    
    // Get existing content to find filename
    $existing_content = get_content($type, $content_id, false);
    log_debug('content_save', 'Existing content lookup', 'content_id', $content_id, 'found', !empty($existing_content), 'path', $existing_content['path'] ?? 'none');
    
    if (empty($existing_content) || empty($existing_content['path'])) {
      log_debug('content_save', 'Content not found or missing path', 'content_id', $content_id);
      return false; // Content not found
    }
    
    $file_path = CONTENT_PATH.'/'.$type.'/'.$existing_content['path'];
    log_debug('content_save', 'File path', $file_path);
    
    // Generate new filename with ID: {id}-{stub}.md
    $new_filename = $content_id . '-' . $post_data['stub'] . '.md';
    
    // If filename changed (stub changed or file doesn't match new format), rename file
    if ($existing_content['path'] !== $new_filename && file_exists($file_path)) {
      $new_file_path = CONTENT_PATH.'/'.$type.'/'.$new_filename;
      if (!file_exists($new_file_path)) {
        rename($file_path, $new_file_path);
        $file_path = $new_file_path;
        $meta['path'] = $new_filename;
        log_debug('content_save', 'File renamed', $file_path);
      } else {
        log_debug('content_save', 'Cannot rename - target file exists', $new_file_path);
        return false;
      }
    } else {
      // Update path in meta if it doesn't match the new format
      if ($existing_content['path'] !== $new_filename) {
        $meta['path'] = $new_filename;
      }
    }
  }
  
  // Ensure CONTENT_PATH directory exists
  if (!is_dir(CONTENT_PATH)) {
    if (!mkdir(CONTENT_PATH, 0755, true)) {
      log_debug('content_save', 'Failed to create CONTENT_PATH directory', CONTENT_PATH);
      return false;
    }
  }
  
  // Ensure content directory exists
  $content_dir = CONTENT_PATH.'/'.$type;
  if (!is_dir($content_dir)) {
    if (!mkdir($content_dir, 0755, true)) {
      log_debug('content_save', 'Failed to create content directory', $content_dir);
      return false;
    }
    log_debug('content_save', 'Created content directory', $content_dir);
  }
  
  // Check if directory is writable
  if (!is_writable($content_dir)) {
    log_debug('content_save', 'Directory not writable', $content_dir);
    return false;
  }
  
  // Generate frontmatter and markdown
  $frontmatter = _generate_yaml_frontmatter($meta);
  $markdown_content = _generate_markdown_file($frontmatter, $content_body);
  
  // Write file
  $bytes_written = file_put_contents($file_path, $markdown_content);
  if ($bytes_written === false) {
    log_debug('content_save', 'Failed to write file', $file_path);
    return false;
  }
  
  log_debug('content_save', 'File written successfully', $file_path, $bytes_written . ' bytes');
  
  // If this was a new content item, move images from _new directory to actual ID directory
  if ($is_new) {
    _move_images_from_new($type, $meta['id']);
  }
  
  // Invalidate cache
  _invalidate_content_cache($type);
  
  return [
    'id' => $meta['id'],
    'stub' => $meta['stub'],
  ];
}

/**
 * Handle save POST request
 * @param array $path_data
 * @param array $variables
 * @return array|false
 */
function content_handle_save($path_data, &$variables) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return false;
  }
  
  $content_type = $path_data['menu_item']['content_type'] ?? null;
  if (empty($content_type)) {
    $variables['error'] = 'Invalid content type';
    return false;
  }
  
  // Debug: log POST data
  log_debug('content_handle_save', 'POST data', $_POST);
  
  // Get required fields from config
  $content_type_config = $GLOBALS['config']['content_types'][$content_type] ?? [];
  $required_fields = $content_type_config['required_fields'] ?? [];
  
  // Only validate if required_fields is configured
  if (!empty($required_fields)) {
    // Field name translations for error messages
    $field_labels = [
      'title' => 'Title',
      'date' => 'Date',
      'stub' => 'Stub',
      'content' => 'Content',
    ];
    
    // Validate required fields with better error messages
    foreach ($required_fields as $field) {
      // Check if field exists and is not empty
      if (!isset($_POST[$field]) || $_POST[$field] === '' || $_POST[$field] === null) {
        $field_label = $field_labels[$field] ?? $field;
        log_debug('content_handle_save', 'Missing required field', $field, 'POST keys:', array_keys($_POST), 'required_fields:', $required_fields);
        $variables['error'] = $field_label . ' is required';
        return false;
      }
    }
  }
  
  $result = content_save($content_type, $_POST);
  
  if ($result === false) {
    log_debug('content_handle_save', 'content_save failed', $content_type);
    $variables['error'] = 'The save failed. Please check that all required fields are filled in.';
    return false;
  }
  
  return $result;
}

/**
 * Handle delete POST request
 * @param array $path_data
 * @param array $variables
 * @return bool
 */
function content_handle_delete($path_data, &$variables) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return false;
  }
  
  $content_type = $path_data['menu_item']['content_type'] ?? null;
  if (empty($content_type)) {
    $variables['error'] = 'Invalid content type';
    return false;
  }
  
  $content_id = $path_data['menu_item']['content_id'] ?? null;
  if (empty($content_id)) {
    $variables['error'] = 'Content ID not provided';
    return false;
  }
  
  log_debug('content_handle_delete', 'Deleting content', $content_type, $content_id);
  
  // Load content to get file path
  $content = get_content($content_type, $content_id, false);
  if (empty($content) || empty($content['path'])) {
    log_debug('content_handle_delete', 'Content not found', $content_type, $content_id);
    $variables['error'] = 'The content was not found';
    return false;
  }
  
  // Delete markdown file
  $file_path = CONTENT_PATH.'/'.$content_type.'/'.$content['path'];
  if (file_exists($file_path)) {
    if (!unlink($file_path)) {
      log_debug('content_handle_delete', 'Failed to delete file', $file_path);
      $variables['error'] = 'The file deletion failed';
      return false;
    }
    log_debug('content_handle_delete', 'File deleted', $file_path);
  } else {
    log_debug('content_handle_delete', 'File not found', $file_path);
  }
  
  // Invalidate cache
  _invalidate_content_cache($content_type);
  
  return true;
}
