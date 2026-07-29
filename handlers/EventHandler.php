<?php

require_once __DIR__ . '/BaseContentHandler.php';

/**
 * Event content handler
 * Handles event-specific logic like sticky/other splitting
 */
class EventHandler extends BaseContentHandler {
  
  public function __construct() {
    parent::__construct('event');
  }
  
  /**
   * Process event list: split into sticky and other events
   */
  public function processList(&$content_list, &$path_data, &$variables) {
    if (empty($content_list['content'])) {
      return false;
    }
    
    $sticky_events = [];
    $other_events = [];

    foreach ($content_list['content'] as $event) {
      $event_sticky = empty($event['sticky']) ? false : $event['sticky'];
      if ($event_sticky) {
        $sticky_events[] = $event;
      }
      else {
        $other_events[] = $event;
      }
    }

    // Sort sticky events descending (newest first)
    usort($sticky_events, function($a, $b) {
      $date_a = isset($a['date']) ? $a['date'] : '1970-01-01';
      $date_b = isset($b['date']) ? $b['date'] : '1970-01-01';
      return strcmp($date_b, $date_a);
    });

    // Sort other events descending (latest first)
    usort($other_events, function($a, $b) {
      $date_a = isset($a['date']) ? $a['date'] : '1970-01-01';
      $date_b = isset($b['date']) ? $b['date'] : '1970-01-01';
      return strcmp($date_b, $date_a);
    });

    // Process URLs for both arrays
    foreach ($sticky_events as &$event) {
      _content_process_urls($event, $path_data['menu_item']);
      _content_process_relations($event, 1);
    }
    foreach ($other_events as &$event) {
      _content_process_urls($event, $path_data['menu_item']);
      _content_process_relations($event, 1);
    }

    $variables['sticky_events'] = $sticky_events;
    $variables['other_events'] = $other_events;
    // Keep content_list for backward compatibility if needed
    $variables['content_list'] = $content_list;
    
    return true; // Handled
  }
  
  /**
   * Process optional metadata fields for events
   */
  public function processOptionalMetadata(&$meta, $post_data) {
    if (!empty($post_data['subtitle'])) {
      $meta['subtitle'] = $post_data['subtitle'];
    }
    if (!empty($post_data['title_cn'])) {
      $meta['title_cn'] = $post_data['title_cn'];
    }
    if (!empty($post_data['keywords'])) {
      $keywords = is_array($post_data['keywords']) ? $post_data['keywords'] : explode(',', $post_data['keywords']);
      $keywords = array_map('trim', $keywords);
      $keywords = array_filter($keywords);
      if (!empty($keywords)) {
        $meta['keywords'] = $keywords;
      }
    }
  }
  
  /**
   * Process content for edit form - handle keywords and content body
   */
  public function processForEdit(&$content, $content_type) {
    // Ensure keywords is an array for the form
    if (isset($content['keywords']) && is_string($content['keywords'])) {
      $content['keywords'] = explode(',', $content['keywords']);
      $content['keywords'] = array_map('trim', $content['keywords']);
    } elseif (!isset($content['keywords'])) {
      $content['keywords'] = [];
    }

    // Get raw markdown content body
    $content_file = CONTENT_PATH.'/'.$content_type.'/'.$content['path'];
    if (file_exists($content_file)) {
      $file_content = file_get_contents($content_file);
      if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $file_content, $matches)) {
        $content['content'] = $matches[2];
      }
    }
  }
}
