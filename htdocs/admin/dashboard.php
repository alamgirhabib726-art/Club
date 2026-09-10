<?php
/**
 * UNMOOR CLUB - ADMIN OPERATIONAL DASHBOARD
 * Displays real-time operational statistics, financial summaries, and recent activity from the database.
 */

require_once __DIR__ . "/guard.php";

/* =========================================
   REAL DATABASE METRICS CALCULATION
   ========================================= */

// 1. User Statistics
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role != 'system'")->fetchColumn();
$activeUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND apply_status = 'approved' AND role != 'system'")->fetchColumn();
$pendingUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE apply_status = 'pending' AND role != 'system'")->fetchColumn();
$bannedUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'banned'")->fetchColumn();
$vipUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'premium' OR role = 'premium'")->fetchColumn();

// 2. Financial & Balance Metrics
$userCoinPool = (float)$db->query("SELECT COALESCE(SUM(coins), 0) FROM users WHERE role != 'system'")->fetchColumn();
$userBalancePool = (float)$db->query("SELECT COALESCE(SUM(balance), 0) FROM users WHERE role != 'system'")->fetchColumn();

// 3. Payment Metrics
$pendingPaymentsCount = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$pendingPaymentsSum = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'pending'")->fetchColumn();
$approvedPaymentsCount = (int)$db->query("SELECT COUNT(*) FROM payments WHERE status = 'approved'")->fetchColumn();
$approvedPaymentsSum = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'approved'")->fetchColumn();

// 4. Events & Promotions
$totalEvents = (int)$db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$activeEvents = (int)$db->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn();
$eventParticipants = (int)$db->query("SELECT COUNT(*) FROM event_participants")->fetchColumn();

$activeCoupons = (int)$db->query("SELECT COUNT(*) FROM coupons WHERE status = 'active' AND (used_by IS NULL OR used_by = 0)")->fetchColumn();
$usedCoupons = (int)$db->query("SELECT COUNT(*) FROM coupons WHERE status = 'used' OR (used_by IS NOT NULL AND used_by != 0)")->fetchColumn();

// 5. System Status
$totalNotices = (int)$db->query("SELECT COUNT(*) FROM notices")->fetchColumn();
$maintStmt = $db->query("SELECT maintenance FROM settings WHERE id = 1 LIMIT 1");
$maintVal = $maintStmt ? (int)$maintStmt->fetchColumn() : 0;
$maintenanceActive = ($maintVal === 1);

