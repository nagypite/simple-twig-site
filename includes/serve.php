<?php

function serve($path, $variables = [], $status = 200) {
  // Handle API routes first (before other routing)
  if (strpos($path, 'api/') === 0) {
    $api_path = substr($path, 4); // Remove 'api/' prefix
    
    if ($api_path === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      handle_api_upload();
      exit; // handle_api_upload() already sends response and exits
    }
    
    if ($api_path === 'list-images' && $_SERVER['REQUEST_METHOD'] === 'GET') {
      handle_api_list_images();
      exit; // handle_api_list_images() already sends response and exits
    }
    
    // Unknown API endpoint
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'API endpoint not found']);
    exit;
  }
  
  // Handle logout route
  if ($path === 'logout') {
    logout();
    header('Location: /');
    exit;
  }
  
  // Handle login route
  if ($path === 'login') {
    // Handle POST login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $login_result = handle_login($_POST);
      if ($login_result['success']) {
        // Redirect to return URL or home
        $return_url = get_return_url();
        header('Location: ' . $return_url);
        exit;
      } else {
        // Pass error to template
        $variables['login_error'] = $login_result['error'];
      }
    }
    
    // Create path_data for login page
    $path_data = [
      'path' => 'login',
      'template' => 'login.html',
      'menu_item' => [
        'path' => 'login',
        'generic' => true,
      ],
    ];
  } else {
    // Check for any files to serve (content files are resolved via content/files.php)
    $file = content_resolve_file($path);
    if ($file) {
      return serve_file($file, $variables, 200);
    }

    # replace non-allowed characters
    $path = preg_replace('/[^a-zA-Z0-9\/_\-]+/', '-', $path);

    $path_data = resolve_path($path);
  }

  $variables = array_merge([
    'path' => $path,
    'path_data' => $path_data,
    'sitename' => $GLOBALS['config']['sitename'],
    'meta' => $GLOBALS['config']['meta'],
    'url' => $GLOBALS['config']['siteurl'].'/'.$path,
    'query' => $_SERVER['QUERY_STRING'],
    'request' => $_REQUEST,
    'method' => $_SERVER['REQUEST_METHOD'],
  ], $variables);

  // Check authentication for protected routes
  if (!empty($path_data['menu_item'])) {
    $menu_item = $path_data['menu_item'];
    
    // Check show_for restriction - applies to ALL access (menu visibility + direct URL)
    $show_for_roles = null;
    
    // First check parent menu item from config (for content actions and base paths)
    if (!empty($menu_item['content_type'])) {
      foreach ($GLOBALS['config']['menu'] as $config_item) {
        if (isset($config_item['content_type']) && $config_item['content_type'] === $menu_item['content_type']) {
          if (!empty($config_item['show_for']) && is_array($config_item['show_for'])) {
            $show_for_roles = $config_item['show_for'];
          }
          break;
        }
      }
    }
    
    // Also check the original config menu item by path (for direct paths like 'belso')
    if (empty($show_for_roles)) {
      $menu_path = $menu_item['path'] ?? $path_data['path'];
      foreach ($GLOBALS['config']['menu'] as $config_item) {
        // Check exact path match
        if (isset($config_item['path']) && $config_item['path'] === $menu_path) {
          if (!empty($config_item['show_for']) && is_array($config_item['show_for'])) {
            $show_for_roles = $config_item['show_for'];
            break;
          }
        }
        // Check path aliases
        if (isset($config_item['path_aliases']) && in_array($menu_path, $config_item['path_aliases'])) {
          if (!empty($config_item['show_for']) && is_array($config_item['show_for'])) {
            $show_for_roles = $config_item['show_for'];
            break;
          }
        }
      }
    }
    
    // Fallback: check the resolved menu item itself
    if (empty($show_for_roles) && !empty($menu_item['show_for']) && is_array($menu_item['show_for'])) {
      $show_for_roles = $menu_item['show_for'];
    }
    
    // If show_for is set, enforce access control
    if (!empty($show_for_roles)) {
      $user = current_user();
      $is_authenticated = !empty($user);
      $user_roles = $user['roles'] ?? [];
      
      $has_access = false;
      
      foreach ($show_for_roles as $required_condition) {
        // Handle special token: _guest (allow only when NOT authenticated)
        if ($required_condition === '_guest') {
          if (!$is_authenticated) {
            $has_access = true;
            break;
          }
        }
        // Handle special token: _user (allow only when authenticated)
        elseif ($required_condition === '_user') {
          if ($is_authenticated) {
            $has_access = true;
            break;
          }
        }
        // Handle regular role: allow if user has this role
        elseif ($is_authenticated && in_array($required_condition, $user_roles)) {
          $has_access = true;
          break;
        }
      }
      
      if (!$has_access) {
        if (!$is_authenticated) {
          // User is not authenticated - check if any condition requires authentication
          // (i.e., _user or regular roles, not _guest)
          $requires_auth = false;
          foreach ($show_for_roles as $required_condition) {
            if ($required_condition !== '_guest') {
              $requires_auth = true;
              break;
            }
          }
          if ($requires_auth) {
            // Redirect to login for _user or regular roles
            require_auth($show_for_roles);
          } else {
            // Only _guest was required - this shouldn't happen (has_access should be true)
            // but handle edge case anyway
            http_response_code(403);
            die('Access denied.');
          }
        } else {
          // User is authenticated but doesn't meet requirements
          // This happens when: _guest is required (user shouldn't be authenticated)
          // or user doesn't have required role
          http_response_code(403);
          die('Access denied. You do not have permission to access this page.');
        }
      }
    }
    
    // Check if menu item requires authentication
    // Also check parent menu item if this is a content action (edit/add/save)
    $requires_auth = false;
    $required_roles = [];
    
    // Check parent menu item from config (for content actions)
    if (!empty($menu_item['content_type'])) {
      foreach ($GLOBALS['config']['menu'] as $config_item) {
        if (isset($config_item['content_type']) && $config_item['content_type'] === $menu_item['content_type']) {
          if (!empty($config_item['auth_required'])) {
            $requires_auth = true;
            $required_roles = $config_item['roles'] ?? [];
          }
          break;
        }
      }
    }
    
    // Check current menu item for auth_required
    if (!empty($menu_item['auth_required'])) {
      $requires_auth = true;
      $required_roles = $menu_item['roles'] ?? $required_roles;
    }
    
    // Protect only edit/add/save/delete actions when parent menu item requires auth
    // Listing and viewing pages remain public
    if ($requires_auth && !empty($menu_item['action']) && in_array($menu_item['action'], ['edit', 'add', 'save', 'delete'])) {
      require_auth($required_roles);
    }
  }

  // Handle POST requests for save action before template rendering
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($path_data['menu_item']['action']) && $path_data['menu_item']['action'] === 'save') {
    $result = content_handle_save($path_data, $variables);
    if ($result !== false) {
      // Redirect on success - get the original menu item path from config
      $menu_item = $path_data['menu_item'];
      $content_type = $menu_item['content_type'] ?? null;
      
      // Find the original menu item from config to get the base path
      $base_path = null;
      foreach ($GLOBALS['config']['menu'] as $config_item) {
        if (isset($config_item['content_type']) && $config_item['content_type'] === $content_type) {
          $base_path = $config_item['path'];
          // Prefer path alias if available (usually the plural form)
          if (isset($config_item['path_aliases']) && !empty($config_item['path_aliases'])) {
            $base_path = $config_item['path_aliases'][0];
          }
          break;
        }
      }
      
      if ($base_path) {
        // Check for custom redirect parameter (from query string or POST)
        $custom_redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? null;
        
        if (!empty($custom_redirect)) {
          // Use custom redirect path if provided
          $redirect_url = '/'.$custom_redirect;
        } else {
          // Use handler to get redirect URL (allows content types to customize redirect behavior)
          $handler = _get_content_handler($content_type);
          $redirect_url = $handler->getRedirectUrlAfterSave($result, $base_path);
        }
        
        header('Location: ' . $redirect_url);
        exit;
      }
    }
    // On error, redirect back to edit form with error message
    if (isset($variables['error'])) {
      $menu_item = $path_data['menu_item'];
      $content_type = $menu_item['content_type'] ?? null;
      
      // Find the original menu item from config
      $base_path = null;
      foreach ($GLOBALS['config']['menu'] as $config_item) {
        if (isset($config_item['content_type']) && $config_item['content_type'] === $content_type) {
          $base_path = $config_item['path'];
          break;
        }
      }
      
      if ($base_path) {
        $redirect_url = '/'.$base_path;
        if (!empty($_POST['content_id'])) {
          $redirect_url .= '/edit/'.$_POST['content_id'];
        } else {
          $redirect_url .= '/add';
        }
        // Pass error via query string
        $redirect_url .= '?error='.urlencode($variables['error']);
        header('Location: ' . $redirect_url);
        exit;
      }
    }
  }

  // Handle POST requests for delete action before template rendering
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($path_data['menu_item']['action']) && $path_data['menu_item']['action'] === 'delete') {
    $result = content_handle_delete($path_data, $variables);
    if ($result === true) {
      // Redirect on success - get the original menu item path from config
      $menu_item = $path_data['menu_item'];
      $content_type = $menu_item['content_type'] ?? null;
      
      // Find the original menu item from config to get the base path
      $base_path = null;
      foreach ($GLOBALS['config']['menu'] as $config_item) {
        if (isset($config_item['content_type']) && $config_item['content_type'] === $content_type) {
          // Prefer path alias if available (usually the plural form)
          if (isset($config_item['path_aliases']) && !empty($config_item['path_aliases'])) {
            $base_path = $config_item['path_aliases'][0];
          } else {
            $base_path = $config_item['path'];
          }
          break;
        }
      }
      
      if ($base_path) {
        $redirect_url = '/'.$base_path;
        header('Location: ' . $redirect_url);
        exit;
      }
    }
    // On error, redirect back to delete confirmation page with error message
    if (isset($variables['error'])) {
      $menu_item = $path_data['menu_item'];
      $content_type = $menu_item['content_type'] ?? null;
      $content_id = $menu_item['content_id'] ?? null;
      
      // Find the original menu item from config
      $base_path = null;
      foreach ($GLOBALS['config']['menu'] as $config_item) {
        if (isset($config_item['content_type']) && $config_item['content_type'] === $content_type) {
          $base_path = $config_item['path'];
          break;
        }
      }
      
      if ($base_path && $content_id) {
        $redirect_url = '/'.$base_path.'/delete/'.$content_id;
        // Pass error via query string
        $redirect_url .= '?error='.urlencode($variables['error']);
        header('Location: ' . $redirect_url);
        exit;
      }
    }
  }

  // handle content type paths
  if (!empty($path_data['menu_item']['content_type'])) {
    content_serve_preprocess($path_data, $variables);
  }

  // handle custom preprocess functions from menu items
  if (!empty($path_data['menu_item']['preprocess']) && empty($path_data['menu_item']['content_type'])) {
    $preprocess_func = $path_data['menu_item']['preprocess'] . '_serve_preprocess';
    if (function_exists($preprocess_func)) {
      $preprocess_func($path_data, $variables);
    } else {
      log_debug('serve', 'preprocess function not found', $preprocess_func);
    }
  }

  if (isset($path_data['error'])) {
    log_debug('serve', 'error', $path_data['error'], $path);
    if ($path == $GLOBALS['config']['error_path']) {
      die('Cannot serve error_path.');
    }

    $redirect_path = $path_data['menu_item']['error_path'] ?? $GLOBALS['config']['error_path'];
    
    // Prevent infinite loop: if redirect path is the same as current path, use global error_path
    if ($redirect_path === $path) {
      $redirect_path = $GLOBALS['config']['error_path'];
      log_debug('serve', 'Prevented redirect loop', $path, 'using global error_path', $redirect_path);
    }
    
    // Double-check: if redirect path is still the same as current path, die to prevent infinite loop
    if ($redirect_path === $path) {
      die('Infinite redirect loop detected. Cannot redirect to same path: ' . $path);
    }
    
    log_debug('serve', $path_data['error'], $path, 'redirecting to', $redirect_path);
    if (empty($variables['message'])) {
      $variables['message'] = $GLOBALS['config']['lang']['page_not_found'];
    }
    return serve($redirect_path, $variables, 404);
  }

  log_debug('serve', $path, $path_data['template']);

  $variables['menu'] = build_menu($path_data);
  $variables['secondary_menu'] = build_secondary_menu($path_data);
  
  // Add user data to template variables
  $variables['user'] = current_user();
  $variables['is_authenticated'] = is_authenticated();
  
  // Add permission variables for templates
  $variables['is_admin'] = has_role('admin');
  $variables['can_edit_article'] = has_any_role(['admin', 'article']);
  $variables['can_edit_event'] = has_any_role(['admin', 'event']);
  $variables['can_edit_gallery'] = has_any_role(['admin', 'gallery']);
  $variables['can_edit_newsletter'] = has_any_role(['admin', 'newsletter']);
  $variables['can_edit_post'] = has_any_role(['admin']);
  
  // Add CKEditor config to template variables
  $variables['ckeditor'] = $GLOBALS['config']['ckeditor'] ?? [];
  
  // Set file path in global context for markdown filter if content has _file_path
  if (isset($variables['content']['_file_path'])) {
    $GLOBALS['twig_markdown_file_path'] = $variables['content']['_file_path'];
  } else {
    unset($GLOBALS['twig_markdown_file_path']);
  }

  if ($status !== 200) {
    http_response_code($status);
  }

  if (isset($path_data['template'])) {
    echo $GLOBALS['twig']->render($path_data['template'], $variables);
    // Clear the global file path after rendering
    unset($GLOBALS['twig_markdown_file_path']);
    return true;
  }

  die('Nothing to serve.');
}

