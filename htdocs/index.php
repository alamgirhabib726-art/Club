<?php
/**
 * UNMOOR CLUB - APPLY FOR MEMBERSHIP
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* BLOCK LOGGED IN USERS */
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($name) < 3) {
        $error = "Name must be at least 3 characters.";
    } elseif (!preg_match('/^\d{10,11}$/', $phone)) {
        $error = "Phone number must be 10 or 11 digits.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        /* CHECK DUPLICATE PHONE */
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);

        if ($stmt->fetch()) {
            $error = "Phone number already registered.";
        } else {

            /* CREATE PENDING USER */
            $stmt = $db->prepare("
                INSERT INTO users
                (name, phone, password, role, status, apply_status, created_at)
                VALUES (?, ?, ?, 'user', 'pending', 'pending', NOW())
            ");
            $stmt->execute([
                $name,
                $phone,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $uid = $db->lastInsertId();

            /* TEMP SESSION */
            $_SESSION['apply_user_id'] = $uid;
            $_SESSION['user_id'] = $uid;

            /* REDIRECT TO PAYMENT */
            header("Location: apply_payment.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Apply for Membership • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            
            <div class="auth-logo">
                <img src="assets/logo/logo.png" alt="Unmoor Club">
            </div>

            <h2 style="font-size: 22px; font-weight: 900; margin-bottom: 6px; color: #ffffff;">Club Membership</h2>
            <div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.25); border-radius: var(--radius-md); padding: 10px; margin-bottom: 16px; font-size: 13px; color: var(--accent-gold);">
                📝 Application fee: <b>৳150</b> • ⏳ Admin approval required
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="index.php">
                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Full Name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Create Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-gold btn-block" style="margin-top: 10px; padding: 14px;">
                    Apply for Membership
                </button>
            </form>

            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border-color); font-size: 13.5px; color: var(--text-muted);">
                Already applied or registered? 
                <a href="login.php" style="color: var(--accent-gold); font-weight: 800; text-decoration: none;">Log In</a>
            </div>

        </div>
    </div>
</body>
</html>
