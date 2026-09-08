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
