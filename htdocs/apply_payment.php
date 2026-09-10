<?php
/**
 * UNMOOR CLUB - APPLICATION PAYMENT (RESTORED OLD VISUAL DESIGN)
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/config.php";
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
if ($user['apply_status'] === 'approved' || in_array($user['status'], ['active', 'premium'], true)) {
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

/* ================= CONFIG (UNIFIED SOURCE) ================= */
$BASE_AMOUNT = 150;
$PAY_NUMBER  = PAYMENT_NUMBER;

$methods = [
    'bkash' => 'bKash (Send Money)',
    'nagad' => 'Nagad (Send Money)'
];

$error = "";

/* ================= SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $coupon = trim($_POST['coupon'] ?? '');

    /* ================= COUPON FLOW ================= */
    if ($coupon !== '') {

        $stmt = $db->prepare("
            SELECT id, amount, used_by
            FROM coupons
            WHERE code = ?
              AND (type = 'apply' OR type IS NULL OR type = '')
              AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$coupon]);
        $cp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cp || !empty($cp['used_by']) || $cp['amount'] < $BASE_AMOUNT) {
            $error = "Invalid, expired, or already used coupon code.";
        } else {

            $db->beginTransaction();
            try {

                // Atomic coupon lock to avoid double usage
                $cpUpd = $db->prepare("
                    UPDATE coupons
                    SET status = 'used',
                        used_by = ?,
                        used_at = NOW()
                    WHERE id = ? AND status = 'active' AND used_by IS NULL
                ");
                $cpUpd->execute([$uid, $cp['id']]);

                if ($cpUpd->rowCount() === 0) {
                    throw new Exception("Coupon has already been used.");
                }

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

                // Payment audit record
                $db->prepare("
                    INSERT INTO payments
                    (user_id, type, amount, method, source, status, created_at)
                    VALUES (?, 'apply', ?, 'coupon', 'coupon', 'approved', NOW())
                ")->execute([$uid, $cp['amount']]);

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
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = "Coupon processing failed: " . $e->getMessage();
            }
        }
    }

    /* ================= MANUAL PAYMENT ================= */
    else {

        $method = trim($_POST['method'] ?? '');

        if (!isset($methods[$method])) {
            $error = "Please select a valid payment method (bKash or Nagad).";
        }
        elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            $error = "Payment screenshot proof is required.";
        }
        else {

            // Check if user already has a pending application payment
            $chkPending = $db->prepare("
                SELECT id FROM payments
                WHERE user_id = ? AND type = 'apply' AND status = 'pending'
                LIMIT 1
            ");
            $chkPending->execute([$uid]);
            if ($chkPending->fetch()) {
                $error = "You already have a pending application payment under review.";
            } else {

                $tmpName = $_FILES['proof']['tmp_name'];
                $fileSize = $_FILES['proof']['size'] ?? 0;

                if ($fileSize > 8 * 1024 * 1024) {
                    $error = "Image file is too large (maximum 8MB).";
                } else {

                    $dir = __DIR__ . "/uploads/apply";
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

                    // Validate extension
                    if (!in_array($ext, $allowedExts, true)) {
                        $error = "Only JPG, JPEG, PNG or WEBP images are allowed.";
                    } else {

                        // Validate MIME type
                        $validMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp'];
                        $detectedMime = '';
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $detectedMime = finfo_file($finfo, $tmpName);
                            finfo_close($finfo);
                        }

                        if ($detectedMime && !in_array($detectedMime, $validMimes, true)) {
                            $error = "The uploaded file is not a valid image.";
                        } else {

                            $file = "apply_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
                            $destination = $dir . "/" . $file;

                            if (!move_uploaded_file($tmpName, $destination)) {
                                $error = "Screenshot upload failed. Please verify storage permissions and try again.";
                            } else {

                                $db->beginTransaction();
                                try {

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

                                    $db->commit();

                                    header("Location: application_pending.php");
                                    exit;

                                } catch (Exception $e) {
                                    if ($db->inTransaction()) {
                                        $db->rollBack();
                                    }
                                    @unlink($destination);
                                    $error = "Payment submission failed. Please try again.";
                                }
                            }
                        }
                    }
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
    <style>
        body.apply-body {
            background-color: #020617;
            color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .apply-wrapper {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }
        .apply-card {
            background: linear-gradient(135deg, #0f172a, #020617);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 26px;
            padding: 28px 24px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6);
            text-align: center;
        }
        .apply-title {
            font-size: 20px;
            font-weight: 900;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .apply-subtitle {
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 20px;
        }
        .pay-number-box {
            background: rgba(250, 204, 21, 0.05);
            border: 2px dashed rgba(250, 204, 21, 0.35);
            border-radius: 20px;
            padding: 18px;
            margin-bottom: 20px;
        }
        .pay-number-title {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .pay-number-val {
            font-size: 24px;
            font-weight: 900;
            color: #facc15;
            margin: 8px 0;
            letter-spacing: 1px;
        }
        .copy-btn {
            background: #22c55e;
            color: #022c22;
            border: none;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        .copy-btn:hover {
            background: #16a34a;
            color: #ffffff;
        }
        .apply-form-group {
            text-align: left;
            margin-bottom: 14px;
        }
        .apply-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .apply-input, .apply-select {
            width: 100%;
            padding: 12px 14px;
            background: #020617;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
        }
        .apply-input:focus, .apply-select:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
        }
        .apply-submit-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #22c55e, #15803d);
            color: #ffffff;
            font-size: 15px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.35);
            transition: all 0.2s ease;
            margin-top: 10px;
        }
        .apply-submit-btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        /* Toast notification */
        #toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: #facc15;
            border: 1px solid #facc15;
            padding: 10px 20px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            transition: transform 0.3s ease;
            z-index: 9999;
        }
        #toast.show {
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body class="apply-body">
    <div class="apply-wrapper">
        <div class="apply-card">
            <h2 class="apply-title">📝 Membership Payment</h2>
            <p class="apply-subtitle">
                Complete your application payment of <b>৳<?= $BASE_AMOUNT ?></b>
            </p>

            <div class="pay-number-box">
                <div class="pay-number-title">Send Money (bKash / Nagad)</div>
                <div class="pay-number-val" id="payNum">
                    <?= $PAY_NUMBER ?>
                </div>
                <button type="button" class="copy-btn" onclick="copyNumber()">
                    📋 Copy Number
                </button>
            </div>

            <?php if ($error): ?>
                <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 12px; border-radius: 12px; font-size: 13.5px; font-weight: 700; margin-bottom: 16px; text-align: left;">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="apply-form-group">
                    <label class="apply-label">Have an Application Coupon? (Optional)</label>
                    <input type="text" name="coupon" class="apply-input" placeholder="Enter coupon code for instant access">
                </div>

                <div style="text-align: center; margin: 12px 0; color: #64748b; font-size: 11.5px; font-weight: 800; letter-spacing: 0.5px;">
                    — OR PAY MANUALLY —
                </div>

                <div class="apply-form-group">
                    <label class="apply-label">Payment Method</label>
                    <select name="method" class="apply-select">
                        <option value="">Select Payment Method</option>
                        <option value="bkash">bKash (Personal / Send Money)</option>
                        <option value="nagad">Nagad (Personal / Send Money)</option>
                    </select>
                </div>

                <div class="apply-form-group">
                    <label class="apply-label">Payment Screenshot Proof</label>
                    <input type="file" name="proof" class="apply-input" accept="image/*">
                </div>

                <button type="submit" class="apply-submit-btn">
                    Submit Application Payment
                </button>
            </form>

            <div style="margin-top: 20px; text-align: center;">
                <a href="logout.php" style="color: #94a3b8; font-size: 13px; text-decoration: none; font-weight: 700;">
                    🚪 Log Out
                </a>
            </div>
        </div>
    </div>

    <div id="toast">Number copied to clipboard!</div>

    <script>
    function copyNumber() {
        const num = document.getElementById('payNum').innerText.trim();
        navigator.clipboard.writeText(num).then(() => {
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        });
    }
    </script>
</body>
</html>
