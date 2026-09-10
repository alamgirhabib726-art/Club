<?php
/**
 * UNMOOR CLUB - APPLICATION UNDER REVIEW
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
    SELECT apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* Approved → dashboard */
if ($user && $user['apply_status'] === 'approved') {
    header("Location: dashboard.php");
    exit;
}

/* ================= CHECK PENDING PAYMENT ================= */
$stmt = $db->prepare("
    SELECT id
    FROM payments
    WHERE user_id = ?
      AND type = 'apply'
      AND status = 'pending'
    LIMIT 1
");
$stmt->execute([$uid]);

if (!$stmt->fetch()) {
    header("Location: apply_payment.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Under Review • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            
            <div style="font-size: 52px; margin-bottom: 12px;">⏳</div>

            <h2 style="font-size: 20px; font-weight: 900; margin-bottom: 8px; color: #ffffff;">
                Application Under Review
            </h2>

            <p style="font-size: 14px; color: var(--text-muted); line-height: 1.6; margin-bottom: 16px;">
                Your application payment has been submitted successfully.<br>
                Our administration team is reviewing your details.
            </p>

            <div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.25); border-radius: var(--radius-md); padding: 12px; margin-bottom: 20px; font-size: 13px; color: var(--accent-gold);">
                ⏱ Verification usually takes <b>1–12 hours</b>
            </div>

            <a href="logout.php" class="btn btn-secondary btn-block" style="padding: 12px;">
                🚪 Log Out
            </a>

            <div style="margin-top: 24px; font-size: 12px; color: var(--text-dim);">
                Unmoor Club © <?= date("Y") ?>
            </div>

        </div>
    </div>
</body>
</html>
