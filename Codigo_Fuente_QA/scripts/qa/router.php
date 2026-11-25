<?php

$publicRoot = realpath(__DIR__.'/../../public');
$uriPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$candidate = realpath($publicRoot.DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uriPath), DIRECTORY_SEPARATOR));

if ($candidate !== false && str_starts_with($candidate, $publicRoot.DIRECTORY_SEPARATOR) && is_file($candidate)) {
    return false;
}

require $publicRoot.DIRECTORY_SEPARATOR.'index.php';
