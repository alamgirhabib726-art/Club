<?php
/**
 * =========================================
 * REGISTER — UNMOOR CLUB (COUPON ENABLED)
 * =========================================
 */

require_once "db.php";
require_once "core/config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

// Preserve input
$name   = '';
$phone  = '';
$couponInput = '';

/* -----------------------------------------
   HANDLE FORM SUBMISSION
------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $couponInput = trim($_POST['coupon'] ?? '');

    /* ---------- VALIDATION ---------- */

    if (strlen($name) < 3) {
        $errors[] = "Name must be at least 3 characters.";
    }

    if (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors[] = "Phone number must be 10 or 11 digits.";
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters.";
    }

    /* ---------- CHECK EXISTING USER ---------- */
    if (!$errors) {
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = "This phone number is already registered.";
        }
    }

    /* ---------- COUPON VALIDATION ---------- */
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

    /* ---------- CREATE USER ---------- */
    if (!$errors) {

        $db->beginTransaction();

        try {

            $status = 'pending';
            $apply  = 'pending';
            $coins  = 0;
            $cycleStart = null;

            /* ===== COUPON AUTO APPROVAL ===== */
            if ($coupon) {

                $status = 'active';
                $apply  = 'approved';
                $cycleStart = date('Y-m-d H:i:s');

                $extraCoins = floor(($coupon['amount'] - 150) / 10);
                $coins = 5 + max(0, $extraCoins);

                $systemCoins = ($coupon['amount'] / 10) - $coins;

            }

            /* CREATE USER */
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

            /* ===== COUPON POST LOGIC ===== */
            if ($coupon) {

                /* MARK COUPON USED */
                $db->prepare("
                    UPDATE coupons
                    SET used_by = ?, used_at = NOW(), status = 'used'
                    WHERE id = ?
                ")->execute([$uid, $coupon['id']]);

                /* SYSTEM COINS */
                if ($systemCoins > 0) {
                    $db->prepare("
                        UPDATE users
                        SET coins = coins + ?
                        WHERE role = 'system'
                    ")->execute([$systemCoins]);
                }

                /* PAYMENT LOG */
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

            /* AUTO LOGIN */
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
<title>Register • <?= SITE_NAME ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/style.css">
</head>

<body>

<div class="auth-card">

  <div class="auth-logo">
    <img src="assets/logo/logo.png" alt="Unmoor Club">
  </div>

  <h2>Create Account</h2>
  <p style="font-size:13px; opacity:.8;">Instant access after registration</p>

  <?php if ($errors): ?>
    <div class="error-box">
      <?php foreach ($errors as $e): ?>
        • <?= htmlspecialchars($e) ?><br>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>

    <input type="text" name="name" placeholder="Full Name"
           value="<?= htmlspecialchars($name) ?>" required>

    <input type="text" name="phone" placeholder="Phone Number"
           value="<?= htmlspecialchars($phone) ?>" required>

    <input type="password" name="password" placeholder="Password" required>

    <input type="text" name="coupon"
           placeholder="Coupon Code (optional)"
           value="<?= htmlspecialchars($couponInput) ?>">

    <button type="submit" class="submit-btn">
      Register
    </button>
  </form>

  <div style="margin-top:14px;font-size:13px;">
    Already have an account? <a href="login.php">Login</a>
  </div>

</div>

</body>
</html>
