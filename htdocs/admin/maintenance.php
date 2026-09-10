<?php
/**
 * UNMOOR CLUB - ADMIN SYSTEM MAINTENANCE CONTROLLER
 */

require_once __DIR__ . "/guard.php";

/* ================= ENSURE SETTINGS ROW ================= */
try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $db->exec("INSERT OR IGNORE INTO settings (id, maintenance, updated_at) VALUES (1, 0, datetime('now'))");
    } else {
        $db->exec("INSERT IGNORE INTO settings (id, maintenance, updated_at) VALUES (1, 0, CURRENT_TIMESTAMP)");
    }
} catch (Throwable $e) {}

/* ================= FETCH STATUS ================= */
$row = $db->query("
    SELECT maintenance, updated_at
    FROM settings
    WHERE id = 1
")->fetch(PDO::FETCH_ASSOC);

$maintenance = (int)($row['maintenance'] ?? 0);
$updatedAt  = $row['updated_at'] ?? null;

/* ================= TOGGLE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $maintenance ? 0 : 1;
    $now = date('Y-m-d H:i:s');

    $db->prepare("
        UPDATE settings
        SET maintenance = ?, updated_at = ?
        WHERE id = 1
    ")->execute([$new, $now]);

    try {
        $db->prepare("INSERT INTO logs (user_id, action, created_at) VALUES (?, ?, ?)")
           ->execute([$admin['id'], ($new ? "Enabled global maintenance mode" : "Disabled maintenance mode (Live) "), $now]);
    } catch (Throwable $t) {}

    header("Location: maintenance.php");
    exit;
}

$pageTitle = 'Maintenance Control';
$activeNav = 'maintenance.php';
$pageSubtitle = 'Toggle site-wide maintenance screen for non-admin members.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-card" style="max-width: 600px; margin: 0 auto; text-align: center; padding: 36px 24px;">
    <div style="font-size: 54px; margin-bottom: 12px;">
        <?= $maintenance ? "🔒" : "🔓" ?>
    </div>

    <h2 style="font-size: 20px; font-weight: 900; color: #ffffff; margin-bottom: 10px;">
        Emergency System Maintenance Switch
    </h2>

    <p style="font-size: 13.5px; color: var(--admin-text-muted); margin-bottom: 24px; line-height: 1.6;">
        When Maintenance Mode is enabled, all member sessions are locked to a friendly maintenance notice. Administrators retain full access.
    </p>

    <div style="margin-bottom: 24px;">
        <?php if ($maintenance): ?>
            <div class="admin-alert admin-alert-danger" style="justify-content: center;">
                <span>🔒</span>
                <div>MAINTENANCE ACTIVE — SITE ACCESS RESTRICTED</div>
            </div>
        <?php else: ?>
            <div class="admin-alert admin-alert-success" style="justify-content: center;">
                <span>🟢</span>
                <div>LIVE MODE — UNMOOR CLUB FULLY OPERATIONAL</div>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" onsubmit="return confirm('Confirm toggling maintenance mode?')">
        <?php if ($maintenance): ?>
            <button type="submit" class="admin-btn admin-btn-success" style="width: 100%; padding: 16px; font-size: 16px;">
                🟢 Restore Live Operations (Disable Maintenance)
            </button>
        <?php else: ?>
            <button type="submit" class="admin-btn admin-btn-danger" style="width: 100%; padding: 16px; font-size: 16px;">
                🔒 Enable Maintenance Mode (Lock Site)
            </button>
        <?php endif; ?>
    </form>

    <div style="font-size: 12px; color: var(--admin-text-dim); margin-top: 20px;">
        Last state toggle: <?= $updatedAt ? date("d M Y • h:i A", strtotime($updatedAt)) : "Never" ?>
    </div>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
