<?php
/* ==================================================
   DATABASE CONNECTION
================================================== */

/* ==================================================
   DATABASE CONFIGURATION & AUTO-DISCOVERY (RAILWAY / CLOUD COMPATIBLE)
================================================== */

$dbUrl = getenv('MYSQL_URL') ?: (getenv('DATABASE_URL') ?: '');
$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_NAME = 'if0_40736960_club';
$DB_USER = 'root';
$DB_PASS = '';

if (!empty($dbUrl) && str_starts_with($dbUrl, 'mysql://')) {
    $parsedUrl = parse_url($dbUrl);
    $DB_HOST = $parsedUrl['host'] ?? '127.0.0.1';
    $DB_PORT = (string)($parsedUrl['port'] ?? '3306');
    $DB_USER = $parsedUrl['user'] ?? 'root';
    $DB_PASS = $parsedUrl['pass'] ?? '';
    $DB_NAME = ltrim($parsedUrl['path'] ?? 'if0_40736960_club', '/');
} else {
    $DB_HOST = getenv('MYSQLHOST') ?: (getenv('MYSQL_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1'));
    $DB_PORT = getenv('MYSQLPORT') ?: (getenv('MYSQL_PORT') ?: (getenv('DB_PORT') ?: '3306'));
    $DB_NAME = getenv('MYSQLDATABASE') ?: (getenv('MYSQL_DATABASE') ?: (getenv('DB_NAME') ?: 'if0_40736960_club'));
    $DB_USER = getenv('MYSQLUSER') ?: (getenv('MYSQL_USER') ?: (getenv('DB_USER') ?: (getenv('DB_HOST') ? 'if0_40736960' : 'root')));
    $DB_PASS = getenv('MYSQLPASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: (getenv('DB_PASS') ?: (getenv('DB_HOST') ? 'kUTjS5kXvmO' : '')));
}

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
];

$db = null;

// Helper to auto-import schema and seed rows if database is empty
function bootstrapDatabaseIfEmpty($pdo) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $chk = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        } else {
            $chk = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
        }

        if (!$chk) {
            $sqlFile = __DIR__ . '/../database.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                if ($driver === 'mysql') {
                    // Try executing raw dump directly with multi statements enabled
                    try {
                        $pdo->exec($sqlContent);
                    } catch (Throwable $ex) {
                        // Fallback to statement-by-statement execution
                        $queries = preg_split('/;\s*[\r\n]+/', $sqlContent);
                        foreach ($queries as $q) {
                            $q = trim($q);
                            if (!empty($q) && !str_starts_with($q, '/*') && !str_starts_with($q, '--')) {
                                try {
                                    $pdo->exec($q);
                                } catch (Throwable $t) {}
                            }
                        }
                    }
                }
            }
        }
    } catch (Throwable $t) {
        // Continue if check fails
    }
}

$isExternalMySQL = !empty(getenv('MYSQL_URL')) || !empty(getenv('DATABASE_URL')) || !empty(getenv('MYSQLHOST')) || !empty(getenv('DB_HOST'));
$isProduction = $isExternalMySQL || (getenv('RAILWAY_ENVIRONMENT') !== false) || (getenv('APP_ENV') === 'production');

try {
    if ($isExternalMySQL) {
        $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
        $db = new PDO($dsn, $DB_USER, $DB_PASS, $pdoOptions);
        bootstrapDatabaseIfEmpty($db);
    } elseif (getenv('DB_DRIVER') === 'mysql') {
        $db = new PDO("mysql:host=127.0.0.1;dbname=if0_40736960_club;charset=utf8mb4", "root", "", $pdoOptions);
        bootstrapDatabaseIfEmpty($db);
    } else {
        // Single local development source of truth
        $sqlitePath = __DIR__ . '/../database.sqlite';
        $db = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $db->exec("PRAGMA foreign_keys = ON;");
        bootstrapDatabaseIfEmpty($db);
    }

    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->sqliteCreateFunction('NOW', function() {
            return date('Y-m-d H:i:s');
        }, 0);
        $db->sqliteCreateFunction('CURDATE', function() {
            return date('Y-m-d');
        }, 0);
        $db->sqliteCreateFunction('UNIX_TIMESTAMP', function($val = null) {
            if ($val === null) return time();
            return is_numeric($val) ? (int)$val : (strtotime((string)$val) ?: time());
        }, -1);
        $db->sqliteCreateFunction('IF', function($cond, $trueVal, $falseVal) {
            return $cond ? $trueVal : $falseVal;
        }, 3);
        $db->sqliteCreateFunction('CONCAT', function(...$args) {
            return implode('', $args);
        }, -1);
        $db->sqliteCreateFunction('FIND_IN_SET', function($str, $strList) {
            $arr = explode(',', (string)$strList);
            $idx = array_search((string)$str, $arr, true);
            return $idx === false ? 0 : $idx + 1;
        }, 2);
    }
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    if ($isProduction) {
        die("<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Database Error • Unmoor Club</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#020617;color:#e5e7eb;font-family:system-ui;text-align:center;padding:16px}.card{max-width:440px;padding:32px;background:#0f172a;border:1px solid #1f2937;border-radius:18px}h2{color:#ef4444;margin-top:0}p{color:#9ca3af;font-size:14px;line-height:1.6}</style></head><body><div class='card'><h2>Database Service Unavailable</h2><p>The configured database service could not be reached. Please check the database server status, network connectivity, and deployment environment variables.</p></div></body></html>");
    } else {
        die("<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Database Error</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#020617;color:#e5e7eb;font-family:system-ui;text-align:center;padding:16px}.card{max-width:440px;padding:32px;background:#0f172a;border:1px solid #1f2937;border-radius:18px}h2{color:#ef4444;margin-top:0}p{color:#9ca3af;font-size:14px;line-height:1.6}</style></head><body><div class='card'><h2>Database Initialization Error</h2><p>Unable to connect to local database engine. Check server logs for details.</p></div></body></html>");
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