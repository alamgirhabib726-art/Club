<?php
/**
 * UNMOOR CLUB - REGISTER
 */

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/config.php";
require_once __DIR__ . "/core/components.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

$name   = '';
$phone  = '';
$couponInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $password    = $_POST['password'] ?? '';
    $couponInput = trim($_POST['coupon'] ?? '');

    /* VALIDATION */
    if (strlen($name) < 3) {
        $errors[] = "Name must be at least 3 characters.";
    }

    if (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors[] = "Phone number must be 10 or 11 digits.";
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters.";
    }

    /* CHECK EXISTING USER */
    if (!$errors) {
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = "This phone number is already registered.";
        }
    }

    /* COUPON VALIDATION */
    $coupon = null;

    if (!$errors && $couponInput !== '') {
        $stmt = $db->prepare("
            SELECT id, amount, used_by
            FROM coupons
            WHERE code = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$couponInput]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            $errors[] = "Invalid coupon code.";
        } elseif ($coupon['used_by']) {
            $errors[] = "Coupon already used.";
        } elseif ($coupon['amount'] < 150) {
            $errors[] = "Coupon value must be at least ৳150 for registration.";
        }
    }

    /* CREATE USER */
    if (!$errors) {
        $db->beginTransaction();

        try {
            $status = 'pending';
            $apply  = 'pending';
            $coins  = 0;
            $cycleStart = null;
            $systemCoins = 0;

            if ($coupon) {
                $status = 'active';
                $apply  = 'approved';
                $cycleStart = date('Y-m-d H:i:s');

                $extraCoins = floor(($coupon['amount'] - 150) / 10);
                $coins = 5 + max(0, $extraCoins);

                $systemCoins = ($coupon['amount'] / 10) - $coins;
            }

            $stmt = $db->prepare("
                INSERT INTO users
                    (name, phone, password, role, status, apply_status, coins, coin_cycle_start, created_at)
                VALUES
                    (?, ?, ?, 'user', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $name,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $status,
                $apply,
                $coins,
                $cycleStart
            ]);

            $uid = (int)$db->lastInsertId();

            if ($coupon) {
                $db->prepare("
                    UPDATE coupons
                    SET used_by = ?, used_at = NOW(), status = 'used'
                    WHERE id = ?
                ")->execute([$uid, $coupon['id']]);

                if ($systemCoins > 0) {
                    $db->prepare("
                        UPDATE users
                        SET coins = coins + ?
                        WHERE role = 'system'
                    ")->execute([$systemCoins]);
                }

                $db->prepare("
                    INSERT INTO payments
                    (user_id, type, amount, status, source, created_at)
                    VALUES (?, 'coupon', ?, 'approved', 'registration', NOW())
                ")->execute([
                    $uid,
                    $coupon['amount']
                ]);
            }

            $db->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;
            $_SESSION['role']    = 'user';
            $_SESSION['login_time'] = time();

            header("Location: dashboard.php");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Registration failed. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account • <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            
            <div class="auth-logo">
                <img src="assets/logo/logo.png" alt="Unmoor Club">
            </div>

            <h2 style="font-size: 22px; font-weight: 900; margin-bottom: 6px; color: #ffffff;">Create Account</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
                Instant access with registration coupon or application
            </p>

            <?php if ($errors): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px; text-align: left;">
                    <?php foreach ($errors as $e): ?>
                        • <?= htmlspecialchars($e) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="John Doe" value="<?= htmlspecialchars($name) ?>" required>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($phone) ?>" required>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="At least 6 characters" required>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Coupon Code (optional)</label>
                    <input type="text" name="coupon" class="form-control" placeholder="Have an instant access coupon?" value="<?= htmlspecialchars($couponInput) ?>">
                </div>

                <button type="submit" class="btn btn-gold btn-block" style="margin-top: 10px; padding: 14px;">
                    Register Now
                </button>
            </form>

            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border-color); font-size: 13.5px; color: var(--text-muted);">
                Already have an account? 
                <a href="login.php" style="color: var(--accent-gold); font-weight: 800; text-decoration: none;">Log In</a>
            </div>

        </div>
    </div>
</body>
</html>
