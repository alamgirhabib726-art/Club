<?php
/**
 * UNMOOR CLUB - COMMUNITY DIRECTORY & RECENT CONTACTS
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

/* FETCH RECENT TRANSFER CONTACTS */
$contacts = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.name, u.phone, u.photo
        FROM coin_history ch
        JOIN users u ON (ch.source_user_id = u.id OR ch.user_id = u.id)
        WHERE (ch.user_id = ? OR ch.source_user_id = ?)
          AND u.id != ?
        LIMIT 20
    ");
    $stmt->execute([$uid, $uid, $uid]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contacts & Community • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header('Contacts & Lists', '/account/') ?>

        <!-- RECENT TRANSFER PARTNERS -->
        <div class="card">
            <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin-bottom: 12px;">👥 Recent Transfer Partners</h3>
            
            <?php if ($contacts): ?>
                <?php foreach ($contacts as $c): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 38px; height: 38px; border-radius: 50%; overflow: hidden; background: var(--bg-dark); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($c['photo'])): ?>
                                    <img src="../uploads/avatars/<?= htmlspecialchars($c['photo']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    👤
                                <?php endif; ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 14px; color: #ffffff;"><?= htmlspecialchars($c['name'] ?? 'Member') ?></div>
                                <div style="font-size: 12px; color: var(--text-muted); font-family: monospace;"><?= htmlspecialchars($c['phone'] ?? '') ?></div>
                            </div>
                        </div>

                        <a href="../transfer.php?to=<?= htmlspecialchars($c['phone'] ?? '') ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            Transfer
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-dim); padding: 20px 0; font-size: 13px;">
                    No recent peer transfer history recorded.
                </div>
            <?php endif; ?>
        </div>

        <!-- OFFICIAL CLUB CHANNELS -->
        <div class="card">
            <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin-bottom: 12px;">🌐 Official Channels</h3>

            <a href="https://t.me/Angkur_TouFik" target="_blank" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-main);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 24px;">✈️</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14px;">Official Telegram VIP</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Signals, club updates, &amp; market drops</div>
                    </div>
                </div>
                <span style="color: var(--text-dim);">›</span>
            </a>

            <a href="https://wa.me/8801788674353" target="_blank" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; text-decoration: none; color: var(--text-main);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 24px;">💬</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14px;">24/7 WhatsApp Desk</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Direct member care and verified concierge</div>
                    </div>
                </div>
                <span style="color: var(--text-dim);">›</span>
            </a>
        </div>

        <?= render_support_widget() ?>

    </div>

    <!-- GLOBAL BOTTOM NAVIGATION -->
    <?php require_once __DIR__ . "/../bottom_nav.php"; ?>

    <script src="../assets/app.js"></script>
</body>
</html>
