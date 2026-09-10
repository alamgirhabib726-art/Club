<?php
/**
 * UNMOOR CLUB - ADMIN SYSTEM AUDIT LOGS
 */

require_once __DIR__ . "/guard.php";

$logs = $db->query("
    SELECT l.id, l.user_id, l.action, l.created_at, u.name as user_name, u.phone as user_phone
    FROM logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.id DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'System Logs';
$activeNav = 'logs.php';
$pageSubtitle = 'Security audit log and administrative event tracking.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📋 Security &amp; Administrative Event Logs (<?= count($logs) ?>)</h2>
        <a href="balance_logs.php" class="admin-btn admin-btn-secondary admin-btn-sm">
            💳 Balance Logs
        </a>
    </div>

    <?php if (empty($logs)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No system logs recorded yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Actor / User</th>
                        <th>Action Logged</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td>#<?= $l['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($l['user_name'] ?? 'System') ?></strong>
                                <?php if (!empty($l['user_phone'])): ?>
                                    <div style="font-size: 11px; color: var(--admin-text-dim);">📱 <?= htmlspecialchars($l['user_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #ffffff;"><?= htmlspecialchars($l['action']) ?></strong>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--admin-text-muted);">
                                    <?= date("d M Y • h:i A", strtotime($l['created_at'])) ?>
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
