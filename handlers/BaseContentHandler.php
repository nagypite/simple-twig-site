<?php

/**
 * Base content handler class
 * Provides common functionality for content type handlers
 */
class BaseContentHandler {
  protected $content_type;
  
  public function __construct($content_type) {
    $this->content_type = $content_type;
  }
  
  /**
   * Get default structure for new content
   * @return array
   */
  public function getDefaultStructure() {
    return [
      'id' => '',
      'title' => '',
      'stub' => '',
    ];
  }
  
  /**
   * Process content list (type-specific processing)
   * @param array $content_list
   * @param array $path_data
   * @param array $variables
   * @return bool True if processing handled, false to use default processing
   */
  public function processList(&$content_list, &$path_data, &$variables) {
    return false; // Default: use standard processing
  }
  
  /**
   * Validate content data
   * @param array $post_data
   * @return bool|string True if valid, error message string if invalid
   */
  public function validate($post_data) {
    return true; // Default: no additional validation
  }
  
  /**
   * Preprocess content before saving
   * @param array $meta
   * @param array $post_data
   * @param array $content_type_config
   * @return void
   */
  public function preprocessSave(&$meta, $post_data, $content_type_config = []) {
    // Process gallery images if 'gallery' is in preprocess config
    $required_fields = $content_type_config['required_fields'] ?? [];
    if (in_array('content', $required_fields) && !empty($post_data['content'])) {
      if (isset($content_type_config['preprocess']) && in_array('gallery', $content_type_config['preprocess'])) {
        // Ensure _extract_gallery_images_from_markdown is available
        if (function_exists('_extract_gallery_images_from_markdown')) {
          $gallery_images = _extract_gallery_images_from_markdown($post_data['content']);
          if (!empty($gallery_images)) {
            $meta['gallery'] = json_encode($gallery_images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
          }
        }
      }
    }
  }
  
  /**
   * Update image URLs in gallery JSON when creating new content
   * @param array $meta
   * @param string $type
   * @param int $new_id
   * @return void
   */
  public function updateGalleryImageUrls(&$meta, $type, $new_id) {
    // Update image URLs in gallery JSON if it exists
    if (!empty($meta['gallery']) && is_string($meta['gallery'])) {
      // Ensure _update_image_urls_in_content is available
      if (function_exists('_update_image_urls_in_content')) {
        $gallery_json = _update_image_urls_in_content($meta['gallery'], $type, $new_id);
        $meta['gallery'] = $gallery_json;
        log_debug('BaseContentHandler', 'Updated image URLs in gallery JSON', 'replaced _new with', $new_id);
      }
    }
  }
  
  /**
   * Process optional metadata fields for save
   * @param array $meta
   * @param array $post_data
   * @return void
   */
  public function processOptionalMetadata(&$meta, $post_data) {
    // Default: no optional fields
  }
  
  /**
   * Process content for edit form
   * @param array $content
   * @param string $content_type
   * @return void
   */
  public function processForEdit(&$content, $content_type) {
    // Default: no processing
  }
  
  /**
   * Get redirect URL after successful save
   * @param array $result Save result containing 'id' and 'stub'
   * @param string $base_path Base path for the content type
   * @return string Redirect URL
   */
  public function getRedirectUrlAfterSave($result, $base_path) {
    // Default: redirect to view page
    return '/'.$base_path.'/'.$result['id'].'-'.$result['stub'];
  }
}
