<?php

require_once __DIR__ . '/BaseContentHandler.php';

/**
 * Post content handler
 * Handles post-specific processing
 */
class PostHandler extends BaseContentHandler {
  
  public function __construct() {
    parent::__construct('post');
  }
  
  /**
   * Process optional metadata fields for posts
   */
  public function processOptionalMetadata(&$meta, $post_data) {
    // Set edit_date to current timestamp when saving
    $meta['edit_date'] = date('Y-m-d H:i:s');
  }
  
  /**
   * Process content for edit form - handle content body
   */
  public function processForEdit(&$content, $content_type) {
    // Get raw markdown content body
    $content_file = CONTENT_PATH.'/'.$content_type.'/'.$content['path'];
    if (file_exists($content_file)) {
      $file_content = file_get_contents($content_file);
      if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $file_content, $matches)) {
        $content['content'] = $matches[2];
      }
    }
  }
  
  /**
   * Get redirect URL after successful save
   * For posts, redirect to listing page instead of view page
   * @param array $result Save result containing 'id' and 'stub'
   * @param string $base_path Base path for the content type
   * @return string Redirect URL
   */
  public function getRedirectUrlAfterSave($result, $base_path) {
    // Redirect to listing page instead of view page
    return '/'.$base_path;
  }
}
