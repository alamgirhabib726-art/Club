<?php
/**
 * UNMOOR CLUB - EARN COINS
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* ================= LOGIN REQUIRED ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= USER CHECK ================= */
$stmt = $db->prepare("
    SELECT status, apply_status
    FROM users
    WHERE id = ?
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['status'] !== 'active' && $user['status'] !== 'premium') || $user['apply_status'] !== 'approved') {
    die("ACCESS DENIED");
}

/* ================= FETCH ACTIVE EARN BUTTONS ================= */
$stmt = $db->query("
    SELECT title, link
    FROM earn_buttons
    WHERE status = 'active'
    ORDER BY id DESC
");
$buttons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Earn Coins • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Earn Coins", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 8px; text-transform: uppercase;">
                💰 Available Tasks & Earning Channels
            </h3>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;">
                Complete tasks, view sponsored partners, and participate in community bounties.
            </p>

            <?php if ($buttons): ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($buttons as $b): ?>
                        <a href="<?= htmlspecialchars($b['link']) ?>" target="_blank" class="btn btn-primary btn-block" style="text-align: center; padding: 14px; font-size: 14.5px;">
                            🚀 <?= htmlspecialchars($b['title']) ?> ›
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-muted); padding: 36px 0;">
                    <div style="font-size: 36px; margin-bottom: 8px;">⏳</div>
                    No earning tasks active right now. Please check back soon!
                </div>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
