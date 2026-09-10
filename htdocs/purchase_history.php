<?php
/**
 * UNMOOR CLUB - PURCHASE HISTORY & ORDER TRACKING
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* LOGIN CHECK */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$balances = get_user_balances($db, $uid);

if (
    ($balances['status'] !== 'active' && $balances['status'] !== 'premium') ||
    $balances['apply_status'] !== 'approved'
) {
    die("ACCESS DENIED");
}

/* FETCH PURCHASE HISTORY */
$stmt = $db->prepare("
    SELECT id, source, amount, status, created_at
    FROM payments
    WHERE user_id = ?
      AND type = 'purchase'
    ORDER BY id DESC
");
$stmt->execute([$uid]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* PRODUCT LABELS & ICONS */
$labels = [
    'power' => ['name' => 'Power Click', 'icon' => '⚡'],
    'joint' => ['name' => 'Joint Click', 'icon' => '🤝'],
    'paper' => ['name' => 'Paper Click', 'icon' => '📄']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Status &amp; History • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Order Status", "/purchase.php") ?>

        <!-- BALANCE OVERVIEW CARD -->
        <?= render_balance_card($balances, false) ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">
                    📜 Store Orders (<?= count($orders) ?>)
                </h3>
                <a href="purchase.php" class="btn btn-gold btn-sm" style="text-decoration: none; padding: 6px 12px; font-size: 12px;">
                    + New Order
                </a>
            </div>

            <?php if ($orders): ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($orders as $o): ?>
                        <?php 
                            $info = $labels[$o['source']] ?? ['name' => ucfirst($o['source']), 'icon' => '📦'];
                            $status = strtolower($o['status']);
                        ?>
                        <div style="background: var(--bg-dark); border: 1px solid <?= $status === 'pending' ? 'rgba(245, 158, 11, 0.4)' : 'var(--border-color)' ?>; border-radius: var(--radius-md); padding: 14px; position: relative;">
                            
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 22px;"><?= $info['icon'] ?></span>
                                    <div>
                                        <div style="font-size: 14.5px; font-weight: 800; color: #ffffff;">
                                            <?= htmlspecialchars($info['name']) ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">
                                            Order #<?= $o['id'] ?> • <?= date("d M Y, h:i A", strtotime($o['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="text-align: right;">
                                    <div style="font-size: 16px; font-weight: 900; color: var(--accent-gold);">
                                        🪙 <?= number_format($o['amount'], 2) ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted);">
                                        <?= $status === 'pending' ? 'Locked' : 'Order Cost' ?>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
                                <div>
                                    <?php if ($status === 'pending'): ?>
                                        <span style="color: #fbbf24; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                            <span>🔒</span> Coins Locked — Waiting for Admin Approval
                                        </span>
                                    <?php elseif ($status === 'approved'): ?>
                                        <span style="color: #22c55e; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                            <span>✅</span> Approved &amp; Fulfilled
                                        </span>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <span style="color: #ef4444; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                                            <span>❌</span> Rejected (Coins Released to Available)
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-weight: 700;">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <?php if ($status === 'pending'): ?>
                                        <span class="badge badge-warning">PENDING</span>
                                    <?php elseif ($status === 'approved'): ?>
                                        <span class="badge badge-success">COMPLETED</span>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <span class="badge badge-danger">REJECTED</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending"><?= strtoupper($status) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-muted); padding: 36px 0; font-size: 13.5px;">
                    No store orders placed yet.
                </div>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
