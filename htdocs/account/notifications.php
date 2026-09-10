<?php
/**
 * UNMOOR CLUB - NOTIFICATIONS & CLUB BULLETINS
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

/* FETCH NOTIFICATIONS */
$rows = [];
try {
    $stmt = $db->prepare("
        SELECT id, title, message, type, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 50
    ");
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header('Notifications', '/account/') ?>

        <div class="card" style="padding: 6px 12px;">
            <?php if ($rows): ?>
                <?php foreach ($rows as $n): ?>
                    <div style="display: flex; gap: 12px; padding: 14px 4px; border-bottom: 1px solid var(--border-color); <?= !$n['is_read'] ? 'background: rgba(245, 158, 11, 0.04);' : '' ?>">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--bg-dark); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
                            <?php
                                echo match($n['type'] ?? ''){
                                    'deposit' => '💰',
                                    'game'    => '🎲',
                                    'alert'   => '⚠️',
                                    'system'  => '🔔',
                                    default   => '📢'
                                };
                            ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 700; font-size: 14px; color: #ffffff; margin-bottom: 2px;">
                                <?= htmlspecialchars($n['title'] ?? 'Notification') ?>
                            </div>
                            <div style="font-size: 13px; color: var(--text-muted); line-height: 1.4;">
                                <?= htmlspecialchars($n['message'] ?? '') ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-dim); margin-top: 4px;">
                                <?= !empty($n['created_at']) ? date("d M Y, h:i A", strtotime($n['created_at'])) : '' ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-dim); padding: 36px 16px;">
                    <div style="font-size: 32px; margin-bottom: 8px;">🔔</div>
                    <div style="font-weight: 700; color: #ffffff; font-size: 15px;">No notifications yet</div>
                    <div style="font-size: 12.5px; margin-top: 4px;">You're all caught up with your latest club updates.</div>
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
