<?php
/**
 * UNMOOR CLUB - LOGIN (USER AUTHENTICATION)
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* 🔐 SECURE MASTER PASSWORD (ONLY via ENV in non-production environments) */
$devMasterPassword = getenv('DEV_MASTER_PASSWORD') ?: '';
$isDevEnv = (getenv('APP_ENV') !== 'production') && empty(getenv('RAILWAY_ENVIRONMENT'));
$allowMasterBypass = $isDevEnv && !empty($devMasterPassword);

/* IF ALREADY LOGGED IN: ROUTE PROPERLY WITHOUT REDIRECT LOOPS */
if (!empty($_SESSION['user_id'])) {
    $chkStmt = $db->prepare("SELECT id, status, apply_status FROM users WHERE id = ? LIMIT 1");
    $chkStmt->execute([$_SESSION['user_id']]);
    $currentUser = $chkStmt->fetch(PDO::FETCH_ASSOC);

    if ($currentUser && $currentUser['status'] !== 'banned') {
        if ($currentUser['apply_status'] === 'none') {
            header("Location: apply_payment.php");
            exit;
        } elseif ($currentUser['apply_status'] === 'pending') {
            header("Location: application_pending.php");
            exit;
        } elseif ($currentUser['apply_status'] === 'approved' || in_array($currentUser['status'], ['active', 'premium'], true)) {
            header("Location: dashboard.php");
            exit;
        }
    } else {
        session_unset();
        session_destroy();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $rawPhone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    /* NORMALIZE PHONE NUMBER (support spaces, dashes, +88, 88 prefixes) */
    $cleanPhone = preg_replace('/[\s\-]/', '', $rawPhone);
    if (str_starts_with($cleanPhone, '+880')) {
        $cleanPhone = '0' . substr($cleanPhone, 4);
    } elseif (str_starts_with($cleanPhone, '880')) {
        $cleanPhone = '0' . substr($cleanPhone, 3);
    }

    /* PHONE VALIDATION */
    if (!preg_match('/^\d{10,11}$/', $cleanPhone)) {
        $error = "Phone number must be 10 or 11 digits (e.g. 01XXXXXXXXX).";
    } else {

        /* FETCH USER (match normalized or raw phone) */
        $stmt = $db->prepare("
            SELECT id, name, phone, password, status, apply_status, role
            FROM users
            WHERE phone = ? OR phone = ?
            LIMIT 1
        ");
        $stmt->execute([$cleanPhone, $rawPhone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Invalid phone or password.";
        } else {

            $normalLogin = password_verify($password, $user['password']);
            $masterLogin = $allowMasterBypass && ($password === $devMasterPassword);

            if (!$normalLogin && !$masterLogin) {
                $error = "Invalid phone or password.";
            } elseif ($user['status'] === 'banned') {
                $error = "🚫 Your account has been banned. Please contact support.";
            } else {

                /* ✅ LOGIN SUCCESS — INITIALIZE SESSION */
                session_regenerate_id(true);
                $_SESSION['uid']       = (int)$user['id'];
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role']      = $user['role'] ?? 'user';
                $_SESSION['login_time'] = time();

                /* ROUTING BASED ON USERS.APPLY_STATUS */
                if ($user['apply_status'] === 'none') {
                    header("Location: apply_payment.php");
                    exit;
                }

                if ($user['apply_status'] === 'pending') {
                    header("Location: application_pending.php");
                    exit;
                }

                if ($user['apply_status'] === 'approved' || in_array($user['status'], ['active', 'premium'], true)) {
                    header("Location: dashboard.php");
                    exit;
                }

                /* FALLBACK SAFETY IF UNKNOWN STATE */
                header("Location: apply_payment.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body.login-body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #1c0702 url('assets/bg/bg.svg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            position: relative;
        }
        body.login-body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(5, 7, 15, 0.52);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            z-index: 1;
        }
        .login-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            color: #1f2937;
            border-radius: 24px;
            padding: 32px 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .login-logo {
            width: 100%;
            max-width: 250px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .login-title {
            font-size: 24px;
            font-weight: 900;
            color: #111827;
            margin-bottom: 6px;
        }
        .login-subtitle {
            font-size: 13.5px;
            color: #6b7280;
            margin-bottom: 24px;
        }
        .login-form-group {
            text-align: left;
            margin-bottom: 16px;
        }
        .login-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 6px;
        }
        .login-input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #d1d5db;
            border-radius: 12px;
            font-size: 15px;
            color: #111827;
            background: #f9fafb;
            outline: none;
            transition: all 0.2s ease;
        }
        .login-input:focus {
            border-color: #8b5cf6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
        }
        .login-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: #ffffff;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(109, 40, 217, 0.3);
            transition: all 0.2s ease;
            margin-top: 8px;
        }
        .login-btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .login-alert {
            background: #fee2e2;
            border: 1px solid #f87171;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 18px;
            text-align: left;
        }
        .login-links {
            margin-top: 20px;
            font-size: 13.5px;
            color: #6b7280;
        }
        .login-links a {
            color: #7c3aed;
            font-weight: 800;
            text-decoration: none;
        }
        .login-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="login-logo">
                <?php render_unmoor_logo_svg('240px'); ?>
            </div>

            <h2 class="login-title">Welcome Back</h2>
            <p class="login-subtitle">
                Enter your credentials to access your club account
            </p>

            <?php if ($error): ?>
                <div class="login-alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <div class="login-form-group">
                    <label class="login-label">Phone Number</label>
                    <input type="text" name="phone" class="login-input" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required autofocus>
                </div>

                <div class="login-form-group">
                    <label class="login-label">Password</label>
                    <input type="password" name="password" class="login-input" placeholder="••••••••" required>
                </div>

                <button type="submit" class="login-btn">
                    Log In
                </button>
            </form>

            <div style="margin-top: 16px; font-size: 13px;">
                <a href="https://wa.me/8801788674353?text=I%20forgot%20my%20password" target="_blank" style="color: #6b7280; text-decoration: none;">
                    Forgot password?
                </a>
            </div>

            <div class="login-links">
                Don't have an account? 
                <a href="register.php">Register</a>
                or 
                <a href="register.php">Apply</a>
            </div>

        </div>
    </div>
</body>
</html>
