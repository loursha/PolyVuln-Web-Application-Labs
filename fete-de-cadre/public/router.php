<?php
// Router shim for `php -S`: serve existing static files directly, else hand to index.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file) && !preg_match('#\.php$#', $path)) {
    return false; // let the built-in server serve the asset
}
require __DIR__ . '/index.php';
