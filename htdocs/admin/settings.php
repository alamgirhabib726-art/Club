<?php
/**
 * UNMOOR CLUB - ADMIN SYSTEM CONFIGURATION
 */

require_once __DIR__ . "/guard.php";

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = ($driver === 'sqlite')
        ? "INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v"
        : "INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)";
    
    $stmt = $db->prepare($sql);
    foreach ($_POST as $k => $v) {
        if ($k === 'submit') continue;
        $stmt->execute([$k, trim((string)$v)]);
    }

    log_admin_action($db, $admin['id'], "Updated global system settings");

    $msg = "System parameters saved successfully.";
}

$settings = [];
try {
    $settings = $db->query("SELECT k, v FROM settings WHERE k IS NOT NULL")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {}

$pageTitle = 'System Settings';
$activeNav = 'settings.php';
$pageSubtitle = 'Configure global platform economic parameters, VIP pricing, and game multipliers.';

require_once __DIR__ . "/layout_top.php";
?>

<?php if ($msg): ?>
    <div class="admin-alert admin-alert-success">
        <span>✅</span>
        <div><?= htmlspecialchars($msg) ?></div>
    </div>
<?php endif; ?>

<div class="admin-card" style="max-width: 680px;">
    <div class="admin-card-header">
        <h2 class="admin-card-title">⚙️ Global Configuration Parameters</h2>
    </div>

    <form method="post">
        <div class="admin-form-group">
            <label class="admin-label" for="premium_price">💎 Premium VIP Upgrade Price (🪙 Coins)</label>
            <input type="number" step="any" id="premium_price" name="premium_price" value="<?= htmlspecialchars($settings['premium_price'] ?? '300') ?>" class="admin-input" required>
            <div style="font-size: 12px; color: var(--admin-text-dim); margin-top: 4px;">Coins charged to members when applying for VIP status.</div>
        </div>

        <div class="admin-form-group">
            <label class="admin-label" for="headtail_percent">🪙 Head &amp; Tail Wager Win Multiplier (%)</label>
            <input type="number" step="any" id="headtail_percent" name="headtail_percent" value="<?= htmlspecialchars($settings['headtail_percent'] ?? '80') ?>" class="admin-input" required>
            <div style="font-size: 12px; color: var(--admin-text-dim); margin-top: 4px;">Payout percentage return awarded to players on a winning coin flip (default 80%).</div>
        </div>

        <div class="admin-form-group">
            <label class="admin-label" for="apply_fee">📝 Member Application Fee (🪙 Coins)</label>
            <input type="number" step="any" id="apply_fee" name="apply_fee" value="<?= htmlspecialchars($settings['apply_fee'] ?? '100') ?>" class="admin-input">
            <div style="font-size: 12px; color: var(--admin-text-dim); margin-top: 4px;">Standard registration approval deposit coins.</div>
        </div>

        <button type="submit" class="admin-btn admin-btn-primary" style="margin-top: 10px;">
            💾 Save Global Settings
        </button>
    </form>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
