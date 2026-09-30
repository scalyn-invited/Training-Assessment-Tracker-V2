<?php

// Only the PHP development server uses this router. Production points at public/index.php.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$public = realpath(__DIR__.'/../public');
$file = realpath($public.rawurldecode($path));
if ($path !== '/' && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require $public.'/index.php';
