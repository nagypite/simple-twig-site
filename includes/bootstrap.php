<?php

// Start session early, before any output
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

define('TEMPLATE_PATH', BASE_PATH.'/templates');
define('PAGES_PATH', BASE_PATH.'/pages');
define('CACHE_PATH', BASE_PATH.'/cache');
define('CONTENT_PATH', BASE_PATH.'/content');
define('LOG_PATH', BASE_PATH.'/log');

include BASE_PATH.'/includes/serve.php';
include BASE_PATH.'/includes/menu.php';
include BASE_PATH.'/includes/content.php';
include BASE_PATH.'/includes/index.php';
include BASE_PATH.'/includes/utils.php';
include BASE_PATH.'/includes/auth.php';
include BASE_PATH.'/includes/api.php';
require_once BASE_PATH.'/vendor/autoload.php';

include BASE_PATH.'/config/base.php';

$loader = new \Twig\Loader\FilesystemLoader([TEMPLATE_PATH, PAGES_PATH]);
$twig = new \Twig\Environment($loader, [
  'cache' => CACHE_PATH,
  'debug' => $config['debug'],
  'charset' => 'utf-8',
  'auto_reload' => TRUE,
  'autoescape' => FALSE,
]);

// Register runtime loader for michelf/php-markdown
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\Extra\Markdown\MarkdownInterface;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

// Adapter class to make Michelf\MarkdownExtra compatible with Twig's MarkdownInterface
class MichelfMarkdownAdapter implements MarkdownInterface {
    private $markdown;
    private $config;
    private $cacheDir;
    private $configHash; // Cache config hash to avoid recalculating
    
