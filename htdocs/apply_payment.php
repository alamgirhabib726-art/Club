<?php
/**
 * UNMOOR CLUB - APPLICATION PAYMENT
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, status, apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

/* ================= ROUTING ================= */
if ($user['apply_status'] === 'approved') {
    header("Location: dashboard.php");
    exit;
}

if ($user['apply_status'] === 'pending') {
    $chk = $db->prepare("
        SELECT id FROM payments
        WHERE user_id = ?
          AND type = 'apply'
          AND status = 'pending'
        LIMIT 1
    ");
    $chk->execute([$uid]);

    if ($chk->fetch()) {
        header("Location: application_pending.php");
        exit;
    }
}

/* ================= CONFIG ================= */
$BASE_AMOUNT = 150;
$PAY_NUMBER  = "01788674353";

$methods = [
    'bkash' => 'bKash',
    'nagad' => 'Nagad'
];

$error = "";

/* ================= SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $coupon = trim($_POST['coupon'] ?? '');

    /* ================= COUPON FLOW ================= */
    if ($coupon !== '') {

        $stmt = $db->prepare("
            SELECT id, amount
            FROM coupons
            WHERE code = ?
              AND type = 'apply'
              AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$coupon]);
        $cp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cp || $cp['amount'] < $BASE_AMOUNT) {
            $error = "Invalid or expired coupon code.";
        } else {

            $db->beginTransaction();
            try {

                $baseCoins  = 5;
                $extraCoins = max(0, floor(($cp['amount'] - 150) / 10));
                $totalCoins = $baseCoins + $extraCoins;

                $db->prepare("
                    UPDATE users
                    SET
                        status = 'active',
                        apply_status = 'approved',
                        coins = coins + ?,
                        coin_cycle_start = NOW(),
                        last_coin_cut = NULL
                    WHERE id = ?
                ")->execute([$totalCoins, $uid]);

                $db->prepare("
                    UPDATE users
                    SET coins = coins + 10
                    WHERE role = 'system'
                ")->execute();

                $db->prepare("
                    UPDATE coupons
                    SET status='used',
                        used_by=?,
                        used_at=NOW()
                    WHERE id=?
                ")->execute([$uid, $cp['id']]);

                $db->prepare("
                    INSERT INTO coin_history
                    (user_id, amount, type, reference, created_at)
                    VALUES (?, ?, 'apply_bonus', 'Application Approved via Coupon', NOW())
                ")->execute([$uid, $totalCoins]);

                $systemId = $db->query("
                    SELECT id FROM users WHERE role='system' LIMIT 1
                ")->fetchColumn();

                if ($systemId) {
                    $db->prepare("
                        INSERT INTO coin_history
                        (user_id, amount, type, reference, created_at)
                        VALUES (?, 10, 'registration_income', 'User Application (Coupon)', NOW())
                    ")->execute([$systemId]);
                }

                $db->commit();
                header("Location: dashboard.php");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $error = "Coupon processing failed.";
            }
        }
    }

    /* ================= MANUAL PAYMENT ================= */
    else {

        $method = $_POST['method'] ?? '';

        if (!isset($methods[$method])) {
            $error = "Select a valid payment method.";
        }
        elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            $error = "Payment screenshot required.";
        }
        else {

            $dir = __DIR__ . "/uploads/apply";
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $ext  = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed)) {
                $error = "Only JPG, PNG or WEBP images allowed.";
            } else {
                $file = "apply_" . time() . "_" . rand(1000,9999) . "." . $ext;

                if (!move_uploaded_file($_FILES['proof']['tmp_name'], "$dir/$file")) {
                    $error = "Upload failed.";
                } else {

                    $db->prepare("
                        INSERT INTO payments
                        (user_id, type, amount, method, proof, status, created_at)
                        VALUES (?, 'apply', ?, ?, ?, 'pending', NOW())
                    ")->execute([
                        $uid,
                        $BASE_AMOUNT,
                        $method,
                        $file
                    ]);

                    $db->prepare("
                        UPDATE users
                        SET apply_status = 'pending'
                        WHERE id = ?
                    ")->execute([$uid]);

                    header("Location: application_pending.php");
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Payment • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <div class="card" style="text-align: center;">
            <h2 style="font-size: 20px; font-weight: 900; margin-bottom: 6px; color: #ffffff;">📝 Membership Payment</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                Complete your application payment of <b>৳<?= $BASE_AMOUNT ?></b>
            </p>

            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 16px;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Send Money (bKash / Nagad)</div>
                <div style="font-size: 22px; font-weight: 900; color: var(--accent-gold); margin: 8px 0;" id="payNum">
                    <?= $PAY_NUMBER ?>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyNumber()">
                    📋 Copy Number
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px; text-align: left;">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Have an Application Coupon? (Optional)</label>
                    <input type="text" name="coupon" class="form-control" placeholder="Enter coupon code for instant access">
                </div>

                <div style="text-align: center; margin: 12px 0; color: var(--text-dim); font-size: 12px; font-weight: 800;">
                    — OR PAY MANUALLY —
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Payment Method</label>
                    <select name="method" class="form-control">
                        <option value="">Select Payment Method</option>
                        <option value="bkash">bKash (Personal / Send Money)</option>
                        <option value="nagad">Nagad (Personal / Send Money)</option>
                    </select>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label">Payment Screenshot Proof</label>
                    <input type="file" name="proof" class="form-control" accept="image/*">
                </div>

                <button type="submit" class="btn btn-gold btn-block" style="padding: 14px; margin-top: 10px;">
                    Submit Application Payment
                </button>
            </form>

            <div style="margin-top: 20px; text-align: center;">
                <a href="logout.php" style="color: var(--text-muted); font-size: 13px; text-decoration: none;">
                    🚪 Log Out
                </a>
            </div>
        </div>

        <?= render_support_widget() ?>

    </div>

    <script>
    function copyNumber() {
        const num = document.getElementById('payNum').innerText.trim();
        navigator.clipboard.writeText(num).then(() => {
            alert('Payment number copied: ' + num);
        });
    }
    </script>
</body>
</html>
