<?php

/**
 * Simple YAML frontmatter parser
 * @param string $yaml
 * @return array
 */
function _parse_yaml_frontmatter($yaml) {
  $meta = [];
  $lines = explode("\n", trim($yaml));
  
  foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;
    
    if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
      $key = trim($matches[1]);
      $value = empty($matches[2]) ? '' : trim($matches[2]);
      
      // Remove quotes if present
      if (!empty($value) && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
        $value = substr($value, 1, -1);
      }

      if ($key === 'keywords') {
        $value = explode(',', $value);
        $value = array_map('trim', $value);
      }
      
      $meta[$key] = $value;
    }
  }
  
  return $meta;
}

/**
 * Generate YAML frontmatter from meta array
 * @param array $meta
 * @return string
 */
function _generate_yaml_frontmatter($meta) {
  $lines = [];
  foreach ($meta as $key => $value) {
    if ($value === null || $value === '') {
      continue;
    }
    
    // Handle array values (keywords)
    if ($key === 'keywords' && is_array($value)) {
      $value = implode(', ', $value);
    }
    
    // Convert to string
    $value_str = (string)$value;
    
    // Quote values if they contain special characters
    if (preg_match('/[:|&*!%#@\[\]{}<>,\n]/', $value_str) || $value_str !== trim($value_str)) {
      $value_str = '"' . addslashes($value_str) . '"';
    }
    
    $lines[] = $key . ': ' . $value_str;
  }
  
  return implode("\n", $lines);
}

/**
 * Generate markdown file content from frontmatter and body
 * @param string $frontmatter
 * @param string $content
 * @return string
 */
function _generate_markdown_file($frontmatter, $content) {
  return "---\n" . $frontmatter . "\n---\n\n" . $content;
}
