<?php
/**
 * UNMOOR CLUB - ACCOUNT PRIVACY & SECURITY LOGS
 */

session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../core/components.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH LOGIN HISTORY */
$logins = [];
try {
    $stmt = $db->prepare("
        SELECT ip_address, user_agent, created_at
        FROM login_history
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 20
    ");
    $stmt->execute([$uid]);
    $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {
    // If table missing, create gracefully
}

$currentIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Privacy & Security • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header('Privacy & Security', '/account/') ?>

        <!-- CURRENT SESSION -->
        <div class="card">
            <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin-bottom: 12px;">🛡️ Current Active Session</h3>
            
            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px; margin-bottom: 8px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">IP Address</div>
                <div style="font-size: 14px; font-weight: 700; color: var(--accent-green); font-family: monospace; margin-top: 2px;">
                    <?= htmlspecialchars($currentIp) ?> (This Device)
                </div>
            </div>

            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Browser User Agent</div>
                <div style="font-size: 12px; color: var(--text-muted); line-height: 1.4; margin-top: 2px; word-break: break-all;">
                    <?= htmlspecialchars($currentAgent) ?>
                </div>
            </div>
        </div>

        <!-- RECENT LOGIN ACTIVITY -->
        <div class="card">
            <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin-bottom: 12px;">📜 Login History & Device Logins</h3>

            <?php if ($logins): ?>
                <?php foreach ($logins as $l): ?>
                    <div style="padding: 12px 0; border-bottom: 1px dashed var(--border-color);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <span style="font-weight: 700; font-size: 13.5px; color: #ffffff; font-family: monospace;">
                                <?= htmlspecialchars($l['ip_address'] ?? 'Unknown') ?>
                            </span>
                            <span style="font-size: 11px; color: var(--text-dim);">
                                <?= !empty($l['created_at']) ? date("d M Y, h:i A", strtotime($l['created_at'])) : 'Recent' ?>
                            </span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars($l['user_agent'] ?? 'Web browser session') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-dim); padding: 20px; font-size: 13px;">
                    No previous session logs recorded.
                </div>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <!-- GLOBAL BOTTOM NAVIGATION -->
    <?php require_once __DIR__ . "/../bottom_nav.php"; ?>

    <script src="../assets/app.js"></script>
</body>
</html>
