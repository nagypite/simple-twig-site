<?php

require_once __DIR__ . '/BaseContentHandler.php';

/**
 * Author content handler
 * Handles author-specific structure and validation
 */
class AuthorHandler extends BaseContentHandler {
  
  public function __construct() {
    parent::__construct('author');
  }
  
  /**
   * Get default structure for new author
   */
  public function getDefaultStructure() {
    return [
      'id' => '',
      'title' => '',
      'stub' => '',
      'image' => '',
      'abstract' => '',
      'abstract_cn' => '',
    ];
  }
}
