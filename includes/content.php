<?php

/**
 * Content handling module
 * 
 * This file serves as the main entry point for all content-related functionality.
 * It includes all content modules in the correct order.
 */

// Core content operations (list, get, cache)
require_once __DIR__ . '/content/core.php';

// YAML frontmatter operations
require_once __DIR__ . '/content/yaml.php';

// Utility functions
require_once __DIR__ . '/content/utils.php';

// File operations
require_once __DIR__ . '/content/files.php';

// Content filtering
require_once __DIR__ . '/content/filter.php';

// Content processing pipeline
require_once __DIR__ . '/content/processor.php';

// Content handlers
require_once __DIR__ . '/content/handlers.php';

// Content save operations
require_once __DIR__ . '/content/save.php';

// Content serve operations (preprocessing for pages)
require_once __DIR__ . '/content/serve.php';
