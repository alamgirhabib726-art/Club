<?php
/**
 * UNMOOR CLUB - ADMIN PURCHASE ORDERS MANAGEMENT
 */

require_once __DIR__ . "/guard.php";

/* FETCH PURCHASE ORDERS */
$stmt = $db->prepare("
    SELECT 
        p.id,
        p.amount,
        p.status,
        p.created_at,
        p.method,
        p.source,
        u.name as user_name,
        u.phone as user_phone,
        u.coins as user_coins
    FROM payments p
    JOIN users u ON u.id = p.user_id
    WHERE p.type = 'purchase'
    ORDER BY p.id DESC
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Purchase Orders';
$activeNav = 'purchase_orders.php';
$pageSubtitle = 'Orders placed by members purchasing products with coin balance.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">🛍️ Purchase Orders Queue (<?= count($orders) ?>)</h2>
        <a href="products.php" class="admin-btn admin-btn-secondary admin-btn-sm">
            📦 Products Catalog
        </a>
    </div>

    <?php if (empty($orders)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No purchase orders received yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Member</th>
                        <th>Amount / Cost</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><strong style="color: #ffffff;">#<?= $o['id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($o['user_name']) ?></strong>
                                <div style="font-size: 11px; color: var(--admin-text-dim);">📱 <?= htmlspecialchars($o['user_phone']) ?></div>
                            </td>
                            <td>
                                <strong style="color: var(--admin-gold);">🪙 <?= number_format($o['amount'], 2) ?></strong>
                            </td>
                            <td>
                                <?php
                                    $st = strtolower($o['status']);
                                    $badge = ($st === 'approved') ? 'admin-badge-success' : (($st === 'pending') ? 'admin-badge-warning' : 'admin-badge-danger');
                                ?>
                                <span class="admin-badge <?= $badge ?>"><?= strtoupper($st) ?></span>
                            </td>
                            <td><span style="font-size: 12px; color: var(--admin-text-muted);"><?= date("d M Y • h:i A", strtotime($o['created_at'])) ?></span></td>
                            <td style="text-align: right;">
                                <?php if ($o['status'] === 'pending'): ?>
                                    <div style="display: inline-flex; gap: 6px;">
                                        <a class="admin-btn admin-btn-sm admin-btn-success" href="purchase_action.php?id=<?= $o['id'] ?>&action=approve" data-confirm="Approve and fulfill purchase order #<?= $o['id'] ?> for <?= htmlspecialchars($o['user_name'] ?? 'User') ?>?" data-confirm-title="Approve Order" data-confirm-btn="Yes, Approve Order">
                                            ✅ Approve
                                        </a>
                                        <a class="admin-btn admin-btn-sm admin-btn-danger" href="purchase_action.php?id=<?= $o['id'] ?>&action=reject" data-confirm="Reject purchase order #<?= $o['id'] ?> and refund locked funds?" data-confirm-title="Reject Order" data-confirm-danger="true" data-confirm-btn="Yes, Reject Order">
                                            ❌ Reject
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--admin-text-dim);">Processed</span>
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
