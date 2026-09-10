<?php
/**
 * UNMOOR CLUB - ADMIN ACTIVITY LOGS
 */

require_once __DIR__ . "/guard.php";

$logs = $db->query("
    SELECT l.id, l.amount, l.note, l.created_at, u.name as user_name, u.phone as user_phone 
    FROM admin_balance_logs l
    JOIN users u ON u.id = l.user_id
    ORDER BY l.id DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Activity Logs';
$activeNav = 'activity_logs.php';
$pageSubtitle = 'Chronological log of administrative actions, balance updates, and events.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📝 System Activity Logs (<?= count($logs) ?>)</h2>
        <a href="logs.php" class="admin-btn admin-btn-secondary admin-btn-sm">
            All System Logs
        </a>
    </div>

    <?php if (empty($logs)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No activity logs recorded.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Target Member</th>
                        <th>Value Amount</th>
                        <th>Action Summary</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td>#<?= $l['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($l['user_name'] ?? 'User') ?></strong>
                                <div style="font-size: 11px; color: var(--admin-text-dim);">📱 <?= htmlspecialchars($l['user_phone']) ?></div>
                            </td>
                            <td>
                                <strong style="color: <?= $l['amount'] < 0 ? '#ef4444' : '#22c55e' ?>;">
                                    <?= $l['amount'] > 0 ? '+' : '' ?><?= number_format($l['amount'], 2) ?> UC
                                </strong>
                            </td>
                            <td><?= htmlspecialchars($l['note'] ?: '-') ?></td>
                            <td><span style="font-size: 12px; color: var(--admin-text-muted);"><?= date("d M Y • h:i A", strtotime($l['created_at'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
