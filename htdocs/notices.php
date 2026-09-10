<?php
/**
 * UNMOOR CLUB - ALL NOTICES
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $db->query("
    SELECT id, text, created_at
    FROM notices
    ORDER BY id DESC
    LIMIT 30
");
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Notices • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Club Announcements", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 12px; text-transform: uppercase;">
                📢 Official Announcements
            </h3>

            <?php if ($notices): ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($notices as $n): ?>
                        <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px;">
                            <div style="font-size: 13.5px; color: #ffffff; line-height: 1.5;">
                                <?= nl2br(htmlspecialchars($n['text'])) ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-dim); margin-top: 8px;">
                                ⏱ <?= date("d M Y • h:i A", strtotime($n['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-muted); padding: 36px 0; font-size: 13.5px;">
                    No notices posted at this time.
                </div>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
