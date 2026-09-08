<?php
// Session configuration for web & iframe security
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );

    $sameSite = $isHttps ? 'None' : 'Lax';
    $secure   = $isHttps;

    ini_set('session.cookie_samesite', $sameSite);
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_path', '/');
    ini_set('session.gc_maxlifetime', '2592000');

    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => $sameSite
    ]);
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docRoot = __DIR__ . '/htdocs';
$filePath = $docRoot . $uri;

// Helper to execute PHP script with directory context
function executePhpScript(string $absolutePath, string $uri): bool {
    if (!file_exists($absolutePath)) {
        return false;
    }
    $_SERVER['SCRIPT_NAME']     = $uri;
    $_SERVER['PHP_SELF']        = $uri;
    $_SERVER['SCRIPT_FILENAME'] = $absolutePath;
    chdir(dirname($absolutePath));
    include $absolutePath;
    return true;
}

// 1. Handle root "/" or empty URI -> htdocs/index.php
if ($uri === '' || $uri === '/') {
    return executePhpScript($docRoot . '/index.php', '/index.php');
}

// 2. Handle direct static files (images, css, js, fonts) or direct PHP scripts
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    if (str_ends_with($filePath, '.php')) {
        return executePhpScript($filePath, $uri);
    }
    // Return false to allow built-in server to serve static assets directly
    return false;
}

// 3. Handle directory index (e.g. /account/ or /admin/)
if (is_dir($filePath)) {
    $indexFile = rtrim($filePath, '/') . '/index.php';
    if (file_exists($indexFile)) {
        return executePhpScript($indexFile, rtrim($uri, '/') . '/index.php');
    }
}

// 4. Handle extensionless PHP routes (e.g. /login -> /login.php, /dashboard -> /dashboard.php)
if (file_exists($filePath . '.php')) {
    return executePhpScript($filePath . '.php', $uri . '.php');
}

// 5. Fallback check for root index.php
if (file_exists($docRoot . '/index.php')) {
    return executePhpScript($docRoot . '/index.php', '/index.php');
}

return false;
