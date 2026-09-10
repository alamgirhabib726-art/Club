<?php
/**
 * UNMOOR CLUB - DONATE & SUPPORT
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

/* ================= FETCH DONOR ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, coins, status, apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['status'] !== 'active' && $user['status'] !== 'premium') || $user['apply_status'] !== 'approved') {
    die("ACCESS DENIED");
}

/* ================= SYSTEM RECEIVER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone
    FROM users
    WHERE role = 'system'
    LIMIT 1
");
$stmt->execute();
$receiver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receiver) {
    die("SYSTEM ACCOUNT NOT FOUND");
}

$msg = $_SESSION['donation_msg'] ?? '';
$error = $_SESSION['donation_err'] ?? '';
unset($_SESSION['donation_msg'], $_SESSION['donation_err']);

/* ================= DONATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = round((float)($_POST['amount'] ?? 0), 2);

    if ($amount <= 0) {
        $_SESSION['donation_err'] = "❌ Enter a valid amount";
    }
    elseif ($amount > (float)$user['coins']) {
        $_SESSION['donation_err'] = "❌ Insufficient coin balance";
    }
    else {

        $db->beginTransaction();
        try {

            $db->prepare("UPDATE users SET coins = coins - ? WHERE id = ?")
               ->execute([$amount, $user['id']]);

            $db->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")
               ->execute([$amount, $receiver['id']]);

            $db->prepare("
                INSERT INTO coin_history
                    (user_id, amount, type,
                     source_user_id, source_name, source_number, created_at)
                VALUES
                    (?, ?, 'donation_out', ?, ?, ?, NOW())
            ")->execute([
                $user['id'],
                -$amount,
                $receiver['id'],
                'Unmoor Club Reserve',
                $receiver['phone'] ?? 'SYSTEM'
            ]);

            $db->prepare("
                INSERT INTO coin_history
                    (user_id, amount, type,
                     source_user_id, source_name, source_number, created_at)
                VALUES
                    (?, ?, 'donation_in', ?, ?, ?, NOW())
            ")->execute([
                $receiver['id'],
                $amount,
                $user['id'],
                $user['name'],
                $user['phone']
            ]);

            $db->prepare("
                INSERT INTO payments
                    (user_id, type, amount, status, source, created_at)
                VALUES
                    (?, 'donation', ?, 'approved', ?, NOW())
            ")->execute([
                $user['id'],
                $amount,
                $user['name'].' ('.$user['phone'].')'
            ]);

            $db->commit();
            $_SESSION['donation_msg'] = "💚 Thank you! Your donation of 🪙" . number_format($amount, 2) . " has been received.";

        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['donation_err'] = "❌ Donation failed. Please try again.";
        }
    }

    header("Location: donation.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donate & Support • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Donate & Support", "/dashboard.php") ?>

        <!-- CURRENT BALANCE -->
        <div class="card" style="text-align: center; padding: 18px 14px; margin-bottom: 14px;">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 800;">Available Balance</div>
            <div style="font-size: 26px; font-weight: 900; color: <?= $user['coins'] < 0 ? 'var(--accent-red)' : 'var(--accent-green)' ?>; margin-top: 4px;">
                🪙 <?= number_format($user['coins'], 2) ?>
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
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 8px; text-transform: uppercase;">
                💚 Support Club Development
            </h3>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;">
                Contributions help fund server stability, club events, prize pools, and system upgrades.
            </p>

            <form method="post">
                <div class="form-group">
                    <label class="form-label">Donation Amount (Coins)</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="e.g. 5.00" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 14px; margin-top: 10px;">
                    Contribute Coins
                </button>
            </form>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
