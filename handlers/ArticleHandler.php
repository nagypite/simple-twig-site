<?php

require_once __DIR__ . '/BaseContentHandler.php';

/**
 * Article content handler
 * Handles article-specific structure and validation
 */
class ArticleHandler extends BaseContentHandler {
  
  public function __construct() {
    parent::__construct('article');
  }
  
  /**
   * Get default structure for new article
   */
  public function getDefaultStructure() {
    return [
      'id' => '',
      'title' => '',
      'subtitle' => '',
      'title_cn' => '',
      'date' => date('Y-m-d'),
      'keywords' => [],
      'stub' => '',
      'author_id' => '',
      'image' => '',
      'abstract' => '',
      'content' => '',
    ];
  }
  
  /**
   * Process optional metadata fields for articles
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
