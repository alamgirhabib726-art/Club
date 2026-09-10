<?php
/**
 * UNMOOR CLUB - DEPOSIT FUNDS
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, name, status, apply_status, coins
    FROM users
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= SECURITY ================= */
if (!$user || $user['status'] === 'banned') {
    session_destroy();
    die("ACCESS DENIED");
}

if ($user['status'] !== 'active' && $user['status'] !== 'premium') {
    header("Location: application_pending.php");
    exit;
}

/* ================= CONFIG ================= */
$PAY_NUMBER = "01788674353";
$methods = [
    'bkash' => 'bKash (Send Money)',
    'nagad' => 'Nagad (Send Money)'
];

$msg = '';
$error = '';

/* ================= APPLY COUPON ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {

    $couponCode = trim($_POST['coupon'] ?? '');

    if ($couponCode === '') {
        $error = "Please enter a coupon code.";
    } else {

        $stmt = $db->prepare("
            SELECT id, amount
            FROM coupons
            WHERE code = ?
              AND type = 'deposit'
              AND (status = 'active' OR status IS NULL)
              AND used_by IS NULL
            LIMIT 1
        ");
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            $error = "❌ Invalid or already used coupon.";
        } else {

            $coins = $coupon['amount'] / 10;

            $db->beginTransaction();
            try {

                $db->prepare("
                    UPDATE users
                    SET coins = coins + ?
                    WHERE id = ?
                ")->execute([
                    $coins,
                    $user['id']
                ]);

                $db->prepare("
                    INSERT INTO coin_history
                        (user_id, amount, type, reference, created_at)
                    VALUES
                        (?, ?, 'credit', 'Coupon deposit', NOW())
                ")->execute([
                    $user['id'],
                    $coins
                ]);

                $db->prepare("
                    UPDATE coupons
                    SET status = 'used',
                        used_by = ?,
                        used_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $user['id'],
                    $coupon['id']
                ]);

                $db->commit();
                $msg = "✅ Successfully redeemed coupon for 🪙" . number_format($coins, 2) . " coins!";
                $user['coins'] += $coins;

            } catch (Exception $e) {
                $db->rollBack();
                $error = "❌ Coupon processing failed. Try again.";
            }
        }
    }
}

/* ================= MANUAL DEPOSIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit'])) {

    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? '';

    if ($amount <= 0) {
        $error = "Please enter a valid deposit amount.";
    }
    elseif (!isset($methods[$method])) {
        $error = "Please select a valid payment method.";
    }
    elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        $error = "Payment screenshot proof is required.";
    }
    else {

        $dir = __DIR__ . "/uploads/deposit";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG or WEBP images allowed.";
        } else {
            $file = "deposit_" . time() . "_" . rand(100,999) . "." . $ext;

            if (!move_uploaded_file($_FILES['proof']['tmp_name'], $dir . "/" . $file)) {
                $error = "Screenshot upload failed. Please try again.";
            } else {

                $db->prepare("
                    INSERT INTO payments
                    (user_id, type, amount, method, proof, status, created_at)
                    VALUES (?, 'deposit', ?, ?, ?, 'pending', NOW())
                ")->execute([
                    $user['id'],
                    $amount,
                    $method,
                    $file
                ]);

                $msg = "✅ Deposit of ৳" . number_format($amount, 2) . " submitted. Awaiting admin approval.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deposit Funds • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Deposit Funds", "/dashboard.php") ?>

        <!-- CURRENT BALANCE -->
        <div class="card" style="text-align: center; padding: 18px 14px; margin-bottom: 14px;">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 800;">Current Balance</div>
            <div style="font-size: 26px; font-weight: 900; color: <?= $user['coins'] < 0 ? 'var(--accent-red)' : 'var(--accent-green)' ?>; margin-top: 4px;">
                🪙 <?= number_format($user['coins'], 2) ?>
            </div>
            <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 2px;">
                Rate: 1 Coin = ৳10
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success" style="margin-bottom: 14px;">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 14px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- 1. REDEEM COUPON CARD -->
        <div class="card" style="margin-bottom: 14px;">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 10px; text-transform: uppercase;">
                🎟️ Instant Coupon Deposit
            </h3>
            <form method="post">
                <div class="form-group">
                    <input type="text" name="coupon" class="form-control" placeholder="Enter deposit coupon code">
                </div>
                <button type="submit" name="apply_coupon" class="btn btn-secondary btn-block" style="padding: 12px;">
                    Apply Coupon
                </button>
            </form>
        </div>

        <!-- 2. MANUAL SEND MONEY CARD -->
        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 10px; text-transform: uppercase;">
                💳 Manual Money Deposit
            </h3>

            <div style="background: var(--bg-dark); border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 14px; text-align: center; margin-bottom: 14px;">
                <div style="font-size: 12px; color: var(--text-muted);">Send Money To (bKash / Nagad)</div>
                <div style="font-size: 20px; font-weight: 900; color: var(--accent-gold); margin: 6px 0;" id="depNum">
                    <?= $PAY_NUMBER ?>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyDepNum()">
                    📋 Copy Number
                </button>
            </div>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Deposit Amount (BDT ৳)</label>
                    <input type="number" name="amount" class="form-control" placeholder="e.g. 500" min="10" step="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="method" class="form-control" required>
                        <option value="">Select Method</option>
                        <option value="bkash">bKash (Send Money)</option>
                        <option value="nagad">Nagad (Send Money)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Screenshot</label>
                    <input type="file" name="proof" class="form-control" accept="image/*" required>
                </div>

                <button type="submit" name="submit_deposit" class="btn btn-gold btn-block" style="padding: 14px; margin-top: 10px;">
                    Submit Deposit Request
                </button>
            </form>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>

    <script>
    function copyDepNum() {
        const num = document.getElementById('depNum').innerText.trim();
        navigator.clipboard.writeText(num).then(() => {
            alert('Number copied to clipboard: ' + num);
        });
    }
    </script>
</body>
</html>
