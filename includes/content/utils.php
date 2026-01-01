<?php

/**
 * Generate a markdown-safe abstract by stripping markdown syntax and truncating at word boundaries
 * @param string $markdown The markdown content to generate abstract from
 * @param int $length Maximum length of the abstract
 * @return string Plain text abstract, truncated at word boundaries with ellipsis if needed
 */
function _generate_markdown_safe_abstract($markdown, $length) {
  if (empty($markdown)) {
    return '';
  }
  
  if ($length <= 0) {
    return '';
  }
  
  // Strip markdown syntax to get plain text
  $plain_text = $markdown;
  
  // Remove code blocks (```code``` or ```code```)
  $plain_text = preg_replace('/```[\s\S]*?```/', '', $plain_text);
  
  // Remove inline code (`code`)
  $plain_text = preg_replace('/`[^`]*`/', '', $plain_text);
  
  // Remove images ![alt](url) or ![alt][ref]
  $plain_text = preg_replace('/!\[([^\]]*)\]\([^\)]*\)/', '', $plain_text);
  $plain_text = preg_replace('/!\[([^\]]*)\]\[[^\]]*\]/', '', $plain_text);
  
  // Remove links [text](url) or [text][ref] - keep the text part
  $plain_text = preg_replace('/\[([^\]]+)\]\([^\)]*\)/', '$1', $plain_text);
  $plain_text = preg_replace('/\[([^\]]+)\]\[[^\]]*\]/', '$1', $plain_text);
  
  // Remove reference-style links [text]: url (standalone lines)
  $plain_text = preg_replace('/^\s*\[[^\]]+\]:\s*.*$/m', '', $plain_text);
  
  // Remove headers (# Header or ## Header, etc.)
  $plain_text = preg_replace('/^#{1,6}\s+(.+)$/m', '$1', $plain_text);
  
  // Remove horizontal rules (---, ***, ___)
  $plain_text = preg_replace('/^[-*_]{3,}\s*$/m', '', $plain_text);
  
  // Remove blockquotes (> quote)
  $plain_text = preg_replace('/^>\s+(.+)$/m', '$1', $plain_text);
  
  // Remove list markers (-, *, +, 1., etc.)
  $plain_text = preg_replace('/^[\s]*[-*+]\s+(.+)$/m', '$1', $plain_text);
  $plain_text = preg_replace('/^[\s]*\d+\.\s+(.+)$/m', '$1', $plain_text);
  
  // Remove bold/italic formatting (**text**, *text*, __text__, _text_)
  $plain_text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $plain_text);
  $plain_text = preg_replace('/\*([^*]+)\*/', '$1', $plain_text);
  $plain_text = preg_replace('/__([^_]+)__/', '$1', $plain_text);
  $plain_text = preg_replace('/_([^_]+)_/', '$1', $plain_text);
  
  // Remove strikethrough (~~text~~)
  $plain_text = preg_replace('/~~([^~]+)~~/', '$1', $plain_text);
  
  // Remove HTML tags if any
  $plain_text = strip_tags($plain_text);
  
  // Normalize whitespace - replace multiple spaces/newlines with single space
  $plain_text = preg_replace('/\s+/', ' ', $plain_text);
  
  // Trim whitespace
  $plain_text = trim($plain_text);
  
  // If content is shorter than or equal to target length, return as is
  if (mb_strlen($plain_text) <= $length) {
    return $plain_text;
  }
  
  // Truncate at word boundary
  $truncated = mb_substr($plain_text, 0, $length);
  
  // Find last space before the limit to break at word boundary
  $last_space = mb_strrpos($truncated, ' ');
  if ($last_space !== false && $last_space > $length * 0.5) {
    // Only break at word boundary if it's not too early (at least 50% of target length)
    $truncated = mb_substr($truncated, 0, $last_space);
  }
  
  // Add ellipsis
  return trim($truncated) . '...';
}

/**
 * Extract all image elements from markdown content
 * Returns an array of images with url and subtitle (alt text)
 * 
 * @param string $markdown_content
 * @return array Array of ['url' => string, 'subtitle' => string]
 */
function _extract_gallery_images_from_markdown($markdown_content) {
  $images = [];
  
  // Match markdown image syntax: ![alt text](url) or ![alt text](url "title")
  // Pattern matches:
  // - ![](url) - no alt text
  // - ![alt text](url) - alt text only
  // - ![alt text](url "title") - alt text and title with double quotes
  // - ![alt text](url 'title') - alt text and title with single quotes
  // The pattern captures:
  // - Group 1: alt text (may be empty)
  // - Group 2: URL
  // - Group 3: title (optional, may be empty)
  $pattern = '/!\[([^\]]*)\]\(([^\)\s]+)(?:\s+["\']([^"\']*)["\'])?\)/';
  
  if (preg_match_all($pattern, $markdown_content, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
      $alt_text = isset($match[1]) ? trim($match[1]) : '';
      $url = isset($match[2]) ? trim($match[2]) : '';
      $title = isset($match[3]) ? trim($match[3]) : '';
      
      // Use title as subtitle if available, otherwise use alt text
      $subtitle = !empty($title) ? $title : $alt_text;
      
      // Only add if URL is not empty
      if (!empty($url)) {
        $images[] = [
          'url' => $url,
          'subtitle' => $subtitle
        ];
      }
    }
  }
  
  return $images;
}

/**
 * Resolve a menu item for a given content type
 * @param string $type
 * @return array|false
 */
function _content_resolve_menu_item($type) {
  foreach ($GLOBALS['config']['menu'] as $menu_item) {
    if (isset($menu_item['content_type']) && $menu_item['content_type'] === $type) {
      return $menu_item;
    }
  }
  return false;
}
