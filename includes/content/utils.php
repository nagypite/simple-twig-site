<?php

/**
 * Strip markdown to plain text for abstracts and similar summaries.
 * @param string $markdown
 * @param int|null $maxLength Optional max length; truncates at word boundary with ellipsis
 * @return string
 */
function _markdown_to_plain_text($markdown, $maxLength = null) {
  if ($markdown === null || $markdown === '') {
    return '';
  }

  $plain_text = $markdown;

  // Remove code blocks and inline code
  $plain_text = preg_replace('/```[\s\S]*?```/', '', $plain_text);
  $plain_text = preg_replace('/`[^`]*`/', '', $plain_text);

  // Remove images
  $plain_text = preg_replace('/!\[([^\]]*)\]\([^\)]*\)/', '', $plain_text);
  $plain_text = preg_replace('/!\[([^\]]*)\]\[[^\]]*\]/', '', $plain_text);

  // Links — keep link text only
  $plain_text = preg_replace('/\[([^\]]+)\]\([^\)]*\)/', '$1', $plain_text);
  $plain_text = preg_replace('/\[([^\]]+)\]\[[^\]]*\]/', '$1', $plain_text);

  // Reference definitions and footnotes
  $plain_text = preg_replace('/^\s*\[[^\]]+\]:\s*.*$/m', '', $plain_text);
  $plain_text = preg_replace('/\[\^[^\]]+\]/', '', $plain_text);
  $plain_text = preg_replace('/^\s*\[\^[^\]]+\]:\s*.*$/m', '', $plain_text);

  // Headers, rules, blockquotes, lists
  $plain_text = preg_replace('/^#{1,6}\s+(.+)$/m', '$1', $plain_text);
  $plain_text = preg_replace('/^[-*_]{3,}\s*$/m', '', $plain_text);
  $plain_text = preg_replace('/^>\s+(.+)$/m', '$1', $plain_text);
  $plain_text = preg_replace('/^[\s]*[-*+]\s+(.+)$/m', '$1', $plain_text);
  $plain_text = preg_replace('/^[\s]*\d+\.\s+(.+)$/m', '$1', $plain_text);

  // Emphasis and strikethrough
  $plain_text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $plain_text);
  $plain_text = preg_replace('/\*([^*]+)\*/', '$1', $plain_text);
  $plain_text = preg_replace('/__([^_]+)__/', '$1', $plain_text);
  $plain_text = preg_replace('/_([^_]+)_/', '$1', $plain_text);
  $plain_text = preg_replace('/~~([^~]+)~~/', '$1', $plain_text);

  // Autolinks and HTML
  $plain_text = preg_replace('/<((?:https?|ftp):\/\/[^>]+)>/i', '$1', $plain_text);
  $plain_text = strip_tags($plain_text);

  // Markdown escape backslashes before punctuation
  $plain_text = preg_replace('~\\\\([\\[\\]`_*()#+.!\\-{}])~u', '$1', $plain_text);

  // Bilingual marker belongs in full content only, not summaries
  $plain_text = preg_replace('/^(?:\\\\)*\[HU\/CN\]\s*/u', '', $plain_text);

  $plain_text = preg_replace('/\s+/u', ' ', $plain_text);
  $plain_text = trim($plain_text);

  if ($maxLength === null || $maxLength <= 0 || mb_strlen($plain_text) <= $maxLength) {
    return _plain_text_trim_abstract_tail($plain_text);
  }

  $slice = mb_substr($plain_text, 0, $maxLength);
  $last_space = mb_strrpos($slice, ' ');
  if ($last_space !== false) {
    $truncated = mb_substr($slice, 0, $last_space);
  } else {
    $truncated = $slice;
  }

  $truncated = _plain_text_trim_abstract_tail($truncated);
  if ($truncated === '') {
    return '';
  }

  if (preg_match('/[.!?…][\'"»›」』】)]*$/u', $truncated)) {
    return $truncated;
  }

  return $truncated . '...';
}

/**
 * Trim trailing whitespace and punctuation unsuitable for abstract endings.
 * Sentence closers (. ! ? …) may remain; commas, hyphens, and similar are removed.
 */
