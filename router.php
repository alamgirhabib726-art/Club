<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docRoot = __DIR__ . '/htdocs';
$filePath = $docRoot . $uri;

if ($uri !== '/' && file_exists($filePath)) {
    if (is_dir($filePath) && file_exists($filePath . '/index.php')) {
        include $filePath . '/index.php';
        return true;
    }
    return false;
}

if ($uri !== '/' && file_exists($filePath . '.php')) {
    include $filePath . '.php';
    return true;
}

return false;
