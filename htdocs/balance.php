<?php
/**
 * UNMOOR CLUB - BALANCE AUDIT LOG
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT amount,
           COALESCE(action, 'credit') AS action,
           note,
           created_at
    FROM admin_balance_logs
    WHERE user_id = ?
    ORDER BY id DESC
");
$stmt->execute([$uid]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Balance History • Unmoor Club</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Balance Audit History", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 12px; text-transform: uppercase;">
                💰 Balance Modifications
            </h3>

            <?php if (!$logs): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 36px 0; font-size: 13.5px;">
                    No manual balance adjustment records found.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($logs as $l): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed var(--border-color);">
                            <div>
                                <span class="badge <?= $l['action'] === 'debit' ? 'badge-danger' : 'badge-success' ?>">
                                    <?= strtoupper($l['action']) ?>
                                </span>
                                <div style="font-size: 13px; color: #ffffff; margin-top: 4px;">
                                    <?= htmlspecialchars($l['note'] ?: 'Balance adjustment') ?>
                                </div>
                                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">
                                    <?= date("d M Y, h:i A", strtotime($l['created_at'])) ?>
                                </div>
                            </div>
                            <div style="font-size: 15px; font-weight: 900; color: <?= $l['action'] === 'debit' ? 'var(--accent-red)' : 'var(--accent-green)' ?>;">
                                <?= $l['action'] === 'debit' ? '−' : '+' ?>৳<?= number_format($l['amount'], 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
