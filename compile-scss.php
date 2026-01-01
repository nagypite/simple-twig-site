<?php

require 'vendor/autoload.php';

use ScssPhp\ScssPhp\Compiler;

$verbose = false;

// Function to compile SCSS with specific settings
function compileScss($sourceFile, $outputFile, $outputStyle, $importPaths) {
    $scss = new Compiler();
    $scss->setImportPaths($importPaths);
    $scss->setOutputStyle($outputStyle);
    
    try {
        $compiledCss = $scss->compile('@import "' . $sourceFile . '"');
        file_put_contents($outputFile, $compiledCss);
        echo "SCSS compiled successfully to " . $outputFile . "\n";
        return true;
    } catch (\Exception $e) {
        echo "SCSS compilation failed for " . $sourceFile . ": " . $e->getMessage() . "\n";
        return false;
    }
}

// Define paths
$scssDir = './scss/';
$bootstrapScssDir = './vendor/twbs/bootstrap/scss/';
$outputDir = 'public/assets/css/';

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Get command line argument for selective compilation
$compileType = isset($argv[1]) ? $argv[1] : '';

$success = true;
$generatedFiles = [];

// Determine what to compile based on arguments
$compileBootstrap = ($compileType === '' || $compileType === 'bootstrap');
$compileCustom = ($compileType === '' || $compileType === 'custom');

// 1. Compile Bootstrap to condensed/minified CSS (if requested)
if ($compileBootstrap) {
    if ($verbose) echo "Compiling Bootstrap to condensed CSS...\n";
    $success &= compileScss(
        'bootstrap.scss',
        $outputDir . 'bootstrap.min.css',
        \ScssPhp\ScssPhp\OutputStyle::COMPRESSED,
        [$scssDir, $bootstrapScssDir]
    );
    if ($success) {
        $generatedFiles[] = $outputDir . "bootstrap.min.css (condensed Bootstrap)";
    }
}

// 2. Compile custom styles to expanded CSS (if requested)
if ($compileCustom) {
    if ($verbose) echo "Compiling custom styles to expanded CSS...\n";
    $success &= compileScss(
        'custom.scss',
        $outputDir . 'custom.css',
        \ScssPhp\ScssPhp\OutputStyle::EXPANDED,
        [$scssDir, $bootstrapScssDir]
    );
    if ($success) {
        $generatedFiles[] = $outputDir . "custom.css (expanded custom styles for client editing)";
    }
}

if ($success) {
    if ($verbose) {
        echo "\nSCSS compilation completed successfully!\n";
        if (!empty($generatedFiles)) {
            echo "Generated files:\n";
            foreach ($generatedFiles as $file) {
                echo "- " . $file . "\n";
            }
        }
    }
} else {
    echo "\nSCSS compilation failed.\n";
    exit(1);
}