    public function __construct($config = null) {
        $this->markdown = new \Michelf\MarkdownExtra();
        $this->config = $config ?? $GLOBALS['config']['markdown'] ?? [];
        $this->cacheDir = CACHE_PATH . '/markdown';
        // Cache config hash once in constructor
        $this->configHash = md5(json_encode($this->config));
        
        // Ensure cache directory exists
        if (!empty($this->config['cache_enabled']) && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function convert(string $body, string $filePath = null, string $contentType = 'content', bool $skipPostProcess = false): string {
        // If file path is provided and caching is enabled, check cache
        if (!empty($this->config['cache_enabled']) && !empty($filePath) && file_exists($filePath)) {
            $cached = $this->getCached($filePath, $contentType, $skipPostProcess);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Transform markdown to HTML
        $html = $this->markdown->transform($body);
        
        // Post-process HTML to add Bootstrap classes and image captions (unless skipped)
        if (!$skipPostProcess) {
            $html = $this->postProcessHtml($html);
        }
        
        // Save to cache if file path provided
        if (!empty($this->config['cache_enabled']) && !empty($filePath) && file_exists($filePath)) {
            $this->saveCache($filePath, $contentType, $html, $skipPostProcess);
        }
        
        return $html;
    }
    
    private function getCached(string $filePath, string $contentType, bool $skipPostProcess = false) {
        $fileMtime = filemtime($filePath);
        // Use cached config hash instead of recalculating
        // Include processing version to invalidate cache when processing logic changes
        $processingVersion = '1.1'; // Increment when processing logic changes
        // Include skipPostProcess in cache key to differentiate between raw and processed versions
        $cacheKey = md5($filePath . ':' . $contentType . ':' . $fileMtime . ':' . $this->configHash . ':' . $processingVersion . ':' . ($skipPostProcess ? 'raw' : 'processed'));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.html';
        
        if (file_exists($cacheFile) && filemtime($cacheFile) >= $fileMtime) {
            return file_get_contents($cacheFile);
        }
        
        return false;
    }
    
    private function saveCache(string $filePath, string $contentType, string $html, bool $skipPostProcess = false): void {
        $fileMtime = filemtime($filePath);
        // Use cached config hash instead of recalculating
        // Include processing version to invalidate cache when processing logic changes
        $processingVersion = '1.1'; // Increment when processing logic changes
        // Include skipPostProcess in cache key to differentiate between raw and processed versions
        $cacheKey = md5($filePath . ':' . $contentType . ':' . $fileMtime . ':' . $this->configHash . ':' . $processingVersion . ':' . ($skipPostProcess ? 'raw' : 'processed'));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.html';
        
        file_put_contents($cacheFile, $html);
    }
    
    private function postProcessHtml(string $html): string {
        if (empty($html)) {
            return $html;
        }
        
        // Create DOMDocument
        $dom = new \DOMDocument();
        $libxmlPreviousState = libxml_use_internal_errors(true);
        
        // Load HTML with UTF-8 encoding
        $html = '<?xml encoding="UTF-8">' . $html;
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlPreviousState);
        
        // Process images
        $images = $dom->getElementsByTagName('img');
        $imageClass = $this->config['image_class'] ?? 'img-fluid';
        $tableClass = $this->config['table_class'] ?? 'table';
        $tableContainerClass = $this->config['table_container_class'] ?? 'table-container';
        $figureClass = $this->config['figure_class'] ?? 'figure';
        $figureImgClass = $this->config['figure_img_class'] ?? 'figure-img img-fluid rounded';
        $figureCaptionClass = $this->config['figure_caption_class'] ?? 'figure-caption';
        
        // Collect images to process (we need to collect first because we'll modify the DOM)
        $imagesToProcess = [];
        foreach ($images as $img) {
            $imagesToProcess[] = $img;
        }
        
        foreach ($imagesToProcess as $img) {
            $altText = $img->getAttribute('alt');
            
            // Wrap in figure if alt text exists
            if (!empty($altText)) {
                $figure = $dom->createElement('figure');
                $figure->setAttribute('class', $figureClass);
                
                // Clone the image node and apply figure-specific classes
                $imgClone = $img->cloneNode(true);
                $existingImgClass = $imgClone->getAttribute('class');
                // Merge existing classes with figure image classes
                if (!empty($existingImgClass)) {
                    $existingClasses = explode(' ', trim($existingImgClass));
                    $figureImgClasses = explode(' ', trim($figureImgClass));
                    $mergedClasses = array_unique(array_merge($existingClasses, $figureImgClasses));
                    $figureImgClasses = implode(' ', $mergedClasses);
                } else {
                    $figureImgClasses = $figureImgClass;
                }
                $imgClone->setAttribute('class', $figureImgClasses);
                $figure->appendChild($imgClone);
                
                // Create figcaption with configurable class
                $figcaption = $dom->createElement('figcaption', htmlspecialchars($altText));
                $figcaption->setAttribute('class', $figureCaptionClass);
                $figure->appendChild($figcaption);
                
                // Replace original image with figure
                $img->parentNode->replaceChild($figure, $img);
            } else {
                // For images without alt text, just add the image class
                $existingClass = $img->getAttribute('class');
                if (strpos($existingClass, $imageClass) === false) {
                    $newClass = trim($existingClass . ' ' . $imageClass);
                    $img->setAttribute('class', $newClass);
                }
            }
        }
        
        // Process tables
        $tables = $dom->getElementsByTagName('table');
        // Collect tables to process (we need to collect first because we'll modify the DOM)
        $tablesToProcess = [];
        foreach ($tables as $table) {
            $tablesToProcess[] = $table;
        }
        
        foreach ($tablesToProcess as $table) {
            // Wrap table in container div
            $container = $dom->createElement('div');
            $container->setAttribute('class', $tableContainerClass);
            $parent = $table->parentNode;
            $parent->insertBefore($container, $table);
            $container->appendChild($table);
            
            $existingClass = $table->getAttribute('class');
            if (strpos($existingClass, $tableClass) === false) {
                $newClass = trim($existingClass . ' ' . $tableClass);
                $table->setAttribute('class', $newClass);
            }
            
            // Remove empty table headers
            // Helper function to check if a th element is empty
            $isThEmpty = function($th) {
                // Get text content and remove all whitespace including non-breaking spaces
                $text = $th->textContent;
                // Remove all whitespace characters including non-breaking spaces and zero-width spaces
                $text = preg_replace('/[\s\x{00A0}\x{2000}-\x{200B}\x{FEFF}]+/u', '', $text);
                return empty($text);
            };
            
            // Check for thead element
            $thead = $table->getElementsByTagName('thead')->item(0);
            if ($thead) {
                $headerRows = $thead->getElementsByTagName('tr');
                $rowsToRemove = [];
                foreach ($headerRows as $row) {
                    $thElements = $row->getElementsByTagName('th');
                    $allEmpty = true;
                    foreach ($thElements as $th) {
                        if (!$isThEmpty($th)) {
                            $allEmpty = false;
                            break;
                        }
                    }
                    if ($allEmpty && $thElements->length > 0) {
                        $rowsToRemove[] = $row;
                    }
                }
                foreach ($rowsToRemove as $row) {
                    $thead->removeChild($row);
                }
                // Remove thead if it's now empty
                if ($thead->getElementsByTagName('tr')->length === 0) {
                    $table->removeChild($thead);
                }
            }
            
            // Check first tr for th elements (if no thead exists)
            if (!$thead || $table->getElementsByTagName('thead')->length === 0) {
                $firstRow = $table->getElementsByTagName('tr')->item(0);
                if ($firstRow) {
                    $thElements = $firstRow->getElementsByTagName('th');
                    if ($thElements->length > 0) {
                        $allEmpty = true;
                        foreach ($thElements as $th) {
                            if (!$isThEmpty($th)) {
                                $allEmpty = false;
                                break;
                            }
                        }
                        if ($allEmpty) {
                            $table->removeChild($firstRow);
                        }
                    }
                }
            }
        }
        
        // Get the processed HTML
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $html = '';
            foreach ($body->childNodes as $node) {
                $html .= $dom->saveHTML($node);
            }
        } else {
            $html = $dom->saveHTML();
        }
        
        return $html;
    }
}

// Register runtime loader for MarkdownExtension
$twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
    public function load($class) {
        if (MarkdownRuntime::class === $class) {
            return new MarkdownRuntime(new MichelfMarkdownAdapter());
        }
    }
});

