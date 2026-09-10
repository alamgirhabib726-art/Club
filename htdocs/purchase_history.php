<?php
/**
 * UNMOOR CLUB - PURCHASE HISTORY
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

/* FETCH USER */
$stmt = $db->prepare("
    SELECT status, apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$user ||
    ($user['status'] !== 'active' && $user['status'] !== 'premium') ||
    $user['apply_status'] !== 'approved'
) {
    die("ACCESS DENIED");
}

/* FETCH PURCHASE HISTORY */
$stmt = $db->prepare("
    SELECT source, amount, status, created_at
    FROM payments
    WHERE user_id = ?
      AND type = 'purchase'
    ORDER BY id DESC
");
$stmt->execute([$uid]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* PRODUCT LABELS */
$labels = [
    'power' => '⚡ Power Click',
    'joint' => '🤝 Joint Click',
    'paper' => '📄 Paper Click'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase History • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Purchase History", "/purchase.php") ?>

        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 12px; text-transform: uppercase;">
                📜 Store Orders
            </h3>

            <?php if ($orders): ?>
                <?php foreach ($orders as $o): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--border-color);">
                        <div>
                            <div style="font-size: 14px; font-weight: 800; color: #ffffff;">
                                <?= htmlspecialchars($labels[$o['source']] ?? ucfirst($o['source'])) ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-dim); margin-top: 3px;">
                                <?= date("d M Y • h:i A", strtotime($o['created_at'])) ?>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <div style="font-weight: 900; font-size: 14px; color: var(--accent-gold);">
                                🪙 <?= number_format($o['amount'], 2) ?>
                            </div>
                            <div style="margin-top: 4px;">
                                <?php if ($o['status'] === 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php elseif ($o['status'] === 'rejected'): ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-muted); padding: 36px 0; font-size: 13.5px;">
                    No store purchases made yet.
                </div>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
