<?php
/**
 * UNMOOR CLUB - PURCHASE STORE
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
    SELECT id, name, phone, coins, purchase_balance, status, apply_status, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['status'] !== 'active' && $user['status'] !== 'premium') || $user['apply_status'] !== 'approved' || $user['role'] === 'system') {
    die("ACCESS DENIED");
}

/* ================= PRODUCTS ================= */
$products = [
    'power' => [
        'name'  => 'Power Click',
        'coins' => 2000,
        'tag'   => 'Popular'
    ],
    'joint' => [
        'name'  => 'Joint Click',
        'coins' => 1000,
        'tag'   => 'Standard'
    ],
    'paper' => [
        'name'  => 'Paper Click',
        'coins' => 500,
        'tag'   => 'Starter'
    ]
];

$msg = '';
$error = '';

/* ================= HANDLE PURCHASE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_key'])) {

    $key = $_POST['product_key'] ?? '';

    if (!isset($products[$key])) {
        $error = "Invalid product selected";
    } else {

        $p = $products[$key];
        $need = (float)$p['coins'];

        if ((float)$user['coins'] < $need) {
            $error = "Insufficient coins. Required: 🪙" . number_format($need, 2);
        } else {

            $db->beginTransaction();
            try {

                $stmt = $db->prepare("
                    UPDATE users
                    SET coins = coins - ?
                    WHERE id = ? AND coins >= ?
                ");
                $stmt->execute([$need, $uid, $need]);

                if ($stmt->rowCount() !== 1) {
                    throw new Exception("INSUFFICIENT_FUNDS");
                }

                $db->prepare("
                    INSERT INTO payments
                    (user_id, type, amount, status, source, created_at)
                    VALUES (?, 'purchase', ?, 'pending', ?, NOW())
                ")->execute([
                    $uid,
                    $need,
                    $key
                ]);

                $db->prepare("
                    INSERT INTO coin_history
                    (user_id, amount, type, source_name, created_at)
                    VALUES (?, ?, 'purchase', ?, NOW())
                ")->execute([
                    $uid,
                    -$need,
                    $p['name']
                ]);

                $db->commit();
                $msg = "✅ Order submitted for " . htmlspecialchars($p['name']) . "! Awaiting fulfillment.";
                $user['coins'] -= $need;

            } catch (Exception $e) {
                $db->rollBack();
                $error = "Purchase failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Store • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Club Store", "/dashboard.php") ?>

        <!-- CURRENT BALANCE -->
        <div class="card" style="text-align: center; padding: 18px 14px; margin-bottom: 14px;">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 800;">Available Coin Balance</div>
            <div style="font-size: 26px; font-weight: 900; color: <?= $user['coins'] < 0 ? 'var(--accent-red)' : 'var(--accent-green)' ?>; margin-top: 4px;">
                🪙 <?= number_format($user['coins'], 2) ?>
            </div>
            
            <div style="display: flex; gap: 8px; justify-content: center; margin-top: 12px;">
                <a href="purchase_history.php" class="btn btn-secondary btn-sm">
                    📜 Order History
                </a>
                <a href="convert.php" class="btn btn-secondary btn-sm">
                    🔁 Convert Coins
                </a>
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

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($products as $k => $p): ?>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <div style="font-size: 11px; font-weight: 800; color: var(--accent-gold); text-transform: uppercase; margin-bottom: 2px;">
                                <?= htmlspecialchars($p['tag']) ?>
                            </div>
                            <h3 style="font-size: 16px; font-weight: 900; color: #ffffff; margin: 0;">
                                📦 <?= htmlspecialchars($p['name']) ?>
                            </h3>
                        </div>
                        <div style="font-size: 18px; font-weight: 900; color: var(--accent-gold);">
                            🪙 <?= number_format($p['coins']) ?>
                        </div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="product_key" value="<?= htmlspecialchars($k) ?>">
                        <button type="submit" class="btn btn-primary btn-block" style="padding: 12px;" onclick="return confirm('Confirm purchase of <?= htmlspecialchars($p['name']) ?> for 🪙<?= number_format($p['coins']) ?>?');">
                            Order Now
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
