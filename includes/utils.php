<?php

/**
 * Function for sorting an array with proper locale
 * @param array $array
 * @param string $locale
 * @param boolean $case_insensitive
 * @return array
 */
function sort_intl($array, $locale = 'hu_HU', $case_insensitive = true) {
  if ($case_insensitive) {
    uasort($array, '_sort_compare_hu_insensitive');
  }
  else {
    uasort($array, '_sort_compare_hu_sensitive');
  }

  return $array;
}

/**
 * Fallback comparison function for unaccented, case-insensitive sort.
 * This function should only be used if the Intl extension is unavailable.
 *
 * @param string $a
 * @param string $b
 * @return int
 */
function _sort_compare_hu_insensitive($a, $b) {
    // Map of accented to unaccented characters (Hungarian specific)
    $accent_map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
        'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O',
        'Ő' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U',
    ];

    // 1. Unaccent the strings using strtr
    $normalized_a = strtr($a, $accent_map);
    $normalized_b = strtr($b, $accent_map);

    // 2. Perform case-insensitive, natural comparison on the normalized strings
    // strnatcasecmp provides natural sorting (e.g., file2 before file10)
    return strnatcasecmp($normalized_a, $normalized_b);
}

/**
 * Fallback comparison function for unaccented, case-sensitive sort.
 * This function should only be used if the Intl extension is unavailable.
 *
 * @param string $a
 * @param string $b
 * @return int
 */
function _sort_compare_hu_sensitive($a, $b) {
    // Map of accented to unaccented characters (Hungarian specific)
    $accent_map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
        'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O',
        'Ő' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U',
    ];

    // 1. Unaccent the strings using strtr
    $normalized_a = strtr($a, $accent_map);
    $normalized_b = strtr($b, $accent_map);

    // 2. Perform case-sensitive, natural comparison on the normalized strings
    // strnatcasecmp provides natural sorting (e.g., file2 before file10)
    return strnatcmp($normalized_a, $normalized_b);
}

/**
 * Add Open Graph / Twitter image fields to a meta array.
 * @param array $meta
 * @param string $image_url Relative or absolute image URL
 * @param string $image_alt
 * @return void
 */
function _meta_enrich_with_image(array &$meta, $image_url, $image_alt = '') {
  if ($image_url === null || $image_url === '') {
    return;
  }

  if (!function_exists('_content_share_image_local_path')) {
    require_once __DIR__ . '/content/utils.php';
  }

  $siteurl = rtrim($GLOBALS['config']['siteurl'], '/');
  $absolute_image = preg_match('#^https?://#i', $image_url)
    ? $image_url
    : $siteurl . (strpos($image_url, '/') === 0 ? '' : '/') . $image_url;

  $meta['og']['image'] = $absolute_image;
  $meta['og']['image_alt'] = $image_alt !== '' ? $image_alt : ($meta['og']['title'] ?? '');
  $meta['twitter']['image'] = $absolute_image;
  $meta['twitter']['card'] = 'summary_large_image';

  $local_path = _content_share_image_local_path($image_url);
  if ($local_path && is_readable($local_path)) {
    $size = @getimagesize($local_path);
    if ($size) {
      $meta['og']['image_width'] = $size[0];
      $meta['og']['image_height'] = $size[1];
      if (!empty($size['mime'])) {
        $meta['og']['image_type'] = $size['mime'];
      }
    }
  }
}

/**
 * Extract a Twig block's raw content from a template source string.
 * @param string $source
 * @param string $block_name
 * @return string|null
 */
function _template_extract_block($source, $block_name) {
  $pattern = '/\{%\s*block\s+' . preg_quote($block_name, '/') . '\s*%\}(.*?)\{%\s*endblock\s*%\}/s';
  if (!preg_match($pattern, $source, $matches)) {
    return null;
  }

  return trim($matches[1]);
}

/**
 * Extract an explicit meta override from a Twig comment, e.g.
 * {# meta:og:image /assets/images/hero.jpg #}
 * @param string $source
 * @param string $key
 * @return string|null
 */
