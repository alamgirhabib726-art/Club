<?php
/**
 * UNMOOR CLUB - SHARED ADMIN LAYOUT (HEADER & SIDEBAR)
 * Variables: $pageTitle, $activeNav, $pageSubtitle, $admin (from guard.php)
 */

if (!isset($pageTitle)) $pageTitle = 'Admin Panel';
if (!isset($activeNav)) $activeNav = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!isset($pageSubtitle)) $pageSubtitle = 'Administrator Management Console';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> • Unmoor Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/admin.css">
    <script src="../assets/modal.js"></script>
</head>
<body>

<div class="admin-layout">

    <!-- Mobile Backdrop -->
    <div class="admin-backdrop" id="adminBackdrop"></div>

    <!-- Persistent Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-brand">
            <span>UNMOOR</span>
            <span class="admin-brand-badge">ADMIN</span>
        </div>

        <!-- 1. CORE -->
        <div class="admin-nav-group">
            <div class="admin-nav-group-title">Overview</div>
            <a href="dashboard.php" class="admin-nav-link <?= in_array($activeNav, ['dashboard.php', 'dashboard'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">🏠</span>
                <span>Dashboard</span>
            </a>
        </div>

        <!-- 2. USERS & AUDIT -->
        <div class="admin-nav-group">
            <div class="admin-nav-group-title">Users & Audit</div>
            <a href="users.php" class="admin-nav-link <?= in_array($activeNav, ['users.php', 'users'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">👥</span>
                <span>User Directory</span>
            </a>
            <a href="balance_logs.php" class="admin-nav-link <?= in_array($activeNav, ['balance_logs.php', 'balance_logs'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">💳</span>
                <span>Balance Logs</span>
            </a>
            <a href="activity_logs.php" class="admin-nav-link <?= in_array($activeNav, ['activity_logs.php', 'activity_logs'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">📝</span>
                <span>Activity Logs</span>
            </a>
        </div>

        <!-- 3. FINANCIAL & STORE -->
        <div class="admin-nav-group">
            <div class="admin-nav-group-title">Financial & Store</div>
            <a href="payments.php" class="admin-nav-link <?= in_array($activeNav, ['payments.php', 'payments'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">💰</span>
                <span>Payments</span>
            </a>
            <a href="purchase_orders.php" class="admin-nav-link <?= in_array($activeNav, ['purchase_orders.php', 'purchase_orders'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">🛍️</span>
                <span>Purchase Orders</span>
            </a>
            <a href="donations.php" class="admin-nav-link <?= in_array($activeNav, ['donations.php', 'donations'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">💚</span>
                <span>Donations</span>
            </a>
            <a href="system_ledger.php" class="admin-nav-link <?= in_array($activeNav, ['system_ledger.php', 'system_ledger'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">📊</span>
                <span>System Ledger</span>
            </a>
            <a href="liquidity.php" class="admin-nav-link <?= in_array($activeNav, ['liquidity.php', 'liquidity'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">💧</span>
                <span>Liquidity Control</span>
            </a>
        </div>

        <!-- 4. EVENTS & PROMOTIONS -->
        <div class="admin-nav-group">
            <div class="admin-nav-group-title">Events & Promo</div>
            <a href="events.php" class="admin-nav-link <?= in_array($activeNav, ['events.php', 'events'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">🎉</span>
                <span>Events</span>
            </a>
            <a href="event_participants.php" class="admin-nav-link <?= in_array($activeNav, ['event_participants.php', 'event_participants'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">📋</span>
                <span>Event Participants</span>
            </a>
            <a href="coupons.php" class="admin-nav-link <?= in_array($activeNav, ['coupons.php', 'coupons'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">🎟️</span>
                <span>All Coupons</span>
            </a>
            <a href="coupon_history.php" class="admin-nav-link <?= in_array($activeNav, ['coupon_history.php', 'coupon_history'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">📜</span>
                <span>Coupon History</span>
            </a>
        </div>

        <!-- 5. PRODUCTS & GAME -->
        <div class="admin-nav-group">
            <div class="admin-nav-group-title">Products & Market</div>
            <a href="products.php" class="admin-nav-link <?= in_array($activeNav, ['products.php', 'products'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">📦</span>
                <span>Products</span>
            </a>
            <a href="earn.php" class="admin-nav-link <?= in_array($activeNav, ['earn.php', 'earn_buttons.php', 'earn'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">⚡</span>
                <span>Earn Buttons</span>
            </a>
            <a href="headtail_stats.php" class="admin-nav-link <?= in_array($activeNav, ['headtail_stats.php', 'headtail_stats'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">🎲</span>
                <span>Head/Tail P&amp;L</span>
            </a>
        </div>

        <!-- 6. SYSTEM TOOLS -->
        <div class="admin-nav-group">
            <div class="admin-nav-group-title">System & Tools</div>
            <a href="notices.php" class="admin-nav-link <?= in_array($activeNav, ['notices.php', 'notice.php', 'notices'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">📢</span>
                <span>Notice Board</span>
            </a>
            <a href="settings.php" class="admin-nav-link <?= in_array($activeNav, ['settings.php', 'settings'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">⚙️</span>
                <span>System Settings</span>
            </a>
            <a href="maintenance.php" class="admin-nav-link <?= in_array($activeNav, ['maintenance.php', 'maintenance'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">🛠️</span>
                <span>Maintenance</span>
            </a>
            <a href="export_csv.php" class="admin-nav-link <?= in_array($activeNav, ['export_csv.php', 'export_csv'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">📤</span>
                <span>Export CSV</span>
            </a>
            <a href="logs.php" class="admin-nav-link <?= in_array($activeNav, ['logs.php', 'logs'], true) ? 'active' : '' ?>">
                <span class="admin-nav-icon">📋</span>
                <span>System Logs</span>
            </a>
        </div>

        <!-- LOGOUT -->
        <a href="logout.php" class="admin-nav-link danger">
            <span class="admin-nav-icon">🚪</span>
            <span>Logout</span>
        </a>
    </aside>

    <!-- Main Content Panel -->
    <main class="admin-main">
        
        <!-- Topbar -->
        <div class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button type="button" class="admin-mobile-toggle" id="adminMobileToggle" aria-label="Toggle Navigation">
                    ☰
                </button>
                <div class="admin-page-title-group">
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                    <div class="admin-page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></div>
                </div>
            </div>

            <div class="admin-topbar-actions">
                <div class="admin-user-pill">
                    <span class="admin-status-dot"></span>
                    <span><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></span>
                </div>
            </div>
        </div>
