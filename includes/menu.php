<?php

/**
 * This function builds the menu for a given path
 * @param string $path
 * @return array
 * [
 *   [
 *     'label' => 'Label',
 *     'path' => 'path',
 *     'classes' => 'active',
 *     'active' => true,
 *     'url' => '/path',
 *   ],
 * ]
 */
function build_menu($path_data = NULL) {
  $path = $path_data['path'];
  return array_map(function($item) use ($path_data) {
    return process_menu_item($item, $path_data);
  }, array_filter($GLOBALS['config']['menu'], function($item) use ($path) {
    // Filter hidden menu items
    if (!empty($item['hidden'])) {
      return false;
    }
    
    // Filter by hide_on_paths
    if (!empty($item['hide_on_paths']) && in_array($path, $item['hide_on_paths'])) {
      return false;
    }
    
    // Filter by show_for - only show if user has one of the required roles
    if (!empty($item['show_for']) && is_array($item['show_for'])) {
      $user = current_user();
      if (empty($user)) {
        return false; // User not authenticated
      }
      $user_roles = $user['roles'] ?? [];
      // Check if user has any of the required roles
      $has_required_role = false;
      foreach ($item['show_for'] as $required_role) {
        if (in_array($required_role, $user_roles)) {
          $has_required_role = true;
          break;
        }
      }
      if (!$has_required_role) {
        return false; // User doesn't have required role
      }
    }
    
    return true;
  }));
}

/**
 * This function processes a menu item
 * @param array $item
 * @param string $path
 * @return array
 * [
 *   'label' => 'Label',
 *   'path' => 'path',
 *   'classes' => 'active',
 *   'active' => true,
 *   'url' => '/path',
 * ]
 */
function process_menu_item($item, $path_data = NULL, $parent_menu_item = NULL) {
  if (substr($item['path'], 0, 1) !== '/' && !empty($parent_menu_item) && isset($parent_menu_item['path'])) {
    $item['path'] = $parent_menu_item['path'] . '/' . $item['path'];
  }
  $path = $path_data['path'];
  $original_path = $path_data['original_path'] ?? NULL;
  $item['classes'] = isset($item['classes']) ? (array)$item['classes'] : [];

  $item['active'] = $item['path'] === $path ||
    (isset($item['path_aliases']) && in_array($path, $item['path_aliases'])) ||
    (isset($item['view_path']) && $item['view_path'] === $path) ||
    ($original_path && $item['path'] === $original_path);

  if ($item['active']) $item['classes'][] = 'active';
  $item['url'] = preg_match('/^(https?:\/)?\//', $item['path']) ? $item['path'] : '/'.$item['path'];
  return $item;
}

/**
 * This function builds the secondary menu for a given path
 * @param string $path
 * @return array
 * [
 *   [
 *     'label' => 'Label',
 *     'path' => 'path',
 *     'classes' => 'active',
 *     'active' => true,
 *     'url' => '/path',
 *   ],
 * ]
 */
function build_secondary_menu($path_data = NULL) {
  $path = $path_data['path'];
  $parent_menu_item = array_filter($GLOBALS['config']['menu'], function($item) use ($path) {
    if (!isset($item['children'])) return false;
    if ($item['path'] === $path) return true;
    if (isset($item['path_aliases']) && in_array($path, $item['path_aliases'])) return true;
    if (isset($item['view_path']) && $item['view_path'] === $path) return true;
    return false;
  });
  if (empty($parent_menu_item)) {
    return [];
  }
  $parent_menu_item = array_shift($parent_menu_item);
  $children = $parent_menu_item['children'];

  if (is_array($children)) {
    $visible = array_filter($children, function($child) {
      return empty($child['hidden']);
    });
    return array_map(function($item) use ($path_data, $parent_menu_item) {
      return process_menu_item($item, $path_data, $parent_menu_item);
    }, $visible);
  }
  else if (is_string($children) && strpos($children, 'content:') === 0) {
    $content_type = substr($children, 8);
    return array_map(function($item) use ($path_data, $content_type, $parent_menu_item) {
      return process_menu_item([
        'label' => $item['title'] ?? 'N/A',
        'path' => $parent_menu_item['path'] . '/' . ($item['stub'] ?? 'na'),
      ], $path_data);
    }, list_content($content_type));
  }
  return [];
}