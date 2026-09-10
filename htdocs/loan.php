<?php
/**
 * UNMOOR CLUB - LOAN / ACCOUNT HOLD
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $db->prepare("SELECT coins FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$coins = (float)$stmt->fetchColumn();

if ($coins >= 0) {
    header("Location: dashboard.php");
    exit;
}

$need = abs($coins);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account On Hold • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card" style="text-align: center;">
            <div style="font-size: 48px; margin-bottom: 12px;">⚠️</div>
            
            <h2 style="font-size: 20px; font-weight: 900; color: var(--accent-red); margin-bottom: 8px;">
                Account On Hold
            </h2>
            
            <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;">
                Your account balance is currently negative. Features are temporarily locked until the deficit is cleared.
            </p>

            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 20px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 800;">Required Deposit</div>
                <div style="font-size: 26px; font-weight: 900; color: var(--accent-red); margin-top: 4px;">
                    🪙 -<?= number_format($need, 2) ?>
                </div>
            </div>

            <a class="btn btn-gold btn-block" href="deposit.php" style="padding: 14px; text-decoration: none; text-align: center;">
                💳 Clear Balance via Deposit
            </a>

            <div style="margin-top: 18px;">
                <a href="logout.php" style="color: var(--text-muted); font-size: 13px; text-decoration: none;">
                    🚪 Log Out
                </a>
            </div>
        </div>
    </div>
</body>
</html>