function check_path($path) {
  $path_data = resolve_path($path);
  if (empty($path_data) || isset($path_data['error'])) {
    return false;
  }
  return true;
}

/**
 * Resolve aliases and content paths to actual paths
 * @param string $path
 * @return string
 */
function resolve_menu_item($path) {
  // resolve exact matches and aliases
  foreach ($GLOBALS['config']['menu'] as $item) {
    if ($item['path'] === $path) {
      return $item;
    }
    if (isset($item['path_aliases']) && in_array($path, $item['path_aliases'])) {
      return $item;
    }
  }

  $slash_pos = strpos($path, '/');
  if ($slash_pos !== false) {
    $parent_path = substr($path, 0, $slash_pos);
    $child_subpath = substr($path, $slash_pos + 1);

    // look for static parent menu item
    foreach ($GLOBALS['config']['menu'] as $item) {
      if (isset($item['children']) && is_array($item['children'])) {
        foreach ($item['children'] as $child) {
          if ($child['path'] === $child_subpath) {
            return $item;
          }
        }
      }
    }

    // Check for edit/add/save/view patterns with content type
    $parent_menu_item = NULL;
    foreach ($GLOBALS['config']['menu'] as $item) {
      if (!isset($item['content_type'])) continue;
      if ($item['path'] !== $parent_path && !in_array($parent_path, $item['path_aliases'])) continue;
      $parent_menu_item = $item;
      break;
    }

    if ($parent_menu_item) {
      $content_type = $parent_menu_item['content_type'];
      
      // Check for explicit view pattern: {content_type}/view/{id}-{stub}
      if (preg_match('/^view\/(\d+)-(.+)$/', $child_subpath, $matches)) {
        $content_id = $matches[1];
        return array_merge($parent_menu_item, [
          'stub' => $matches[1].'-'.$matches[2],
          'content_id' => $content_id,
          'action' => 'view',
          'path' => $parent_menu_item['view_path'] ?? $parent_menu_item['path'].'/view',
        ]);
      }
      
      // Check for edit pattern: {content_type}/edit/{id} or {content_type}/edit/{id}-{stub}
      if (preg_match('/^edit\/(\d+)(?:-(.+))?$/', $child_subpath, $matches)) {
        $content_id = $matches[1];
        return array_merge($parent_menu_item, [
          'content_id' => $content_id,
          'action' => 'edit',
          'path' => $parent_menu_item['path'].'/edit',
        ]);
      }
      
      // Check for delete pattern: {content_type}/delete/{id} or {content_type}/delete/{id}-{stub}
      if (preg_match('/^delete\/(\d+)(?:-(.+))?$/', $child_subpath, $matches)) {
        $content_id = $matches[1];
        return array_merge($parent_menu_item, [
          'content_id' => $content_id,
          'action' => 'delete',
          'path' => $parent_menu_item['path'].'/delete',
        ]);
      }
      
      // Check for add pattern: {content_type}/add
      if ($child_subpath === 'add') {
        return array_merge($parent_menu_item, [
          'action' => 'add',
          'path' => $parent_menu_item['path'].'/edit',
        ]);
      }
      
      // Check for save pattern: {content_type}/save
      if ($child_subpath === 'save') {
        return array_merge($parent_menu_item, [
          'action' => 'save',
          'path' => $parent_menu_item['path'].'/save',
        ]);
      }
      
      // Default: look for content by stub (existing behavior for {id}-{stub})
      // This handles the shorthand view pattern
      return array_merge($parent_menu_item, [
        'stub' => $child_subpath,
        'path' => $parent_menu_item['view_path'] ?? $parent_menu_item['path'].'/view',
      ]);
    }
  }

  // return generic menu item
  return [
    'path' => $path,
    'generic' => true,
  ];
}

