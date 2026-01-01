<?php

/**
 * Get required roles for a content type
 * @param string $content_type
 * @return array
 */
function get_content_type_roles($content_type) {
  $content_type_config = $GLOBALS['config']['content_types'][$content_type] ?? null;
  
  if ($content_type_config && isset($content_type_config['roles'])) {
    return $content_type_config['roles'];
  }
  
  return ['admin'];
}

/**
 * Handle API upload request
 * @return void (sends JSON response and exits)
 */
function handle_api_upload() {
  // Check authentication
  if (!is_authenticated()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
  }
  
  // Check if file was uploaded
  if (!isset($_FILES['filepond'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
      'success' => false, 
      'error' => 'No file uploaded',
      'details' => [
        'files_received' => isset($_FILES) ? array_keys($_FILES) : [],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'not set',
      ]
    ]);
    exit;
  }
  
  $file = $_FILES['filepond'];
  
  // Check upload error code and provide detailed information
  if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
      UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
      UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
      UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
      UPLOAD_ERR_NO_FILE => 'No file was uploaded',
      UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
      UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
      UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
    ];
    
    $error_code = $file['error'];
    $error_message = $error_messages[$error_code] ?? 'Unknown upload error (code: ' . $error_code . ')';
    
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
      'success' => false, 
      'error' => 'Upload error: ' . $error_message,
      'details' => [
        'error_code' => $error_code,
        'file_name' => $file['name'] ?? 'not set',
        'file_size' => $file['size'] ?? 0,
        'file_type' => $file['type'] ?? 'not set',
        'tmp_name' => $file['tmp_name'] ?? 'not set',
        'php_upload_max_filesize' => ini_get('upload_max_filesize'),
        'php_post_max_size' => ini_get('post_max_size'),
        'php_max_file_uploads' => ini_get('max_file_uploads'),
        'php_memory_limit' => ini_get('memory_limit'),
      ]
    ]);
    exit;
  }
  
  // Get parameters
  $content_type = $_POST['content_type'] ?? '';
  $content_id = $_POST['content_id'] ?? '';
  $target_attribute = $_POST['target_attribute'] ?? 'image';
  
  // Validate content_type
  if (empty($content_type) || !isset($GLOBALS['config']['content_types'][$content_type])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid content_type']);
    exit;
  }
  
  // Check if user has permission for this content type
  $required_roles = get_content_type_roles($content_type);
  if (!has_any_role($required_roles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Forbidden: Insufficient permissions']);
    exit;
  }
  
  // Validate file type (images only for now)
  $file = $_FILES['filepond'];
  $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
  
  // Suppress warnings from mime_content_type if file doesn't exist
  $old_error_reporting = error_reporting(0);
  $file_type = @mime_content_type($file['tmp_name']);
  error_reporting($old_error_reporting);
  
  if ($file_type === false || !in_array($file_type, $allowed_types)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images are allowed.']);
    exit;
  }
  
  // Validate file size (5MB limit)
  $max_file_size = 5 * 1024 * 1024; // 5MB in bytes
  if ($file['size'] > $max_file_size) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'File size exceeds the maximum limit of 5MB.']);
    exit;
  }
  
  // Determine upload directory
  $directory_id = empty($content_id) ? '_new' : $content_id;
  $upload_dir = CONTENT_PATH . '/' . $content_type . '/images/' . $directory_id . '/';
  
  // Create directory if it doesn't exist (suppress warnings to handle errors properly)
  if (!is_dir($upload_dir)) {
    $old_error_reporting = error_reporting(0);
    $mkdir_result = @mkdir($upload_dir, 0755, true);
    error_reporting($old_error_reporting);
    
    if (!$mkdir_result) {
      // Check if directory was created by another process
      if (!is_dir($upload_dir)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory. Please check permissions.']);
        exit;
      }
    }
  }
  
  // Check if directory is writable
  if (!is_writable($upload_dir)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Upload directory is not writable. Please check permissions.']);
    exit;
  }
  
  // Generate safe filename
  $original_name = $file['name'];
  $extension = pathinfo($original_name, PATHINFO_EXTENSION);
  $base_name = pathinfo($original_name, PATHINFO_FILENAME);
  // Sanitize filename
  $base_name = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $base_name);
  $base_name = preg_replace('/-+/', '-', $base_name);
  $filename = $base_name . '.' . strtolower($extension);
  
  // Ensure unique filename
  $counter = 1;
  $original_filename = $filename;
  while (file_exists($upload_dir . $filename)) {
    $filename = $base_name . '-' . $counter . '.' . strtolower($extension);
    $counter++;
  }
  
  // Move uploaded file
  $target_path = $upload_dir . $filename;
  $old_error_reporting = error_reporting(0);
  $move_result = @move_uploaded_file($file['tmp_name'], $target_path);
  error_reporting($old_error_reporting);
  
  if (!$move_result) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to save file. Please check permissions.']);
    exit;
  }
  
  // Generate URL
  $url = '/content/' . $content_type . '/images/' . $directory_id . '/' . $filename;
  
  // Return success response
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'filename' => $filename,
    'url' => $url,
    'message' => 'File uploaded successfully'
  ]);
  exit;
}

/**
 * Handle API list images request
 * @return void (sends JSON response and exits)
 */
function handle_api_list_images() {
  // Check authentication
  if (!is_authenticated()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
  }
  
  // Get parameters
  $content_type = $_GET['content_type'] ?? '';
  $content_id = $_GET['content_id'] ?? '';
  $target_attribute = $_GET['target_attribute'] ?? 'image';
  
  // Validate content_type
  if (empty($content_type) || !isset($GLOBALS['config']['content_types'][$content_type])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid content_type']);
    exit;
  }
  
  // Check if user has permission for this content type
  $required_roles = get_content_type_roles($content_type);
  if (!has_any_role($required_roles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Forbidden: Insufficient permissions']);
    exit;
  }
  
  // Determine directory to scan
  $directory_id = empty($content_id) ? '_new' : $content_id;
  $images_dir = CONTENT_PATH . '/' . $content_type . '/images/' . $directory_id . '/';
  
  // Check if directory exists
  if (!is_dir($images_dir)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'images' => []]);
    exit;
  }
  
  // Scan directory for image files
  $images = [];
  $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  
  $old_error_reporting = error_reporting(0);
  $files = @scandir($images_dir);
  error_reporting($old_error_reporting);
  
  if ($files === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'images' => []]);
    exit;
  }
  
  foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
      continue;
    }
    
    $file_path = $images_dir . $file;
    if (!is_file($file_path)) {
      continue;
    }
    
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions)) {
      continue;
    }
    
    $url = '/content/' . $content_type . '/images/' . $directory_id . '/' . $file;
    
    $images[] = [
      'filename' => $file,
      'url' => $url,
      'thumbnail_url' => $url // For now, same as url
    ];
  }
  
  // Sort by filename
  usort($images, function($a, $b) {
    return strcmp($a['filename'], $b['filename']);
  });
  
  // Return success response
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'images' => $images
  ]);
  exit;
}