// Register markdown extension (needed for runtime loader)
$twig->addExtension(new \Twig\Extra\Markdown\MarkdownExtension());

// Custom markdown filter that can access file path (for backward compatibility)
// Note: Pre-processed content should use content_html, abstract_html fields instead
$twig->addFilter(new \Twig\TwigFilter('markdown_to_html', function ($content, $filePath = null) {
    // If file path not provided, try to get from global context (set by serve.php)
    if (empty($filePath) && isset($GLOBALS['twig_markdown_file_path'])) {
        $filePath = $GLOBALS['twig_markdown_file_path'];
    }
    
    // Use singleton adapter instance for better performance
    static $adapter = null;
    if ($adapter === null) {
        $adapter = new MichelfMarkdownAdapter($GLOBALS['config']['markdown'] ?? []);
    }
    return $adapter->convert($content ?? '', $filePath, 'content');
}, ['is_safe' => ['html']]));

// Raw markdown filter without post-processing (for use in editors)
$twig->addFilter(new \Twig\TwigFilter('markdown_to_html_raw', function ($content, $filePath = null) {
    // If file path not provided, try to get from global context (set by serve.php)
    if (empty($filePath) && isset($GLOBALS['twig_markdown_file_path'])) {
        $filePath = $GLOBALS['twig_markdown_file_path'];
    }
    
    // Use singleton adapter instance for better performance
    static $adapter = null;
    if ($adapter === null) {
        $adapter = new MichelfMarkdownAdapter($GLOBALS['config']['markdown'] ?? []);
    }
    return $adapter->convert($content ?? '', $filePath, 'content', true);
}, ['is_safe' => ['html']]));

// Template function to get post by stub
$twig->addFunction(new \Twig\TwigFunction('get_post_by_stub', function ($stub) {
    // Ensure content functions are available
    if (!function_exists('get_content_by_stub')) {
        require_once BASE_PATH.'/includes/content/core.php';
    }
    if (!function_exists('_content_process_markdown')) {
        require_once BASE_PATH.'/includes/content/processor.php';
    }
    
    $post = get_content_by_stub('post', $stub, true);
    
    if ($post && !empty($post['content'])) {
        // Process markdown to HTML if content exists
        $content_type_config = $GLOBALS['config']['content_types']['post'] ?? [];
        if (isset($content_type_config['preprocess'])) {
            $content_file = CONTENT_PATH.'/post/'.$post['path'];
            if (file_exists($content_file)) {
                _content_process_markdown($post, $content_file, $content_type_config['preprocess']);
            }
        }
    }
    
    return $post ? $post : null;
}));

// set up error reporting and logging to LOG_PATH
error_reporting(E_ALL);
ini_set('display_startup_errors', $config['debug'] ? 1 : 0);
ini_set('display_errors', $config['debug'] ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH.'/error.log');

// Set upload file size limits (5MB)
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '5M');

function log_debug() {
  if ($GLOBALS['config']['debug']) {
    file_put_contents(LOG_PATH.'/debug.log', date('Y-m-d H:i:s').' '.json_encode(func_get_args(), true)."\n", FILE_APPEND);
  }
}