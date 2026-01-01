<?php

// Ensure content functions are available
require_once __DIR__ . '/content.php';

/**
 * Preprocess variables for index page
 * @param array $path_data
 * @param array $variables
 * @return bool
 */
function index_serve_preprocess(&$path_data, &$variables) {
  // Load recent posts for index page
  $post_list = list_content('post', false);
  $recent_posts = [];
  
  if (!empty($post_list['content'])) {
    // Get first 2 posts (already sorted by date, newest first)
    $posts = array_slice($post_list['content'], 0, 2, true);
    
    // Find menu item for posts to process URLs correctly
    $post_menu_item = null;
    foreach ($GLOBALS['config']['menu'] as $menu_item) {
      if (isset($menu_item['content_type']) && $menu_item['content_type'] === 'post') {
        $post_menu_item = $menu_item;
        break;
      }
    }
    
    // Process URLs for each post
    foreach ($posts as &$post) {
      _content_process_urls($post, $post_menu_item);
      $recent_posts[] = $post;
    }
  }
  
  $variables['recent_posts'] = $recent_posts;
  
  // Load index gallery for carousel by stub 'index-gallery'
  $index_gallery = get_content_by_stub('gallery', 'index-gallery');
  $gallery_images = [];

  // Parse gallery JSON field if it exists
  if ($index_gallery && !empty($index_gallery['gallery'])) {
    $gallery_json = $index_gallery['gallery'];
    // Handle both JSON string and already decoded array
    if (is_string($gallery_json)) {
      // unescape quotes
      $gallery_json = str_replace('\\"', '"', $gallery_json);
      $decoded = json_decode($gallery_json, true);
      
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $gallery_images = $decoded;
      }
      else {
        log_debug('index_serve_preprocess', 'gallery_json is string', $gallery_json, 'decoded', $decoded, 'json_last_error', json_last_error());
        file_put_contents(LOG_PATH.'/debug.log',$gallery_json."\n", FILE_APPEND);
      }
    } elseif (is_array($gallery_json)) {
      log_debug('index_serve_preprocess', 'gallery_json is array', $gallery_json);
      $gallery_images = $gallery_json;
    }
  }
  
  // Add gallery data to variables
  $variables['index_gallery'] = $index_gallery;
  $variables['gallery_images'] = $gallery_images;
  $variables['has_gallery_images'] = !empty($gallery_images);
  
  return true;
}
