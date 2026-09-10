<?php
/**
 * UNMOOR CLUB - ADMIN DONATIONS HISTORY
 */

require_once __DIR__ . "/guard.php";

/* FETCH DONATIONS */
$stmt = $db->prepare("
    SELECT 
        p.id,
        p.amount,
        p.status,
        p.created_at,
        p.method,
        u.name as user_name,
        u.phone as user_phone
    FROM payments p
    JOIN users u ON u.id = p.user_id
    WHERE p.type = 'donation'
    ORDER BY p.id DESC
");
$stmt->execute();
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDonationsSum = 0;
foreach ($donations as $d) {
    if ($d['status'] === 'approved') {
        $totalDonationsSum += (float)$d['amount'];
    }
}

$pageTitle = 'Donations';
$activeNav = 'donations.php';
$pageSubtitle = 'Community and member voluntary club fund contributions.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Total Approved Donations</span>
            <div class="admin-stat-icon" style="color: #22c55e;">💚</div>
        </div>
        <div class="admin-stat-value">৳ <?= number_format($totalDonationsSum, 2) ?></div>
        <div class="admin-stat-subtext">
            <span><?= count($donations) ?> donation contributions recorded</span>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">💚 Donation Records (<?= count($donations) ?>)</h2>
    </div>

    <?php if (empty($donations)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No donations recorded yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Donor Name</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $d): ?>
                        <tr>
                            <td>#<?= $d['id'] ?></td>
                            <td><strong><?= htmlspecialchars($d['user_name']) ?></strong></td>
                            <td><span style="color: var(--admin-text-dim);"><?= htmlspecialchars($d['user_phone']) ?></span></td>
                            <td><strong style="color: #22c55e;">৳ <?= number_format($d['amount'], 2) ?></strong></td>
                            <td>
                                <?php
                                    $st = strtolower($d['status']);
                                    $badge = ($st === 'approved') ? 'admin-badge-success' : (($st === 'pending') ? 'admin-badge-warning' : 'admin-badge-danger');
                                ?>
                                <span class="admin-badge <?= $badge ?>"><?= strtoupper($st) ?></span>
                            </td>
                            <td><span style="font-size: 12px; color: var(--admin-text-muted);"><?= date("d M Y • h:i A", strtotime($d['created_at'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
