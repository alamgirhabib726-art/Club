<?php
/* ==================================================
   DATABASE CONNECTION
================================================== */

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'if0_40736960_club';
$DB_USER = getenv('DB_USER') ?: 'if0_40736960';
$DB_PASS = getenv('DB_PASS') ?: 'kUTjS5kXvmO';

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

$db = null;
try {
    $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    try {
        $db = new PDO("mysql:host=127.0.0.1;dbname=$DB_NAME;charset=utf8mb4", "root", "", $pdoOptions);
    } catch (PDOException $e2) {
        @shell_exec('su -s /bin/bash mysql -c "mariadbd --datadir=/var/lib/mysql" >/dev/null 2>&1 &');
        usleep(500000);
        try {
            $db = new PDO("mysql:host=127.0.0.1;dbname=$DB_NAME;charset=utf8mb4", "root", "", $pdoOptions);
        } catch (PDOException $e3) {
            http_response_code(500);
            die("Database connection failed.");
        }
    }
}

/* ==================================================
   SESSION
================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==================================================
   MAINTENANCE MODE (GLOBAL)
================================================== */

$settings = $db
    ->query("SELECT maintenance, updated_at FROM settings WHERE id = 1")
    ->fetch();

$maintenance = (int)($settings['maintenance'] ?? 0);
$updatedAt   = $settings['updated_at'] ?? null;

$currentPath = $_SERVER['REQUEST_URI'] ?? '';

/* Detect role */
$role = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
}

/* Maintenance rules */
if ($maintenance === 1) {

    $isAdminPath =
        (strpos($currentPath, "/admin") === 0) ||
        (strpos($currentPath, "admin_login.php") !== false);

    if ($role !== 'admin' && !$isAdminPath) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Maintenance</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body{
                    margin:0;height:100vh;
                    display:flex;align-items:center;justify-content:center;
                    background:#020617;color:#e5e7eb;font-family:system-ui;
                }
                .card{
                    max-width:420px;padding:32px;
                    border-radius:22px;
                    background:#020617;
                    border:1px solid #1f2937;
                    text-align:center;
                }
                .badge{
                    background:#7f1d1d;color:#fecaca;
                    padding:8px 16px;border-radius:999px;
                    font-weight:800;font-size:13px;
                }
                h1{margin:16px 0 8px}
                p{color:#9ca3af}
            </style>
        </head>
        <body>
            <div class="card">
                <div class="badge">🔒 MAINTENANCE</div>
                <h1>System Upgrade</h1>
                <p>অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন</p>
                <?php if ($updatedAt): ?>
                    <small><?= date("d M Y, h:i A", strtotime($updatedAt)) ?></small>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/* ==================================================
   DEBT / NEGATIVE BALANCE GLOBAL CONTROL
================================================== */

if (!empty($_SESSION['user_id'])) {

    $stmt = $db->prepare("
        SELECT coins, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userState = $stmt->fetch();

    if ($userState && $userState['role'] === 'user') {

        $coins = (float)$userState['coins'];
        $isDebt = ($coins < 0);

        /*
          ALLOWED EVEN IF DEBT:
          - dashboard.php
          - deposit.php
          - history.php
          - account.php
          - loan.php
          - logout.php
        */

        $allowedPages = [
            'dashboard.php',
            'deposit.php',
            'history.php',
            'account.php',
            'loan.php',
            'logout.php',
            'avatar.php'
        ];

        $currentFile = basename(parse_url($currentPath, PHP_URL_PATH));

        if ($isDebt && !in_array($currentFile, $allowedPages, true)) {
            header("Location: loan.php");
            exit;
        }

        /* Make available globally */
        define('IS_DEBT_USER', $isDebt);
        define('USER_COINS', $coins);
    }
}