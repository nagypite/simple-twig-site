<?php

require 'vendor/autoload.php';

use ScssPhp\ScssPhp\Compiler;

$scss = new Compiler();
$scss->setImportPaths(['./scss/', './vendor/twbs/bootstrap/scss/']);

// Configure output formatting for production (compressed) or development (expanded)
// $scss->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);
$scss->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::EXPANDED);

$sourcePath = 'style.scss'; // Path to your main SCSS file
$destinationPath = 'public/assets/css/style.css';  // Desired output path

try {
    $compiledCss = $scss->compile('@import "' . $sourcePath . '"');
    file_put_contents($destinationPath, $compiledCss);
    echo "SCSS compiled successfully to " . $destinationPath . "\n";
} catch (\Exception $e) {
    echo "SCSS compilation failed: " . $e->getMessage() . "\n";
    exit(1); // Exit with an error code
}
