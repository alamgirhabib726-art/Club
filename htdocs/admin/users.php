<?php
/**
 * UNMOOR CLUB - ADMIN USER DIRECTORY & MANAGEMENT
 */

require_once __DIR__ . "/guard.php";

$msg = '';
$error = '';

/* ======================
   BAN / UNBAN / APPROVE ACTIONS
   ====================== */
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        if ($_GET['action'] === 'ban') {
            $db->prepare("
                UPDATE users
                SET status = 'banned'
                WHERE id = ? AND role NOT IN ('admin','system')
            ")->execute([$id]);

            log_admin_action($db, $admin['id'], "Banned User #$id");

            $msg = "User #$id has been banned.";
        }

        if ($_GET['action'] === 'unban') {
            $db->prepare("
                UPDATE users
                SET status = 'active'
                WHERE id = ? AND role NOT IN ('admin','system')
            ")->execute([$id]);

            log_admin_action($db, $admin['id'], "Unbanned User #$id");

            $msg = "User #$id has been activated.";
        }

        if ($_GET['action'] === 'approve') {
            $db->prepare("
                UPDATE users
                SET status = 'active', apply_status = 'approved'
                WHERE id = ?
            ")->execute([$id]);

            log_admin_action($db, $admin['id'], "Approved User Application #$id");

            $msg = "User #$id application approved.";
        }
    }
}

/* ======================
   SEARCH & FILTER
   ====================== */
$search = trim($_GET['q'] ?? '');
$filterRole = trim($_GET['role'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "SELECT id, name, phone, email, role, status, apply_status, coins, locked_coins, balance, coin_cycle_start, created_at FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR id = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = is_numeric($search) ? (int)$search : 0;
}

if ($filterRole !== '') {
    $sql .= " AND role = ?";
    $params[] = $filterRole;
}

if ($filterStatus !== '') {
    if ($filterStatus === 'pending') {
        $sql .= " AND apply_status = 'pending'";
    } elseif ($filterStatus === 'debt') {
        $sql .= " AND coins < 0";
    } else {
        $sql .= " AND status = ?";
        $params[] = $filterStatus;
    }
}

$sql .= " ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$totalCount = count($users);
$negativeCount = 0;
foreach ($users as $u) {
    if ($u['role'] === 'user' && $u['coins'] < 0) {
        $negativeCount++;
    }
}

$pageTitle = 'User Directory';
$activeNav = 'users.php';
$pageSubtitle = 'Manage member accounts, permissions, balances, application approvals, and statuses.';

require_once __DIR__ . "/layout_top.php";
?>

<?php if ($msg): ?>
    <div class="admin-alert admin-alert-success">
        <span>✅</span>
        <div><?= htmlspecialchars($msg) ?></div>
    </div>
<?php endif; ?>

<!-- SEARCH & STATS BAR -->
<div class="admin-card" style="padding: 18px 22px;">
    <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="admin-input" placeholder="Search by name, phone, email, or User ID..." style="max-width: 320px;">
            
            <select name="status" class="admin-select" style="max-width: 160px;">
                <option value="">All Statuses</option>
                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Approval</option>
                <option value="banned" <?= $filterStatus === 'banned' ? 'selected' : '' ?>>Banned</option>
                <option value="debt" <?= $filterStatus === 'debt' ? 'selected' : '' ?>>Debt (Negative Coins)</option>
            </select>

            <select name="role" class="admin-select" style="max-width: 140px;">
                <option value="">All Roles</option>
                <option value="user" <?= $filterRole === 'user' ? 'selected' : '' ?>>User</option>
                <option value="premium" <?= $filterRole === 'premium' ? 'selected' : '' ?>>VIP / Premium</option>
                <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>

            <button type="submit" class="admin-btn admin-btn-primary">Filter</button>
            <?php if ($search !== '' || $filterStatus !== '' || $filterRole !== ''): ?>
                <a href="users.php" class="admin-btn admin-btn-secondary">Clear</a>
            <?php endif; ?>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="admin-badge admin-badge-info">Showing <?= $totalCount ?> Users</span>
            <?php if ($negativeCount > 0): ?>
                <span class="admin-badge admin-badge-danger">⚠️ <?= $negativeCount ?> in Debt</span>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- USERS TABLE -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">👥 Member Accounts List</h2>
        <div style="display:flex; gap:8px;">
            <a href="export_csv.php" class="admin-btn admin-btn-secondary admin-btn-sm">📤 Export CSV</a>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No matching users found.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Member Details</th>
                        <th>Role</th>
                        <th>Coin Balances (Avail / Locked)</th>
                        <th>Status</th>
                        <th>Joined Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php 
                            $avail = (float)$u['coins'];
                            $locked = (float)($u['locked_coins'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <strong style="color: #ffffff;">#<?= $u['id'] ?></strong>
                            </td>
                            <td>
                                <div style="font-weight: 800; color: #ffffff;"><?= htmlspecialchars($u['name']) ?></div>
                                <div style="font-size: 12px; color: var(--admin-text-dim);">📱 <?= htmlspecialchars($u['phone']) ?></div>
                                <?php if (!empty($u['email'])): ?>
                                    <div style="font-size: 11px; color: var(--admin-text-dim);">✉️ <?= htmlspecialchars($u['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="admin-badge admin-badge-admin">ADMIN</span>
                                <?php elseif ($u['role'] === 'system'): ?>
                                    <span class="admin-badge" style="background:rgba(100,116,139,0.2);color:#94a3b8;">SYSTEM</span>
                                <?php elseif ($u['role'] === 'premium' || $u['status'] === 'premium'): ?>
                                    <span class="admin-badge admin-badge-warning">👑 VIP</span>
                                <?php else: ?>
                                    <span class="admin-badge" style="background:rgba(255,255,255,0.06);color:#cbd5e1;">MEMBER</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <strong style="color: <?= $avail < 0 ? '#ef4444' : '#facc15' ?>; font-size: 14px;">
                                        🪙 <?= number_format($avail, 2) ?>
                                    </strong>
                                    <?php if ($locked > 0): ?>
                                        <span style="font-size: 11px; color: #fbbf24; font-weight: 700;">
                                            🔒 Locked: <?= number_format($locked, 2) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $st = strtolower($u['status']);
                                    $appSt = strtolower($u['apply_status']);
                                    
                                    if ($st === 'banned') {
                                        echo '<span class="admin-badge admin-badge-danger">BANNED</span>';
                                    } elseif ($appSt === 'pending') {
                                        echo '<span class="admin-badge admin-badge-warning">PENDING</span>';
                                    } elseif ($st === 'active') {
                                        echo '<span class="admin-badge admin-badge-success">ACTIVE</span>';
                                    } else {
                                        echo '<span class="admin-badge admin-badge-info">'.htmlspecialchars(strtoupper($st)).'</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--admin-text-muted);">
                                    <?= date("d M Y", strtotime($u['created_at'])) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <?php if ($u['apply_status'] === 'pending'): ?>
                                        <a href="users.php?action=approve&id=<?= $u['id'] ?>" class="admin-btn admin-btn-sm admin-btn-success" onclick="return confirm('Approve this user account?')">
                                            ✅ Approve
                                        </a>
                                    <?php endif; ?>

                                    <a href="add_balance.php?user_id=<?= $u['id'] ?>" class="admin-btn admin-btn-sm admin-btn-primary" title="Adjust Balance">
                                        🪙 Adjust
                                    </a>

                                    <?php if ($u['role'] !== 'admin' && $u['role'] !== 'system'): ?>
                                        <?php if ($u['status'] === 'banned'): ?>
                                            <a href="users.php?action=unban&id=<?= $u['id'] ?>" class="admin-btn admin-btn-sm admin-btn-secondary" onclick="return confirm('Unban this user?')">
                                                Unban
                                            </a>
                                        <?php else: ?>
                                            <a href="users.php?action=ban&id=<?= $u['id'] ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Are you sure you want to ban this user?')">
                                                Ban
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
