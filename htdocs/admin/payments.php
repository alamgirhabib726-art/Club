<?php
/**
 * UNMOOR CLUB - ADMIN PAYMENTS MANAGEMENT
 */

require_once __DIR__ . "/guard.php";

/* ================= FILTER ================= */
$filterType = $_GET['type'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';

$where = "WHERE 1=1";
$params = [];

if ($filterType !== 'all') {
    $where .= " AND p.type = ?";
    $params[] = $filterType;
}

if ($filterStatus !== 'all') {
    $where .= " AND p.status = ?";
    $params[] = $filterStatus;
}

/* ================= FETCH PAYMENTS ================= */
$stmt = $db->prepare("
    SELECT
        p.id,
        p.user_id,
        p.type,
        p.amount,
        p.status,
        p.created_at,
        p.proof,
        p.method,
        p.source,
        u.name as user_name,
        u.phone as user_phone,
        u.coins as user_coins
    FROM payments p
    JOIN users u ON u.id = p.user_id
    $where
    ORDER BY p.id DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$pendingCount = 0;
$pendingSum = 0;
$approvedSum = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'pending') {
        $pendingCount++;
        $pendingSum += (float)$p['amount'];
    } elseif ($p['status'] === 'approved') {
        $approvedSum += (float)$p['amount'];
    }
}

$pageTitle = 'Payments Queue';
$activeNav = 'payments.php';
$pageSubtitle = 'Review, approve, and verify incoming member deposits, purchase orders, and VIP upgrades.';

require_once __DIR__ . "/layout_top.php";
?>

<?php if (isset($_GET['approved'])): ?>
    <div class="admin-alert admin-alert-success">
        <span>✅</span>
        <div>Payment has been approved and credited successfully.</div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['rejected'])): ?>
    <div class="admin-alert admin-alert-danger">
        <span>❌</span>
        <div>Payment has been marked as rejected.</div>
    </div>
<?php endif; ?>

<!-- FILTER & QUICK STATS -->
<div class="admin-card" style="padding: 18px 22px;">
    <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <label class="admin-label" style="margin: 0; align-self: center;">Filter:</label>
            <select name="type" class="admin-select" style="max-width: 150px;">
                <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="deposit" <?= $filterType === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                <option value="apply" <?= $filterType === 'apply' ? 'selected' : '' ?>>Application</option>
                <option value="premium" <?= $filterType === 'premium' ? 'selected' : '' ?>>VIP / Premium</option>
                <option value="purchase" <?= $filterType === 'purchase' ? 'selected' : '' ?>>Purchase</option>
                <option value="donation" <?= $filterType === 'donation' ? 'selected' : '' ?>>Donation</option>
            </select>

            <select name="status" class="admin-select" style="max-width: 150px;">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Only</option>
                <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>

            <button type="submit" class="admin-btn admin-btn-primary">Apply</button>
            <?php if ($filterType !== 'all' || $filterStatus !== 'all'): ?>
                <a href="payments.php" class="admin-btn admin-btn-secondary">Clear</a>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <span class="admin-badge admin-badge-warning"><?= $pendingCount ?> Pending (৳ <?= number_format($pendingSum, 2) ?>)</span>
            <span class="admin-badge admin-badge-success">Approved: ৳ <?= number_format($approvedSum, 2) ?></span>
        </div>
    </form>
</div>

<!-- PAYMENTS LIST -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">💳 Transactions Records (<?= count($payments) ?>)</h2>
    </div>

    <?php if (empty($payments)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No payment records found.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Method / Proof</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td>
                                <strong style="color: #ffffff;">#<?= $p['id'] ?></strong>
                            </td>
                            <td>
                                <strong style="color: #ffffff;"><?= htmlspecialchars($p['user_name']) ?></strong>
                                <div style="font-size: 12px; color: var(--admin-text-dim);">📱 <?= htmlspecialchars($p['user_phone']) ?></div>
                                <div style="font-size: 11px; color: var(--admin-gold);">🪙 <?= number_format($p['user_coins'], 2) ?> UC</div>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-info" style="text-transform: uppercase;">
                                    <?= htmlspecialchars($p['type']) ?>
                                </span>
                                <?php if (!empty($p['source'])): ?>
                                    <div style="font-size: 11px; color: var(--admin-text-dim); margin-top: 2px;">
                                        <?= htmlspecialchars($p['source']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-size: 15px; color: #ffffff;">৳ <?= number_format($p['amount'], 2) ?></strong>
                            </td>
                            <td>
                                <?php if (!empty($p['method'])): ?>
                                    <div style="font-size: 12px; font-weight: 700; color: var(--admin-text-muted);">
                                        <?= htmlspecialchars(strtoupper($p['method'])) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($p['proof'])): ?>
                                    <div style="margin-top: 4px;">
                                        <a href="../uploads/<?= htmlspecialchars($p['proof']) ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-secondary" style="font-size: 11px; padding: 3px 8px;">
                                            🖼️ View Proof
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--admin-text-dim);">No proof file</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $st = strtolower($p['status']);
                                    $badge = ($st === 'approved') ? 'admin-badge-success' : (($st === 'pending') ? 'admin-badge-warning' : 'admin-badge-danger');
                                ?>
                                <span class="admin-badge <?= $badge ?>"><?= strtoupper($st) ?></span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--admin-text-muted);">
                                    <?= date("d M Y • h:i A", strtotime($p['created_at'])) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <div style="display: inline-flex; gap: 6px;">
                                        <form method="post" action="approve_payment.php" style="display:inline;" data-confirm="Approve payment of ৳<?= number_format($p['amount'], 2) ?> for <?= htmlspecialchars($p['user_name']) ?>?" data-confirm-title="Approve Deposit Payment" data-confirm-btn="Yes, Approve Payment">
                                            <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-success">
                                                ✅ Approve
                                            </button>
                                        </form>

                                        <a href="reject.php?id=<?= $p['id'] ?>" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Reject deposit payment #<?= $p['id'] ?>?" data-confirm-title="Reject Payment" data-confirm-danger="true" data-confirm-btn="Yes, Reject">
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
