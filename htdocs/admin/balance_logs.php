<?php
/**
 * UNMOOR CLUB - ADMIN BALANCE ADJUSTMENT LOGS
 */

require_once __DIR__ . "/guard.php";

$rows = $db->query("
    SELECT
        l.id,
        l.amount,
        l.note,
        l.created_at,
        u.name as user_name,
        u.phone as user_phone
    FROM admin_balance_logs l
    JOIN users u ON u.id = l.user_id
    ORDER BY l.id DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Balance Logs';
$activeNav = 'balance_logs.php';
$pageSubtitle = 'Audit log of all manual administrative coin injections and deductions.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">💳 Manual Balance Adjustment Logs (<?= count($rows) ?>)</h2>
        <a href="users.php" class="admin-btn admin-btn-primary admin-btn-sm">
            👥 Users Directory
        </a>
    </div>

    <?php if (empty($rows)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No balance logs recorded yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Member Details</th>
                        <th>Adjustment Amount</th>
                        <th>Audit Note</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($r['user_name'] ?? 'User') ?></strong>
                                <div style="font-size: 11px; color: var(--admin-text-dim);">📱 <?= htmlspecialchars($r['user_phone']) ?></div>
                            </td>
                            <td>
                                <strong style="color: <?= $r['amount'] < 0 ? '#ef4444' : '#22c55e' ?>; font-size: 14px;">
                                    <?= $r['amount'] > 0 ? '+' : '' ?><?= number_format($r['amount'], 2) ?> UC
                                </strong>
                            </td>
                            <td>
                                <span style="color: var(--admin-text); font-size: 13px;">
                                    <?= htmlspecialchars($r['note'] ?: 'No memo') ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--admin-text-muted);">
                                    <?= date("d M Y • h:i A", strtotime($r['created_at'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
