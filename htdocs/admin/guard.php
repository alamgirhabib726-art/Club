<?php
/**
 * UNMOOR CLUB - ADMIN SECURITY GUARD
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.php");
    exit;
}

$stmt = $db->prepare("SELECT id, name, phone, email, role, status FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    die("<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Access Denied</title><style>body{background:#0b0f19;color:#ef4444;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}div{text-align:center;padding:30px;background:#0f172a;border:1px solid #1f2937;border-radius:18px}h1{margin:0 0 10px;font-size:24px}p{color:#94a3b8;margin:0 0 20px}a{color:#facc15;font-weight:700}</style></head><body><div><h1>🔒 ACCESS DENIED</h1><p>Administrator privileges required.</p><a href='admin_login.php'>Back to Admin Login</a></div></body></html>");
}

if (!function_exists('log_admin_action')) {
    function log_admin_action($pdo, $adminId, $action) {
        try {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare("INSERT INTO logs (user_id, action, created_at) VALUES (?, ?, ?)")
                ->execute([$adminId, $action, $now]);
        } catch (Throwable $t) {}
    }
}
