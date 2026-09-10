<?php
/**
 * UNMOOR CLUB - TRANSFER COINS
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$senderId = (int)$_SESSION['user_id'];

/* ================= FETCH SENDER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, coins, status, apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$senderId]);
$sender = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sender || ($sender['status'] !== 'active' && $sender['status'] !== 'premium') || $sender['apply_status'] !== 'approved') {
    die("ACCESS DENIED");
}

$msg   = $_SESSION['transfer_msg'] ?? '';
$error = $_SESSION['transfer_err'] ?? '';
unset($_SESSION['transfer_msg'], $_SESSION['transfer_err']);

/* ================= POST LOCK ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_SESSION['transfer_lock'])) {
        header("Location: transfer.php");
        exit;
    }
    $_SESSION['transfer_lock'] = true;

    $toPhone = trim($_POST['phone'] ?? '');
    $amount  = (float)($_POST['coins'] ?? 0);

    /* VALIDATION */
    if (!preg_match('/^01\d{9}$/', $toPhone)) {
        $_SESSION['transfer_err'] = "❌ Invalid receiver phone number (11 digits required)";
        goto REDIRECT;
    }

    if ($amount < 0.1) {
        $_SESSION['transfer_err'] = "❌ Minimum transfer is 0.1 coin";
        goto REDIRECT;
    }

    if ($toPhone === $sender['phone']) {
        $_SESSION['transfer_err'] = "❌ You cannot transfer coins to yourself";
        goto REDIRECT;
    }

    if ((float)$sender['coins'] < $amount) {
        $_SESSION['transfer_err'] = "❌ Insufficient coin balance";
        goto REDIRECT;
    }

    /* FETCH RECEIVER */
    $stmt = $db->prepare("
        SELECT id, name, phone, status, apply_status
        FROM users
        WHERE phone = ?
        LIMIT 1
    ");
    $stmt->execute([$toPhone]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receiver) {
        $_SESSION['transfer_err'] = "❌ Receiver account not found";
        goto REDIRECT;
    }

    if (($receiver['status'] !== 'active' && $receiver['status'] !== 'premium') || $receiver['apply_status'] !== 'approved') {
        $_SESSION['transfer_err'] = "❌ Receiver account is not active or verified";
        goto REDIRECT;
    }

    /* ================= ATOMIC TRANSFER ================= */
    $db->beginTransaction();
    try {

        $stmt = $db->prepare("
            UPDATE users
            SET coins = coins - ?
            WHERE id = ? AND coins >= ?
        ");
        $stmt->execute([$amount, $senderId, $amount]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception("INSUFFICIENT_BALANCE");
        }

        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id = ?
        ")->execute([$amount, $receiver['id']]);

        $db->prepare("
            INSERT INTO coin_history
            (user_id, amount, type, source_user_id, source_name, source_number, created_at)
            VALUES (?, ?, 'transfer_out', ?, ?, ?, NOW())
        ")->execute([
            $senderId,
            -$amount,
            $receiver['id'],
            $receiver['name'],
            $receiver['phone']
        ]);

        $db->prepare("
            INSERT INTO coin_history
            (user_id, amount, type, source_user_id, source_name, source_number, created_at)
            VALUES (?, ?, 'transfer_in', ?, ?, ?, NOW())
        ")->execute([
            $receiver['id'],
            $amount,
            $sender['id'],
            $sender['name'],
            $sender['phone']
        ]);

        $db->commit();
        $_SESSION['transfer_msg'] = "✅ Successfully transferred 🪙" . number_format($amount, 2) . " to " . htmlspecialchars($receiver['name']) . "!";

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['transfer_err'] = "❌ Transfer failed. Please try again.";
    }

REDIRECT:
    unset($_SESSION['transfer_lock']);
    header("Location: transfer.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transfer Coins • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Transfer Coins", "/dashboard.php") ?>

        <!-- CURRENT BALANCE -->
        <div class="card" style="text-align: center; padding: 18px 14px; margin-bottom: 14px;">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 800;">Available to Send</div>
            <div style="font-size: 26px; font-weight: 900; color: <?= $sender['coins'] < 0 ? 'var(--accent-red)' : 'var(--accent-green)' ?>; margin-top: 4px;">
                🪙 <?= number_format($sender['coins'], 2) ?>
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

        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 12px; text-transform: uppercase;">
                🔄 Member to Member Transfer
            </h3>

            <form method="post">
                <div class="form-group">
                    <label class="form-label">Recipient Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="01XXXXXXXXX" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Amount (Coins)</label>
                    <input type="number" name="coins" class="form-control" step="0.01" min="0.1" placeholder="e.g. 10.00" required>
                </div>

                <button type="submit" class="btn btn-gold btn-block" style="padding: 14px; margin-top: 10px;">
                    Send Coins Instantly
                </button>
            </form>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
