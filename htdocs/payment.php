<?php
/**
 * UNMOOR CLUB - PREMIUM UPGRADE / PAYMENT (RESTORED OLD VISUAL DESIGN)
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/config.php";
require_once __DIR__ . "/core/components.php";

/* ================= LOGIN REQUIRED ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* ================= CHECK USER STATUS ================= */
$stmt = $db->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
$stmt->execute([$user_id]);
$userStatus = $stmt->fetchColumn();

if ($userStatus === 'premium') {
    header("Location: dashboard.php");
    exit;
}

/* ================= CHECK LAST PREMIUM PAYMENT ================= */
$stmt = $db->prepare("
    SELECT status 
    FROM payments 
    WHERE user_id = ? AND type = 'premium' AND status = 'pending'
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);

$pending = ($lastPayment && $lastPayment['status'] === 'pending');

$error = '';
$success = false;
$PAY_NUMBER = PAYMENT_NUMBER;

/* ================= HANDLE FORM ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($pending) {
        $error = "A premium payment request is already pending review.";
    } else {

        $plan  = trim($_POST['plan'] ?? '');
        $proof = $_FILES['proof'] ?? null;

        if (!in_array($plan, ['day', 'month'], true)) {
            $error = "Please select a valid membership plan (Day or Month).";
        }
        elseif (!$proof || $proof['error'] !== UPLOAD_ERR_OK) {
            $error = "Payment screenshot proof is required.";
        }
        elseif (($proof['size'] ?? 0) > 8 * 1024 * 1024) {
            $error = "Screenshot file is too large (maximum 8MB).";
        }
        else {

            $amount = ($plan === 'day') ? 10 : 300;

            /* ===== FILE VALIDATION ===== */
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($proof['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $error = "Only JPG, JPEG, PNG or WEBP image files are allowed.";
            } else {

                // MIME validation
                $validMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp'];
                $detectedMime = '';
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detectedMime = finfo_file($finfo, $proof['tmp_name']);
                    finfo_close($finfo);
                }

                if ($detectedMime && !in_array($detectedMime, $validMimes, true)) {
                    $error = "The uploaded file is not a valid image.";
                } else {

                    $uploadDir = __DIR__ . "/uploads/premium";
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $filename = "premium_" . time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
                    $path = $uploadDir . "/" . $filename;

                    if (!move_uploaded_file($proof['tmp_name'], $path)) {
                        $error = "File upload failed. Please verify permissions and try again.";
                    } else {

                        /* ===== SAVE PAYMENT ===== */
                        try {
                            $stmt = $db->prepare("
                                INSERT INTO payments
                                (user_id, type, amount, plan, method, proof, status, created_at)
                                VALUES (?, 'premium', ?, ?, 'manual', ?, 'pending', NOW())
                            ");
                            $stmt->execute([
                                $user_id,
                                $amount,
                                $plan,
                                $filename
                            ]);

                            $success = true;
                            $pending = true;
                        } catch (Exception $e) {
                            @unlink($path);
                            $error = "Database error saving payment: " . $e->getMessage();
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
    <title>Upgrade to Premium • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body.premium-body {
            background-color: #f1f5f9;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .premium-wrapper {
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }
        .premium-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 28px 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        .premium-title {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .premium-subtitle {
            font-size: 13.5px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .pay-box-light {
            background: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            border-radius: 14px;
            padding: 16px;
            text-align: center;
            margin-bottom: 20px;
        }
        .pay-box-title {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .pay-box-val {
            font-size: 22px;
            font-weight: 900;
            color: #d97706;
            margin: 6px 0;
        }
        .copy-btn-light {
            background: #e2e8f0;
            color: #334155;
            border: none;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
        }
        .copy-btn-light:hover {
            background: #cbd5e1;
        }
        .form-group-light {
            margin-bottom: 16px;
            text-align: left;
        }
        .form-label-light {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-input-light {
            width: 100%;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
            transition: all 0.2s;
        }
        .form-input-light:focus {
            border-color: #9333ea;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.15);
        }
        .submit-btn-purple {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #9333ea, #7e22ce);
            color: #ffffff;
            font-size: 15px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(147, 51, 234, 0.35);
            transition: all 0.2s;
            margin-top: 10px;
        }
        .submit-btn-purple:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .alert-orange {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #c2410c;
            padding: 14px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
            line-height: 1.5;
        }
        .alert-green-light {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #15803d;
            padding: 14px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body class="premium-body">
    <div class="premium-wrapper">
        <div class="premium-card">
            <h3 class="premium-title">
                ⭐ Upgrade to Premium
            </h3>
            <p class="premium-subtitle">
                Unlock higher rewards, prioritized support, reduced fees, and exclusive member features.
            </p>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #f87171; color: #b91c1c; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 14px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($pending): ?>
                <div class="alert-orange">
                    ⏳ <b>পেমেন্ট রিভিউাধীন আছে (Payment Under Review)</b><br>
                    আপনার প্রিমিয়াম আপগ্রেড অনুরোধটি গ্রহণ করা হয়েছে এবং অ্যাডমিন যাচাইকরণের অপেক্ষায় রয়েছে।<br>
                    <span style="font-size: 12px; font-weight: 500; opacity: 0.85;">Your premium upgrade request has been received and is pending administrator verification.</span>
                </div>
                <a href="dashboard.php" class="submit-btn-purple" style="display: block; text-align: center; text-decoration: none; background: #334155; box-shadow: none;">
                    ← Return to Dashboard
                </a>
            <?php elseif ($success): ?>
                <div class="alert-green-light">
                    ✅ <b>পেমেন্ট সফলভাবে জমা হয়েছে! (Payment Submitted!)</b><br>
                    অ্যাডমিন অনুমোদন করার সাথে সাথেই আপনার অ্যাকাউন্ট প্রিমিয়াম হয়ে যাবে।<br>
                    <span style="font-size: 12px; font-weight: 500; opacity: 0.85;">Your account will be upgraded immediately upon admin approval.</span>
                </div>
                <a href="dashboard.php" class="submit-btn-purple" style="display: block; text-align: center; text-decoration: none; background: #334155; box-shadow: none;">
                    ← Return to Dashboard
                </a>
            <?php else: ?>

                <div class="pay-box-light">
                    <div class="pay-box-title">Send Money to (bKash / Nagad)</div>
                    <div class="pay-box-val" id="payNum">
                        <?= $PAY_NUMBER ?>
                    </div>
                    <button type="button" class="copy-btn-light" onclick="copyNum()">
                        📋 Copy Number
                    </button>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <div class="form-group-light">
                        <label class="form-label-light">Select Membership Plan</label>
                        <select name="plan" class="form-input-light" required>
                            <option value="">-- Choose Plan --</option>
                            <option value="day">1 Day Pass — ৳10</option>
                            <option value="month">1 Month VIP Membership — ৳300</option>
                        </select>
                    </div>

                    <div class="form-group-light">
                        <label class="form-label-light">Upload Transaction Screenshot</label>
                        <input type="file" name="proof" class="form-input-light" accept="image/*" required>
                    </div>

                    <button type="submit" class="submit-btn-purple">
                        Submit Premium Upgrade
                    </button>
                </form>

            <?php endif; ?>

            <div style="margin-top: 18px; text-align: center;">
                <a href="dashboard.php" style="color: #64748b; font-size: 13px; text-decoration: none; font-weight: 700;">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
    function copyNum() {
        const n = document.getElementById('payNum').innerText.trim();
        navigator.clipboard.writeText(n).then(() => {
            alert('Number copied: ' + n);
        });
    }
    </script>
</body>
</html>
