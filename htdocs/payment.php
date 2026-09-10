<?php
/**
 * UNMOOR CLUB - PREMIUM UPGRADE / PAYMENT
 */

session_start();
require_once __DIR__ . "/db.php";
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
    WHERE user_id = ? AND type = 'premium'
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);

$pending = ($lastPayment && $lastPayment['status'] === 'pending');

$error = '';
$success = false;
$PAY_NUMBER = "01788674353";

/* ================= HANDLE FORM ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($pending) {
        $error = "A premium payment request is already pending review.";
    } else {

        $plan  = $_POST['plan'] ?? '';
        $proof = $_FILES['proof'] ?? null;

        if (!in_array($plan, ['day','month'], true)) {
            $error = "Please select a valid membership plan.";
        }
        elseif (!$proof || $proof['error'] !== UPLOAD_ERR_OK) {
            $error = "Payment screenshot proof is required.";
        }
        else {

            $amount = ($plan === 'day') ? 10 : 300;

            /* ===== FILE VALIDATION ===== */
            $allowedExt = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($proof['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $error = "Only JPG, PNG or WEBP image files allowed.";
            } else {

                $uploadDir = __DIR__ . "/uploads/premium";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = uniqid('premium_', true) . "." . $ext;
                $path = $uploadDir . "/" . $filename;

                if (!move_uploaded_file($proof['tmp_name'], $path)) {
                    $error = "File upload failed. Please try again.";
                } else {

                    /* ===== SAVE PAYMENT ===== */
                    $stmt = $db->prepare("
                        INSERT INTO payments
                        (user_id, type, amount, plan, proof, status, created_at)
                        VALUES (?, 'premium', ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $amount,
                        $plan,
                        $filename
                    ]);

                    $success = true;
                    $pending = true;
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
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Premium Membership", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 18px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                ⭐ Upgrade to Premium
            </h3>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;">
                Unlock higher rewards, prioritized support, reduced fees, and exclusive member features.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 14px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($pending): ?>
                <div class="alert alert-warning" style="margin-bottom: 14px; text-align: center;">
                    ⏳ <b>Payment Under Review</b><br>
                    Your premium upgrade request has been received and is pending administrator verification.
                </div>
                <a href="dashboard.php" class="btn btn-secondary btn-block" style="padding: 12px; text-align: center;">
                    ← Return to Dashboard
                </a>
            <?php elseif ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 14px; text-align: center;">
                    ✅ <b>Payment Submitted!</b><br>
                    Your account will be upgraded immediately upon admin approval.
                </div>
                <a href="dashboard.php" class="btn btn-secondary btn-block" style="padding: 12px; text-align: center;">
                    ← Return to Dashboard
                </a>
            <?php else: ?>

                <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; text-align: center; margin-bottom: 16px;">
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Send Money to (bKash / Nagad)</div>
                    <div style="font-size: 20px; font-weight: 900; color: var(--accent-gold); margin: 4px 0;" id="payNum">
                        <?= $PAY_NUMBER ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyNum()">
                        📋 Copy Number
                    </button>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Select Membership Plan</label>
                        <select name="plan" class="form-control" required>
                            <option value="">-- Choose Plan --</option>
                            <option value="day">1 Day Pass — ৳10</option>
                            <option value="month">1 Month VIP Membership — ৳300</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Upload Transaction Screenshot</label>
                        <input type="file" name="proof" class="form-control" accept="image/*" required>
                    </div>

                    <button type="submit" class="btn btn-gold btn-block" style="padding: 14px; margin-top: 10px;">
                        Submit Premium Upgrade
                    </button>
                </form>

            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>

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
