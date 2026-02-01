<?php
/**
 * Router for PHP Built-in Development Server
 *
 * Usage: php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPath = __DIR__ . '/public';
$dataPath = __DIR__;
$requestedFile = $publicPath . $uri;

// Check if file is in /data/ directory (images, audio files)
$isDataFile = str_starts_with($uri, '/data/');
if ($isDataFile) {
    $requestedFile = $dataPath . $uri;
}

// Serve static files directly if they exist
if ($uri !== '/' && file_exists($requestedFile) && is_file($requestedFile)) {
    // Set correct MIME types for common files
    $extension = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'xml' => 'application/xml',
    ];

    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
    }

    // Output the file contents
    readfile($requestedFile);
    return;
}

// Route everything else through index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $publicPath . '/index.php';
