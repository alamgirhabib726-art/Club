<?php
/**
 * =========================================
 * AUTHENTICATION CORE
 * Private Club System
 * =========================================
 */

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -----------------------------------------
   LOGIN FUNCTION
------------------------------------------ */
function auth_login(string $email, string $password): bool
{
    global $db;

    $stmt = $db->prepare("
        SELECT id, password, role, status
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return false;
    }

    // 🚫 Block banned users
    if ($user['status'] === 'banned') {
        return false;
    }

    // 🔐 Verify password
    if (!password_verify($password, $user['password'])) {
        return false;
    }

    // 🔒 Secure session
    session_regenerate_id(true);

    $_SESSION['uid']        = (int)$user['id'];
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['login_time'] = time();

    return true;
}

/* -----------------------------------------
   LOGOUT FUNCTION
------------------------------------------ */
function auth_logout(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/* -----------------------------------------
   CHECK LOGGED IN
------------------------------------------ */
function auth_check(): bool
{
    return isset($_SESSION['user_id']) || isset($_SESSION['uid']);
}

/* -----------------------------------------
   CURRENT USER ID
------------------------------------------ */
function auth_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null);
}

/* -----------------------------------------
   CURRENT USER ROLE
------------------------------------------ */
function auth_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/* -----------------------------------------
   ADMIN CHECK
------------------------------------------ */
function auth_is_admin(): bool
{
    return auth_role() === 'admin';
}