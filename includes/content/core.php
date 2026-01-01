<?php

// Ensure YAML functions are available
require_once __DIR__ . '/yaml.php';

define('CONTENT_CACHE_PATH', CACHE_PATH.'/content');

/**
 * This function list all content files for a given type
 * reading only markdown files and processing them with the yaml frontmatter parser
 * returning an array of arrays with the frontmatter properties and file path
 * 
 * @param string $type
 * @return array
 * [
 *   [
 *     'id' => 1,
 *     'type' => 'article',
 *     'path' => 'filename',
 *     'title' => 'Title',
 *     'date' => '2025-10-16',
 *     'keywords' => 'Keyword1, Keyword2',
 *     'stub' => 'stub-name',
 *   ],
 * ]
 */
function list_content($type, $preprocess = true) {
  $content_type_config = $GLOBALS['config']['content_types'][$type];

  $cache_file = CONTENT_CACHE_PATH.'/'.$type.'.php';
  
  // Check if cache file exists and return cached data
  if (file_exists($cache_file)) {
    return include $cache_file;
  }
  
  // Generate content from markdown files
  $content_files = _generate_meta_from_files($type);

  $content_result = [
    'content' => $content_files,
  ];

  if ($preprocess) {
    if (isset($content_type_config['preprocess']) && in_array('keywords', $content_type_config['preprocess'])) {
      $keywords = [];
      foreach ($content_files as $content) {
        if (isset($content['keywords'])) {
          $keywords = array_merge($keywords, $content['keywords']);
        }
      }
      $keywords = array_unique($keywords);
      $content_result['keywords'] = sort_intl($keywords);
    }
  }
  
  // Ensure cache directory exists
  if (!is_dir(CONTENT_CACHE_PATH)) {
    mkdir(CONTENT_CACHE_PATH, 0755, true);
  }
  
  // Write cache file
  $cache_content = "<?php\nreturn " . var_export($content_result, true) . ";\n";
  file_put_contents($cache_file, $cache_content);
  
  return $content_result;
}

/**
 * Generate content metadata from markdown files
 * @param string $type
 * @return array
 */
function _generate_meta_from_files($type) {
  $content_dir = CONTENT_PATH.'/'.$type;
  
  // Check if directory exists
  if (!is_dir($content_dir)) {
    return [];
  }
  
  $content_files = [];
  $files = glob($content_dir.'/*.md');
  
  foreach ($files as $file) {
    $processed_file = _process_file($file, $type);
    if (empty($processed_file)) {
      continue;
    }
    if (empty($processed_file['id'])) {
      $processed_file['id'] = uniqid();
    }
    $content_files[$processed_file['id']] = $processed_file;
  }

  // Sort by date (newest first)
  uasort($content_files, function($a, $b) {
    $date_a = isset($a['date']) ? $a['date'] : '1970-01-01';
    $date_b = isset($b['date']) ? $b['date'] : '1970-01-01';
    return strcmp($date_b, $date_a);
  });
  
  return $content_files;
}

/**
 * Process a single file
 * @param string $file
 * @param string $type
 * @param boolean $include_content
 * @return array
 */
function _process_file($file, $type, $include_content = false) {
    $file_data = [];
    $content = file_get_contents($file);
    if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
        $frontmatter = $matches[1];
        $meta = _parse_yaml_frontmatter($frontmatter);
        
        // Get relative path to the markdown file
        $relative_path = basename($file);
        
        // Flatten meta properties and add path
        $file_data = array_merge($meta, [
          'type' => $type,
          'path' => $relative_path,
        ]);

        if ($include_content) {
            // Remove frontmatter from content
            $content = $matches[2];
        }
    }

    if ($include_content) {
        $file_data['content'] = $content;
    }

    return $file_data;
}

/**
 * Get content from a given type and stub
 * @param string $type
 * @param string $id
 * @param int $get_relation_levels
 * @return array|false
 */
function get_content($type, $id, $get_relation_levels = 0) {
  if (!is_numeric($id)) {
    if (preg_match('/^(\d+)-(.+)$/', $id, $matches)) {
      $id = $matches[1];
    }
  }

  if (!is_numeric($id)) {
    log_debug('get_content', 'id is not numeric', $id);
    return false;
  }

  if ($get_relation_levels === true) {
    $get_relation_levels = 3;
  }

  // Convert ID to integer for comparison
  $id = (int)$id;
  
  $content_list = list_content($type);
  foreach ($content_list['content'] as $content) {
    $content_id = isset($content['id']) ? (int)$content['id'] : 0;
    if ($content_id === $id) {
      $content_file = CONTENT_PATH.'/'.$type.'/'.$content['path'];
      if (!file_exists($content_file)) {
        log_debug('get_content', 'Content file not found', $content_file);
        return false;
      }

      $content = _process_file($content_file, $type, true);
      
      // Store absolute file path for caching purposes
      $content['_file_path'] = $content_file;

      // Preprocess markdown if configured
      $content_type_config = $GLOBALS['config']['content_types'][$type] ?? [];
      if (isset($content_type_config['preprocess'])) {
        _content_process_markdown($content, $content_file, $content_type_config['preprocess']);
      }

      if ($get_relation_levels > 0) {
        _content_process_relations($content, $get_relation_levels);
      }

      _content_process_urls($content);

      return $content;
    }
  }

  log_debug('get_content', 'Content not found in list', 'type', $type, 'id', $id);
  return false;
}

/**
 * Get content by stub
 * @param string $content_type
 * @param string $stub
 * @param int $get_relation_levels
 * @return array|false
 */
function get_content_by_stub($content_type, $stub, $get_relation_levels = 0) {
  $content_list = list_content($content_type, false);
  foreach ($content_list['content'] as $content) {
    if ($content['stub'] === $stub) {
      if ($get_relation_levels) {
        return get_content($content_type, $content['id'], $get_relation_levels);
      }
      return $content;
    }
  }
  return false;
}