function _plain_text_trim_abstract_tail(string $text): string {
  $text = trim($text);
  if ($text === '') {
    return '';
  }

  $strip = ',;:/\\-–—·•|';
  while ($text !== '') {
    $last = mb_substr($text, -1);
    if (preg_match('/\s/u', $last)) {
      $text = trim($text);
      continue;
    }
    if (mb_strpos($strip, $last) !== false) {
      $text = trim(mb_substr($text, 0, -1));
      continue;
    }
    break;
  }

  return trim($text);
}

/**
 * Generate a plain-text abstract from markdown body content.
 * @param string $markdown The markdown content to generate abstract from
 * @param int $length Maximum length of the abstract
 * @return string Plain text abstract, truncated at word boundaries with ellipsis if needed
 */
function _generate_markdown_safe_abstract($markdown, $length) {
  return _markdown_to_plain_text($markdown, $length);
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

/**
 * Resolve the share/preview image URL for a content item.
 * @param array $content
 * @return string|null Relative or absolute image URL
 */
function _content_resolve_share_image_url($content) {
  if (!empty($content['image_url'])) {
    return $content['image_url'];
  }

  if (!empty($content['gallery'])) {
    $gallery = is_string($content['gallery'])
      ? json_decode($content['gallery'], true)
      : $content['gallery'];
    if (!empty($gallery[0]['url'])) {
      return $gallery[0]['url'];
    }
  }

  return null;
}

/**
 * Resolve a local filesystem path for a share image URL.
 * @param string $image_url
 * @return string|null
 */
function _content_share_image_local_path($image_url) {
  if ($image_url === '') {
    return null;
  }

  if (preg_match('#^https?://#i', $image_url)) {
    $site_host = parse_url($GLOBALS['config']['siteurl'] ?? '', PHP_URL_HOST);
    $image_host = parse_url($image_url, PHP_URL_HOST);
    if (empty($site_host) || empty($image_host) || strcasecmp($site_host, $image_host) !== 0) {
      return null;
    }
    $path = parse_url($image_url, PHP_URL_PATH);
    $image_url = $path ?: '';
  }

  if ($image_url === '') {
    return null;
  }

  $relative = (strpos($image_url, '/') === 0 ? '' : '/') . $image_url;
  if (strpos($relative, '/assets/') === 0) {
    $public_path = BASE_PATH . '/public' . $relative;
    if (is_readable($public_path)) {
      return $public_path;
    }
  }

  return BASE_PATH . $relative;
}

/**
 * Build page meta (description + Open Graph / Twitter) for a content item.
 * @param array $content
 * @param string $page_url Canonical absolute page URL
 * @return array
 */
function _content_build_page_meta($content, $page_url) {
  $site_meta = $GLOBALS['config']['meta'];
  $sitename = $GLOBALS['config']['sitename'];
  $siteurl = rtrim($GLOBALS['config']['siteurl'], '/');

  $title = trim($content['title'] ?? '');
  if ($title === '') {
    $title = $sitename;
  }
  $page_title = ($title === $sitename) ? $title : $title . ' - ' . $sitename;

  $description = '';
  if (!empty($content['abstract'])) {
    $description = $content['abstract'];
  } elseif (!empty($content['subtitle'])) {
    $description = $content['subtitle'];
  }
  $description = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $description = preg_replace('/\s+/u', ' ', trim($description));
  if (mb_strlen($description) > 300) {
    $description = mb_substr($description, 0, 297) . '...';
  }
  if ($description === '') {
    $description = $site_meta['description'] ?? '';
  }

  $author = $content['author']['title'] ?? ($site_meta['author'] ?? 'simple-twig-site');

  $meta = [
    'title' => $page_title,
    'description' => $description,
    'author' => $author,
    'canonical' => $page_url,
    'og' => [
      'type' => 'article',
      'title' => $page_title,
      'description' => $description,
      'url' => $page_url,
      'site_name' => $sitename,
      'locale' => 'hu_HU',
    ],
    'twitter' => [
      'card' => 'summary',
      'title' => $page_title,
      'description' => $description,
    ],
  ];

  if (!empty($content['date'])) {
    $timestamp = strtotime($content['date']);
    if ($timestamp !== false) {
      $meta['og']['published_time'] = date('c', $timestamp);
    }
  }

  $image_url = _content_resolve_share_image_url($content);
  if ($image_url !== null) {
    _meta_enrich_with_image($meta, $image_url, $title);
  }

  return $meta;
}
