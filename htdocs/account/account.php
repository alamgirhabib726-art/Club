<?php
/**
 * UNMOOR CLUB - MEMBER ACCOUNT PROFILE & HUB
 */

session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../core/components.php";

/* AUTH CHECK */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH USER DETAILS */
$stmt = $db->prepare("
    SELECT id, name, phone, email, photo, status, apply_status, role, coins, balance, created_at, coin_cycle_start
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] === 'banned') {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$avatar = !empty($user['photo'])
    ? "../uploads/avatars/" . htmlspecialchars($user['photo'])
    : "../assets/default-avatar.png";

$isVip = ($user['status'] === 'premium' || $user['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Account • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header('Member Profile', '/dashboard.php') ?>

        <!-- PROFILE HERO CARD -->
        <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(15, 23, 42, 0.95)); border-color: rgba(245, 158, 11, 0.25); text-align: center; padding: 24px 16px;">
            <div style="position: relative; width: 84px; height: 84px; margin: 0 auto 14px;">
                <img src="<?= $avatar ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-gold); box-shadow: var(--shadow-gold);" onerror="this.src='../assets/default-avatar.png'">
                <a href="avatar.php" style="position: absolute; bottom: 0; right: 0; background: var(--accent-gold); color: #000; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; text-decoration: none; border: 2px solid #000;">
                    📷
                </a>
            </div>

            <h2 style="font-size: 18px; font-weight: 800; color: #ffffff; margin-bottom: 4px;">
                <?= htmlspecialchars($user['name']) ?>
            </h2>

            <div style="font-size: 13px; color: var(--text-muted); font-family: monospace; margin-bottom: 12px;">
                <?= htmlspecialchars($user['phone']) ?> • ID #<?= $user['id'] ?>
            </div>

            <div style="display: flex; justify-content: center; gap: 8px;">
                <?php if ($isVip): ?>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: var(--accent-gold); border: 1px solid var(--accent-gold);">💎 VIP PREMIUM</span>
                <?php else: ?>
                    <span class="badge" style="background: rgba(34, 197, 94, 0.2); color: var(--accent-green); border: 1px solid var(--accent-green);">VERIFIED MEMBER</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- BALANCE OVERVIEW PILLS -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px;">
            <div class="card" style="padding: 14px; text-align: center; margin-bottom: 0;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Club Coins</div>
                <div style="font-size: 18px; font-weight: 900; color: var(--accent-gold); margin-top: 4px;">
                    🪙 <?= number_format($user['coins'], 2) ?>
                </div>
            </div>

            <div class="card" style="padding: 14px; text-align: center; margin-bottom: 0;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Fiat Balance</div>
                <div style="font-size: 18px; font-weight: 900; color: #ffffff; margin-top: 4px;">
                    ৳ <?= number_format($user['balance'] ?? 0, 2) ?>
                </div>
            </div>
        </div>

        <!-- ACCOUNT NAVIGATION LIST -->
        <div class="card" style="padding: 6px 12px;">
            
            <a href="account_settings.php" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 4px; text-decoration: none; border-bottom: 1px solid var(--border-color); color: var(--text-main);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">⚙️</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Account Settings</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Change phone number &amp; password</div>
                    </div>
                </div>
                <span style="color: var(--text-dim); font-size: 14px;">›</span>
            </a>

            <a href="avatar.php" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 4px; text-decoration: none; border-bottom: 1px solid var(--border-color); color: var(--text-main);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">🖼️</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Profile Avatar</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Update profile picture</div>
                    </div>
                </div>
                <span style="color: var(--text-dim); font-size: 14px;">›</span>
            </a>

            <a href="privacy.php" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 4px; text-decoration: none; border-bottom: 1px solid var(--border-color); color: var(--text-main);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">🔒</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Security &amp; Privacy</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Active session logs &amp; IP security</div>
                    </div>
                </div>
                <span style="color: var(--text-dim); font-size: 14px;">›</span>
            </a>

            <a href="notifications.php" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 4px; text-decoration: none; border-bottom: 1px solid var(--border-color); color: var(--text-main);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">🔔</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Notifications</div>
                        <div style="font-size: 12px; color: var(--text-muted);">System alerts &amp; announcements</div>
                    </div>
                </div>
                <span style="color: var(--text-dim); font-size: 14px;">›</span>
            </a>

            <a href="lists.php" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 4px; text-decoration: none; border-bottom: 1px solid var(--border-color); color: var(--text-main);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">👥</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Contacts &amp; Community</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Recent transfer partners &amp; channels</div>
                    </div>
                </div>
                <span style="color: var(--text-dim); font-size: 14px;">›</span>
            </a>

            <a href="storage.php" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 4px; text-decoration: none; color: var(--text-main);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 20px;">🧹</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Storage &amp; Cache Data</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Manage session logs and chat history</div>
                    </div>
                </div>
                <span style="color: var(--text-dim); font-size: 14px;">›</span>
            </a>

        </div>

        <!-- QUICK SHORTCUTS & VIP UPGRADE -->
        <?php if (!$isVip): ?>
            <div class="card" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(2, 6, 23, 0.9)); border-color: rgba(245, 158, 11, 0.3);">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="color: var(--accent-gold); font-size: 15px;">💎 Upgrade to VIP Status</strong>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Unlock priority trading &amp; zero fees</div>
                    </div>
                    <a href="../premium.php" class="btn btn-gold" style="padding: 8px 14px; font-size: 12px;">
                        Upgrade
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- LOGOUT BUTTON -->
        <div style="margin-top: 14px; margin-bottom: 24px;">
            <a href="../logout.php" class="btn btn-danger btn-block" style="text-align: center;" onclick="return confirm('Are you sure you want to log out of your Unmoor Club account?')">
                🚪 Secure Log Out
            </a>
        </div>

        <!-- SUPPORT WIDGET -->
        <?= render_support_widget() ?>

    </div>

    <!-- GLOBAL BOTTOM NAVIGATION -->
    <?php require_once __DIR__ . "/../bottom_nav.php"; ?>

    <script src="../assets/app.js"></script>
</body>
</html>