function resolve_path($path) {
  $path_data = [
    'path' => $path,
    'template' => NULL,
  ];

  // First look for the menu item
  $menu_item = resolve_menu_item($path);

  if (empty($menu_item) || empty($menu_item['path'])) {
    return ['error' => 'menu_item_not_found'];
  }

  $resolved_path = $menu_item['path'];

  $path_data['menu_item'] = $menu_item;

  // Skip template requirement for save action (handled by POST processing)
  if (!empty($menu_item['action']) && $menu_item['action'] === 'save') {
    return $path_data;
  }

  if ($path_data['path'] === $resolved_path) {
    $path_data['template'] = _find_template_file($path_data['path']);
  }
  else {
    $path_data['original_path'] = $path_data['path'];
    $path_data['path'] = $resolved_path;

    if (empty($menu_item['content_type'])) {
      // look for static template file in original path
      $path_data['template'] = _find_template_file($path_data['original_path']);
    }
    else {
      // look for dynamic template file in resolved path
      $path_data['template'] = _find_template_file($path_data['path']);
    }
  }

  if (empty($path_data['template'])) {
    $path_data['error'] = 'file_not_found';
  }

  return $path_data;
}

function _find_template_file($path) {
  $path_dir = PAGES_PATH.'/'.$path;
  if (file_exists($path_dir.'.html')) {
    return $path.'.html';
  }
  else if (file_exists($path_dir.'/content.html')) {
    return $path.'/content.html';
  }
  return false;
}