function _template_extract_meta_comment($source, $key) {
  $pattern = '/\{#\s*meta:' . preg_quote($key, '/') . '\s+(.+?)\s*#\}/';
  if (!preg_match($pattern, $source, $matches)) {
    return null;
  }

  return trim($matches[1]);
}

/**
 * Whether an image URL should be skipped when auto-detecting share images.
 * @param string $src
 * @return bool
 */
function _is_excluded_share_image($src) {
  if ($src === '' || strpos($src, 'data:') === 0) {
    return true;
  }

  $exclude_patterns = [
    '#/assets/images/logo\.png#i',
    '#/assets/favicon/#i',
    '#/assets/images/content-default-image\.jpg#i',
  ];

  foreach ($exclude_patterns as $pattern) {
    if (preg_match($pattern, $src)) {
      return true;
    }
  }

  return false;
}

/**
 * Find the first share-worthy image in a template source string.
 * @param string $source
 * @return string|null
 */
function _template_extract_first_share_image($source) {
  if (!preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $source, $matches)) {
    return null;
  }

  foreach ($matches[1] as $src) {
    $src = trim($src);
    if (!_is_excluded_share_image($src)) {
      return $src;
    }
  }

  return null;
}

/**
 * Build page meta for a static Twig template (Open Graph / Twitter).
 * Uses optional {% block og_image %} / {% block og_description %} overrides,
 * otherwise auto-detects the first suitable <img> in the template file.
 * @param string $template Template path relative to pages/ (e.g. rolunk/volunteer.html)
 * @param string $page_url Canonical absolute page URL
 * @return array
 */
function _template_build_page_meta($template, $page_url) {
  $site_meta = $GLOBALS['config']['meta'];
  $sitename = $GLOBALS['config']['sitename'];

  $template_path = PAGES_PATH . '/' . $template;
  $source = file_exists($template_path) ? file_get_contents($template_path) : '';

  $title_block = _template_extract_block($source, 'title');
  if ($title_block !== null) {
    $page_title = str_replace('{{sitename}}', $sitename, $title_block);
    $page_title = preg_replace('/\{\{.*?\}\}/', '', $page_title);
    $page_title = trim(preg_replace('/\s+/u', ' ', $page_title));
  } else {
    $page_title = $sitename;
  }

  $description_block = _template_extract_meta_comment($source, 'og:description');
  if ($description_block === null) {
    $description_block = _template_extract_block($source, 'og_description');
  }
  if ($description_block !== null) {
    $description = trim(preg_replace('/\s+/u', ' ', $description_block));
  } else {
    $description = $site_meta['description'] ?? '';
  }

  $meta = [
    'title' => $page_title,
    'description' => $description,
    'author' => $site_meta['author'] ?? 'simple-twig-site',
    'canonical' => $page_url,
    'og' => [
      'type' => 'website',
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

  $image_url = _template_extract_meta_comment($source, 'og:image');
  if ($image_url === null) {
    $image_block = _template_extract_block($source, 'og_image');
    $image_url = $image_block !== null ? trim($image_block) : _template_extract_first_share_image($source);
  }
  if ($image_url !== null && $image_url !== '') {
    _meta_enrich_with_image($meta, $image_url, $page_title);
  }

  return $meta;
}

/**
 * Apply template-derived meta to static pages when content meta is not already set.
 * @param array $path_data
 * @param array $variables
 * @return void
 */
function _serve_apply_template_meta($path_data, &$variables) {
  if (!empty($variables['meta']['og']) || empty($path_data['template'])) {
    return;
  }

  $action = $path_data['menu_item']['action'] ?? null;
  if (in_array($action, ['edit', 'add', 'delete', 'save'], true)) {
    return;
  }

  if (!empty($path_data['menu_item']['content_type']) && empty($path_data['menu_item']['stub'])) {
    return;
  }

  $variables['meta'] = _template_build_page_meta($path_data['template'], $variables['url']);
}