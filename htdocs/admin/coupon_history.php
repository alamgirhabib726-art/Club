<?php
/**
 * UNMOOR CLUB - ADMIN COUPON REDEMPTION AUDIT & HISTORY
 */

require_once __DIR__ . "/guard.php";

/* ================= FETCH COUPON HISTORY ================= */
$history = $db->query("
    SELECT
        c.id,
        c.code,
        c.amount,
        c.type,
        c.status,
        c.created_at,
        c.used_at,
        u.name as used_by_name,
        u.phone as used_by_phone
    FROM coupons c
    LEFT JOIN users u ON u.id = c.used_by
    ORDER BY c.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Coupon History';
$activeNav = 'coupon_history.php';
$pageSubtitle = 'Full audit trail of all generated, active, and redeemed prepaid vouchers.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📜 Complete Voucher Logs (<?= count($history) ?>)</h2>
        <a href="coupons.php" class="admin-btn admin-btn-primary admin-btn-sm">
            ⚡ Generate Coupon
        </a>
    </div>

    <?php if (empty($history)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No coupons logged in history.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Coupon Code</th>
                        <th>Type</th>
                        <th>Value Amount</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Redeemed By</th>
                        <th>Redemption Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $c): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--admin-gold); font-family: monospace; font-size: 14px;">
                                    <?= htmlspecialchars($c['code']) ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($c['type'] === 'apply'): ?>
                                    <span class="admin-badge admin-badge-info">REGISTRATION</span>
                                <?php else: ?>
                                    <span class="admin-badge" style="background: rgba(168, 85, 247, 0.2); color: #c084fc;">DEPOSIT</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #ffffff;">🪙 <?= number_format($c['amount'], 2) ?> Coins</strong>
                            </td>
                            <td>
                                <?php if ($c['status'] === 'used'): ?>
                                    <span class="admin-badge admin-badge-success">REDEEMED</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-warning">UNUSED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--admin-text-muted);">
                                    <?= date("d M Y", strtotime($c['created_at'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($c['used_by_name'])): ?>
                                    <strong><?= htmlspecialchars($c['used_by_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--admin-text-dim);"><?= htmlspecialchars($c['used_by_phone'] ?? '') ?></div>
                                <?php else: ?>
                                    <span style="color: var(--admin-text-dim); font-size: 12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['used_at'])): ?>
                                    <span style="font-size: 12px; color: var(--admin-text-muted);"><?= date("d M Y • h:i A", strtotime($c['used_at'])) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--admin-text-dim); font-size: 12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
