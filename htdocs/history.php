<?php
/**
 * UNMOOR CLUB - TRANSACTION HISTORY / LEDGER
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

/* ================= FETCH LEDGER ================= */
$stmt = $db->prepare("
    SELECT amount, type,
           source, source_name, source_number,
           reference, created_at
    FROM coin_history
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 60
");
$stmt->execute([$uid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Ledger • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Account Ledger", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 12px; text-transform: uppercase;">
                📜 Transaction History
            </h3>

            <?php if ($rows): ?>
                <?php foreach ($rows as $r): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed var(--border-color);">
                        <div>
                            <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">
                                <?= htmlspecialchars(str_replace('_', ' ', $r['type'])) ?>
                            </div>
                            <div style="font-size: 13.5px; font-weight: 700; color: #ffffff; margin-top: 2px;">
                                <?php if (!empty($r['source_name'])): ?>
                                    <?= htmlspecialchars($r['source_name']) ?>
                                    <?php if (!empty($r['source_number'])): ?>
                                        <span style="font-size: 11.5px; color: var(--text-muted); font-weight: normal;">(<?= htmlspecialchars($r['source_number']) ?>)</span>
                                    <?php endif; ?>
                                <?php elseif (!empty($r['source'])): ?>
                                    <?= htmlspecialchars($r['source']) ?>
                                <?php elseif (!empty($r['reference'])): ?>
                                    <?= htmlspecialchars($r['reference']) ?>
                                <?php else: ?>
                                    System Transaction
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">
                                ⏱️ <?= date("d M Y, h:i A", strtotime($r['created_at'])) ?>
                            </div>
                        </div>

                        <div style="font-weight: 900; font-size: 15px; text-align: right; color: <?= $r['amount'] >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' ?>;">
                            <?= $r['amount'] >= 0 ? '+' : '−' ?>🪙<?= number_format(abs((float)$r['amount']), 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-muted); padding: 36px 0; font-size: 13.5px;">
                    No transactions recorded yet.
                </div>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
