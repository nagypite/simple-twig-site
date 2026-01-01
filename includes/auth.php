<?php

/**
 * Load users from config/users.php
 * @return array
 */
function load_users() {
  static $users = null;
  if ($users === null) {
    $users_file = BASE_PATH.'/config/users.php';
    if (file_exists($users_file)) {
      $users = include $users_file;
    } else {
      $users = [];
    }
  }
  return $users;
}

/**
 * Get user by email
 * @param string $email
 * @return array|null
 */
function get_user($email) {
  $users = load_users();
  return $users[$email] ?? null;
}

/**
 * Check if user is authenticated
 * @return bool
 */
function is_authenticated() {
  return isset($_SESSION['user_email']) && !empty($_SESSION['user_email']);
}

/**
 * Get current authenticated user data
 * @return array|null
 */
function current_user() {
  if (!is_authenticated()) {
    return null;
  }
  
  $email = $_SESSION['user_email'];
  $user = get_user($email);
  
  if ($user) {
    return array_merge($user, ['email' => $email]);
  }
  
  return null;
}

/**
 * Check if current user has a specific role
 * @param string $role
 * @return bool
 */
function has_role($role) {
  $user = current_user();
  if (!$user) {
    return false;
  }
  
  $user_roles = $user['roles'] ?? [];
  return in_array($role, $user_roles);
}

/**
 * Check if current user has any of the required roles
 * @param array $required_roles
 * @return bool
 */
function has_any_role($required_roles) {
  if (empty($required_roles)) {
    return true; // No roles required
  }
  
  $user = current_user();
  if (!$user) {
    return false;
  }
  
  $user_roles = $user['roles'] ?? [];
  
  // Check if user has any of the required roles (OR logic)
  foreach ($required_roles as $role) {
    if (in_array($role, $user_roles)) {
      return true;
    }
  }
  
  return false;
}

/**
 * Verify password against hash
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verify_password($password, $hash) {
  return password_verify($password, $hash);
}

/**
 * Authenticate user and set session
 * @param string $email
 * @param string $password
 * @return array|false Returns user data on success, false on failure
 */
function login($email, $password) {
  $user = get_user($email);
  
  if (!$user) {
    return false;
  }
  
  $password_hash = $user['password_hash'] ?? '';
  if (empty($password_hash) || !verify_password($password, $password_hash)) {
    return false;
  }
  
  // Set session data
  $_SESSION['user_email'] = $email;
  
  // Regenerate session ID to prevent session fixation
  session_regenerate_id(true);
  
  return array_merge($user, ['email' => $email]);
}

/**
 * Logout current user
 * @return void
 */
function logout() {
  // Clear all session data
  $_SESSION = [];
  
  // Destroy session cookie
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      $params["secure"], $params["httponly"]
    );
  }
  
  // Destroy session
  session_destroy();
}

/**
 * Require authentication and check roles
 * Redirects to login if not authenticated or missing required roles
 * @param array $required_roles Array of roles (user needs ANY of these)
 * @param string $return_url URL to redirect to after login
 * @return bool Returns true if authenticated and authorized, false otherwise
 */
function require_auth($required_roles = [], $return_url = null) {
  // If already authenticated and has required roles, allow access
  if (is_authenticated() && has_any_role($required_roles)) {
    return true;
  }
  
  // Store return URL for redirect after login
  if ($return_url === null) {
    $return_url = $_SERVER['REQUEST_URI'] ?? '/';
  }
  
  $_SESSION['return_url'] = $return_url;
  
  // Redirect to login
  header('Location: /login');
  exit;
}

/**
 * Handle login form submission
 * @param array $post_data
 * @return array Returns ['success' => bool, 'error' => string|null, 'user' => array|null]
 */
function handle_login($post_data) {
  $username = trim($post_data['username'] ?? '');
  $password = $post_data['password'] ?? '';
  
  if (empty($username) || empty($password)) {
    return [
      'success' => false,
      'error' => 'Felhasználónév és jelszó megadása kötelező.',
      'user' => null,
    ];
  }
  
  $user = login($username, $password);
  
  if (!$user) {
    return [
      'success' => false,
      'error' => 'Helytelen felhasználónév vagy jelszó.',
      'user' => null,
    ];
  }
  
  return [
    'success' => true,
    'error' => null,
    'user' => $user,
  ];
}

/**
 * Get return URL from session and clear it
 * @return string
 */
function get_return_url() {
  $return_url = $_SESSION['return_url'] ?? '/';
  unset($_SESSION['return_url']);
  return $return_url;
}