// 6. Recent Pending / Approved Payments Feed
$recentPayments = $db->query("
    SELECT p.id, p.user_id, p.type, p.amount, p.method, p.source, p.status, p.created_at,
           u.name as user_name, u.phone as user_phone
    FROM payments p
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY p.id DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// 7. Recent User Registrations Feed
$recentUsers = $db->query("
    SELECT id, name, phone, status, apply_status, coins, role, created_at
    FROM users
    WHERE role != 'system'
    ORDER BY id DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// 8. Recent Logs
$recentLogs = $db->query("
    SELECT l.id, l.user_id, l.action, l.created_at, u.name as user_name
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Operational Dashboard';
$activeNav = 'dashboard.php';
$pageSubtitle = 'Real-time database metrics, user health, financial ledger, and pending approvals.';

require_once __DIR__ . "/layout_top.php";
?>

<!-- SYSTEM STATUS & WARNINGS BANNER -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin-bottom: 24px;">
    <div style="background: linear-gradient(135deg, #0f172a, #020617); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-md); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 20px;">⚙️</span>
            <div>
                <div style="font-size: 13px; font-weight: 800; color: #ffffff;">System Engine</div>
                <div style="font-size: 11px; color: var(--admin-text-muted);">Database: Connected • PHP <?= PHP_VERSION ?></div>
            </div>
        </div>
        <span class="admin-badge admin-badge-success">OPERATIONAL</span>
    </div>

    <div style="background: linear-gradient(135deg, #0f172a, #020617); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-md); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 20px;">🛠️</span>
            <div>
                <div style="font-size: 13px; font-weight: 800; color: #ffffff;">Maintenance Mode</div>
                <div style="font-size: 11px; color: var(--admin-text-muted);">Global member lock status</div>
            </div>
        </div>
        <?php if ($maintenanceActive): ?>
            <span class="admin-badge admin-badge-danger">ENABLED</span>
        <?php else: ?>
            <span class="admin-badge admin-badge-success">DISABLED (NORMAL)</span>
        <?php endif; ?>
    </div>

    <?php if ($pendingPaymentsCount > 0 || $pendingUsers > 0): ?>
        <div style="background: linear-gradient(135deg, rgba(250, 204, 21, 0.1), rgba(245, 158, 11, 0.05)); border: 1px solid rgba(250, 204, 21, 0.3); border-radius: var(--admin-radius-md); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">⚡</span>
                <div>
                    <div style="font-size: 13px; font-weight: 800; color: var(--admin-gold);">Action Required</div>
                    <div style="font-size: 11px; color: var(--admin-text-muted);"><?= $pendingPaymentsCount ?> payments &bull; <?= $pendingUsers ?> user applicants</div>
                </div>
            </div>
            <a href="payments.php" class="admin-btn admin-btn-sm admin-btn-primary">Review</a>
        </div>
    <?php endif; ?>
</div>

<!-- STATS BENTO GRID -->
<div class="admin-stats-grid">
    <!-- Total Users -->
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Total Members</span>
            <div class="admin-stat-icon" style="color: #38bdf8;">👥</div>
        </div>
        <div class="admin-stat-value"><?= number_format($totalUsers) ?></div>
        <div class="admin-stat-subtext">
            <span class="admin-badge admin-badge-success" style="font-size: 10px;"><?= number_format($activeUsers) ?> Active</span>
            <?php if ($pendingUsers > 0): ?>
                <span class="admin-badge admin-badge-warning" style="font-size: 10px;"><?= $pendingUsers ?> Pending</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Coin Circulation Pool -->
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Circulating Coins</span>
            <div class="admin-stat-icon" style="color: #facc15;">🪙</div>
        </div>
        <div class="admin-stat-value"><?= number_format($userCoinPool, 2) ?></div>
        <div class="admin-stat-subtext">
            <span style="color: var(--admin-text-muted);">Total Member Coin Balances</span>
        </div>
    </div>

    <!-- Pending Payments -->
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Pending Payments</span>
            <div class="admin-stat-icon" style="color: #f59e0b;">⏳</div>
        </div>
        <div class="admin-stat-value"><?= number_format($pendingPaymentsCount) ?></div>
        <div class="admin-stat-subtext">
            <span style="color: var(--admin-gold); font-weight: 700;">৳ <?= number_format($pendingPaymentsSum, 2) ?></span>
            <span style="color: var(--admin-text-muted);">awaiting review</span>
        </div>
    </div>

    <!-- Approved Volume -->
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Approved Volume</span>
            <div class="admin-stat-icon" style="color: #22c55e;">💰</div>
        </div>
        <div class="admin-stat-value">৳ <?= number_format($approvedPaymentsSum, 2) ?></div>
        <div class="admin-stat-subtext">
            <span style="color: var(--admin-text-muted);"><?= number_format($approvedPaymentsCount) ?> verified deposits/purchases</span>
        </div>
    </div>
</div>

<!-- SECOND ROW OF METRIC CARDS -->
<div class="admin-stats-grid">
    <!-- Active Events -->
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Events</span>
            <div class="admin-stat-icon" style="color: #ec4899;">🎉</div>
        </div>
        <div class="admin-stat-value"><?= $activeEvents ?> / <?= $totalEvents ?></div>
        <div class="admin-stat-subtext">
            <span style="color: var(--admin-text-muted);"><?= $eventParticipants ?> total participants</span>
        </div>
    </div>

    <!-- Active Coupons -->
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Coupons</span>
            <div class="admin-stat-icon" style="color: #a855f7;">🎟️</div>
        </div>
        <div class="admin-stat-value"><?= $activeCoupons ?> Active</div>
        <div class="admin-stat-subtext">
            <span style="color: var(--admin-text-muted);"><?= $usedCoupons ?> redeemed</span>
        </div>
    </div>

    <!-- Notices Broadcast -->
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Broadcast Notices</span>
            <div class="admin-stat-icon" style="color: #38bdf8;">📢</div>
        </div>
        <div class="admin-stat-value"><?= $totalNotices ?></div>
        <div class="admin-stat-subtext">
            <a href="notices.php" style="color: var(--admin-accent); font-weight: 700;">Manage notices &rarr;</a>
        </div>
    </div>

    <!-- VIP / Premium -->
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">VIP & Premium</span>
            <div class="admin-stat-icon" style="color: #facc15;">👑</div>
        </div>
        <div class="admin-stat-value"><?= $vipUsers ?></div>
        <div class="admin-stat-subtext">
            <span style="color: var(--admin-text-muted);"><?= $bannedUsers ?> banned accounts</span>
        </div>
    </div>
</div>

<!-- QUICK ACTION SHORTCUTS -->
<div class="admin-card" style="padding: 16px 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h3 class="admin-card-title">⚡ Quick Management Actions</h3>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="users.php" class="admin-btn admin-btn-secondary admin-btn-sm">👥 All Users</a>
        <a href="payments.php" class="admin-btn admin-btn-secondary admin-btn-sm">💳 Payments Queue</a>
        <a href="purchase_orders.php" class="admin-btn admin-btn-secondary admin-btn-sm">🛍️ Purchase Orders</a>
        <a href="notices.php" class="admin-btn admin-btn-secondary admin-btn-sm">📢 Post Notice</a>
        <a href="events.php" class="admin-btn admin-btn-secondary admin-btn-sm">🎉 Manage Events</a>
        <a href="coupons.php" class="admin-btn admin-btn-secondary admin-btn-sm">🎟️ Create Coupon</a>
        <a href="system_ledger.php" class="admin-btn admin-btn-secondary admin-btn-sm">📊 System Ledger</a>
        <a href="maintenance.php" class="admin-btn admin-btn-secondary admin-btn-sm">🛠️ Maintenance Switch</a>
    </div>
</div>

<!-- DUAL COLUMN DATA TABLES -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 24px;">

    <!-- Recent Payments Queue -->
    <div class="admin-card" style="margin-bottom: 0;">
        <div class="admin-card-header">
            <h2 class="admin-card-title">💳 Recent Payments</h2>
            <a href="payments.php" class="admin-btn admin-btn-sm admin-btn-secondary">View All &rarr;</a>
        </div>

        <?php if (empty($recentPayments)): ?>
            <p style="color: var(--admin-text-muted); text-align: center; padding: 24px 0;">No payment records found.</p>
        <?php else: ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayments as $p): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($p['user_name'] ?? 'User #'.$p['user_id']) ?></strong>
                                    <div style="font-size: 11px; color: var(--admin-text-dim);"><?= htmlspecialchars($p['user_phone'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 700; text-transform: capitalize;">
                                        <?= htmlspecialchars($p['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: #ffffff;">৳ <?= number_format($p['amount'], 2) ?></strong>
                                </td>
                                <td>
                                    <?php
                                        $s = strtolower((string)$p['status']);
                                        $badgeClass = ($s === 'approved') ? 'admin-badge-success' : (($s === 'pending') ? 'admin-badge-warning' : 'admin-badge-danger');
                                    ?>
                                    <span class="admin-badge <?= $badgeClass ?>"><?= strtoupper($s) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Member Registrations -->
    <div class="admin-card" style="margin-bottom: 0;">
        <div class="admin-card-header">
            <h2 class="admin-card-title">👥 Recent Members</h2>
            <a href="users.php" class="admin-btn admin-btn-sm admin-btn-secondary">View All &rarr;</a>
        </div>

        <?php if (empty($recentUsers)): ?>
            <p style="color: var(--admin-text-muted); text-align: center; padding: 24px 0;">No members found.</p>
        <?php else: ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Coins</th>
                            <th>Status</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($u['name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--admin-text-dim);"><?= htmlspecialchars($u['phone']) ?></div>
                                </td>
                                <td>
                                    <span style="color: var(--admin-gold); font-weight: 800;">🪙 <?= number_format($u['coins'], 2) ?></span>
                                </td>
                                <td>
                                    <?php
                                        $ustatus = ($u['apply_status'] === 'approved' && $u['status'] === 'active') ? 'active' : $u['apply_status'];
                                        $badge = ($ustatus === 'active') ? 'admin-badge-success' : (($ustatus === 'pending') ? 'admin-badge-warning' : 'admin-badge-danger');
                                    ?>
                                    <span class="admin-badge <?= $badge ?>"><?= strtoupper($ustatus) ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 11.5px; color: var(--admin-text-muted);">
                                        <?= date("d M Y", strtotime($u['created_at'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- RECENT AUDIT LOGS -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📝 Recent System &amp; Audit Logs</h2>
        <a href="logs.php" class="admin-btn admin-btn-sm admin-btn-secondary">All Logs &rarr;</a>
    </div>

    <?php if (empty($recentLogs)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 18px 0;">No recent audit logs recorded.</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <?php foreach ($recentLogs as $log): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-sm); font-size: 13px;">
                    <div>
                        <strong style="color: #ffffff;"><?= htmlspecialchars($log['user_name'] ?? 'System') ?>:</strong>
                        <span style="color: var(--admin-text-muted); margin-left: 6px;"><?= htmlspecialchars($log['action']) ?></span>
                    </div>
                    <span style="font-size: 11.5px; color: var(--admin-text-dim); white-space: nowrap; margin-left: 12px;">
                        <?= date("d M, h:i A", strtotime($log['created_at'])) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
