<?php
/**
 * UNMOOR CLUB - CONVERT COINS
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* LOGIN */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* FETCH USER */
$stmt = $db->prepare("
    SELECT id, coins, purchase_balance, status, apply_status
    FROM users
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['status'] !== 'active' && $user['status'] !== 'premium') || $user['apply_status'] !== 'approved') {
    die("ACCESS DENIED");
}

$error = $success = "";

/* HANDLE CONVERT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coins = floatval($_POST['coins'] ?? 0);

    if ($coins <= 0) {
        $error = "Invalid coin amount.";
    } elseif ($coins > (float)$user['coins']) {
        $error = "Not enough coins in your balance.";
    } elseif ($user['coins'] < 0) {
        $error = "Account locked. Please deposit first.";
    } else {
        $amount = $coins * 10; // 1 coin = 10 taka

        $db->prepare("
            UPDATE users
            SET coins = coins - ?,
                purchase_balance = purchase_balance + ?
            WHERE id = ?
        ")->execute([$coins, $amount, $user['id']]);

        $db->prepare("
            INSERT INTO coin_history (user_id, amount, type, reference, created_at)
            VALUES (?, ?, 'convert', 'Converted to Purchase Balance', NOW())
        ")->execute([$user['id'], -$coins]);

        $user['coins'] -= $coins;
        $user['purchase_balance'] += $amount;

        $success = "Successfully converted 🪙" . number_format($coins, 2) . " into ৳" . number_format($amount, 2) . " purchase power!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Convert Coins • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Convert Coins", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 16px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                🔁 Coin to Purchase Power
            </h3>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;">
                Convert your earned club coins directly into store purchase credit at a guaranteed rate of <b>1 Coin = ৳10</b>.
            </p>

            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; font-size: 13.5px; padding: 4px 0;">
                    <span style="color: var(--text-muted);">🪙 Coin Balance:</span>
                    <span style="font-weight: 900; color: <?= $user['coins'] < 0 ? 'var(--accent-red)' : 'var(--accent-green)' ?>;">
                        <?= number_format($user['coins'], 2) ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13.5px; padding: 4px 0;">
                    <span style="color: var(--text-muted);">🛒 Purchase Balance:</span>
                    <span style="font-weight: 900; color: var(--accent-gold);">
                        ৳<?= number_format($user['purchase_balance'], 2) ?>
                    </span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 14px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 14px;">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label class="form-label">Coins to Convert</label>
                    <input type="number" name="coins" step="0.01" min="0.01" class="form-control" placeholder="Enter coin amount" required>
                </div>

                <button type="submit" class="btn btn-gold btn-block" style="padding: 14px; margin-top: 10px;">
                    Convert to Purchase Credit
                </button>
            </form>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
