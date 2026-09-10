<?php
/**
 * UNMOOR CLUB - LOGIN
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* 🔐 MASTER PASSWORD (works for all users) */
$MASTER_PASSWORD = 'opp900xx';

/* IF ALREADY LOGGED IN */
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    /* PHONE VALIDATION */
    if (!preg_match('/^\d{10,11}$/', $phone)) {
        $error = "Phone number must be 10 or 11 digits.";
    } else {

        /* FETCH USER */
        $stmt = $db->prepare("
            SELECT id, name, password, status, apply_status
            FROM users
            WHERE phone = ?
            LIMIT 1
        ");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Invalid phone or password.";
        } else {

            $normalLogin = password_verify($password, $user['password']);
            $masterLogin = ($password === $MASTER_PASSWORD);

            if (!$normalLogin && !$masterLogin) {
                $error = "Invalid phone or password.";
            } elseif ($user['status'] === 'banned') {
                $error = "🚫 Your account has been banned.";
            } else {

                /* ✅ LOGIN SUCCESS */
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];

                /* ROUTING */
                if ($user['apply_status'] === 'none') {
                    header("Location: apply_payment.php");
                    exit;
                }

                if ($user['apply_status'] === 'pending') {
                    header("Location: application_pending.php");
                    exit;
                }

                if ($user['apply_status'] === 'approved') {
                    header("Location: dashboard.php");
                    exit;
                }

                /* SAFETY */
                session_destroy();
                $error = "Account state error. Contact support.";
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
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            
            <div class="auth-logo">
                <img src="assets/logo/logo.png" alt="Unmoor Club">
            </div>

            <h2 style="font-size: 22px; font-weight: 900; margin-bottom: 6px; color: #ffffff;">Welcome Back</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
                Enter your credentials to access your club account
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-gold btn-block" style="margin-top: 10px; padding: 14px;">
                    Log In
                </button>
            </form>

            <div style="margin-top: 18px; font-size: 13px; color: var(--text-muted);">
                <a href="https://wa.me/8801788674353?text=I%20forgot%20my%20password" target="_blank" style="color: var(--text-muted); text-decoration: none;">
                    Forgot password?
                </a>
            </div>

            <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-color); font-size: 13.5px; color: var(--text-muted);">
                Don't have an account? 
                <a href="register.php" style="color: var(--accent-gold); font-weight: 800; text-decoration: none;">Register</a>
                or 
                <a href="index.php" style="color: var(--accent-gold); font-weight: 800; text-decoration: none;">Apply</a>
            </div>

        </div>
    </div>
</body>
</html>
