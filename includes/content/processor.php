<?php

// Ensure utils functions are available
require_once __DIR__ . '/utils.php';

/**
 * Process markdown for content fields based on preprocess configuration
 * @param array $content
 * @param string $file_path
 * @param array $preprocess_config
 * @return void
 */
function _content_process_markdown(&$content, $file_path, $preprocess_config) {
  // Check if markdown preprocessing is enabled
  $markdown_config = $GLOBALS['config']['markdown'] ?? [];
  if (empty($markdown_config['cache_enabled'])) {
    return; // Markdown processing disabled
  }
  
  // Get singleton adapter instance
  static $markdownAdapter = null;
  if ($markdownAdapter === null) {
    // We need to ensure bootstrap.php is loaded to get the adapter class
    if (!class_exists('MichelfMarkdownAdapter')) {
      return; // Adapter not available
    }
    $markdownAdapter = new MichelfMarkdownAdapter($markdown_config);
  }
  
  // Process abstract if 'abstract' is in preprocess config
  if (in_array('abstract', $preprocess_config) && !empty($content['abstract'])) {
    $content['abstract_html'] = $markdownAdapter->convert(
      $content['abstract'],
      $file_path,
      'abstract'
    );
  }
  
  // Process abstract_cn if 'abstract' is in preprocess config
  if (in_array('abstract', $preprocess_config) && !empty($content['abstract_cn'])) {
    $content['abstract_cn_html'] = $markdownAdapter->convert(
      $content['abstract_cn'],
      $file_path,
      'abstract_cn'
    );
  }
  
  // Process content if 'content' is in preprocess config
  if (in_array('content', $preprocess_config) && !empty($content['content'])) {
    $content['content_html'] = $markdownAdapter->convert(
      $content['content'],
      $file_path,
      'content'
    );
  }
}

/**
 * Process relations for a given content
 * @param array $content
 * @param int $get_relation_levels
 * @return void
 */
function _content_process_relations(&$content, $get_relation_levels) {
  $type = $content['type'];
  if (empty($GLOBALS['config']['content_types'][$type]['relations'])) {
    return;
  }

  foreach ($GLOBALS['config']['content_types'][$type]['relations'] as $relation_key => $relation_content_type) {
    if (empty($content[$relation_key])) {
      continue;
    }

    $relation_id = $content[$relation_key];
    $content[$relation_content_type] = get_content($relation_content_type, $relation_id, $get_relation_levels - 1);
  }
}

/**
 * Process URLs for a given content
 * @param array $content
 * @param array|null $menu_item
 * @return void
 */
function _content_process_urls(&$content, $menu_item = NULL) {
  if (empty($menu_item)) {
    $menu_item = _content_resolve_menu_item($content['type']);
  }

  if ($menu_item) {
    $type_path = $menu_item['path'];
    $content['url'] = '/'.$type_path.'/'.$content['id'].'-'.$content['stub'];
  }

  if (!empty($content['image'])) {
    $content['image_url'] = '/content/'.$content['type'].'/images/'.$content['id'].'/'.$content['image'];
  }
}

/**
 * Process siblings for a given content
 * @param array $content
 * @return void
 */
function _content_process_siblings(&$content) {
  $type = $content['type'];
  if (!in_array('siblings', $GLOBALS['config']['content_types'][$type]['preprocess'] ?? [])) {
    return;
  }

  $siblings = [
    'previous' => null,
    'next' => null,
  ];
  $found_current = false;
  $content_list = list_content($type, false);
  foreach ($content_list['content'] as $sibling) {
    if ($sibling['id'] !== $content['id']) {
      if (!$found_current) {
        _content_process_urls($sibling);
        $siblings['previous'] = $sibling;
        continue;
      }
      else if (is_null($siblings['next'])) {
        _content_process_urls($sibling);
        $siblings['next'] = $sibling;
        break;
      }
    }
    else {
      $found_current = true;
    }
  }
  $content['siblings'] = $siblings;
}