/**
 * Serves a file securely with proper HTTP headers for performance and content type.
 *
 * @param string $filePath Absolute path to the file on the server.
 * @param string $fileName The file name (optional, used for Content-Disposition).
 * @return void
 */
function serve_file($filePath) {
    // Check for absolute path
    if (substr($filePath, 0, 1) !== '/') {
      $filePath = BASE_PATH.'/'.$filePath;
    }

    // --- 1. Basic File Checks ---
    if (!file_exists($filePath) || !is_readable($filePath)) {
      log_debug('serve_file', 'file not found', $filePath);
      header('HTTP/1.0 404 Not Found');
      exit;
    }

    $fileSize = filesize($filePath);
    $fileMime = mime_content_type($filePath); // Requires fileinfo extension
    if ($fileMime === false) {
        $fileMime = 'application/octet-stream'; // Fallback
    }

    // --- 2. Caching & Conditional Request Handling ---
    $fileModifiedTime = filemtime($filePath);
    $etag = md5($filePath . $fileModifiedTime . $fileSize);

    // Set standard caching headers
    header('Cache-Control: public, max-age='.($GLOBALS['config']['cache_max_age'] ?? 86400)); // Cache defaults to 1 day
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileModifiedTime) . ' GMT');
    header('Etag: "' . $etag . '"');

    // Check for conditional requests (If-None-Match or If-Modified-Since)
    // This is the performance gain: sending a 304 Not Modified response
    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '', '"');

    if (
        ($ifNoneMatch === $etag) ||
        ($ifModifiedSince && strtotime($ifModifiedSince) >= $fileModifiedTime)
    ) {
        // File hasn't changed. Tell the browser to use its cached copy.
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    // --- 3. Content Headers ---
    header('Content-Type: ' . $fileMime);
    header('Content-Length: ' . $fileSize);

    // Optional: Force download if needed (usually not for images)
    // if ($fileName) {
    //     header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    // }

    // Clear output buffer to ensure headers are sent immediately
    if (ob_get_level()) {
        ob_clean();
    }

    // --- 4. Serve the File ---
    readfile($filePath);
    exit;
}