<?php

require_once __DIR__ . '/BaseContentHandler.php';

/**
 * Gallery content handler
 * Handles gallery-specific processing (image extraction)
 */
class GalleryHandler extends BaseContentHandler {
  
  public function __construct() {
    parent::__construct('gallery');
  }
  
  // Gallery image extraction is now handled in BaseContentHandler::preprocessSave()
  // This handler can be extended for gallery-specific validation or processing if needed
  
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
}
