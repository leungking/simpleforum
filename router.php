<?php
// Simple router for PHP built-in server to serve static files
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = __DIR__ . $uri;
// If the requested resource exists as a file, let the server serve it
if ($uri !== '/' && file_exists($path) && is_file($path)) {
    return false;
}
// Otherwise, hand off to the application
require __DIR__ . '/index.php';
