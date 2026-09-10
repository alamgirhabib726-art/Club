<?php
/**
 * UNMOOR CLUB - ADMIN MARKET SEED & INITIAL PRICE
 */

require_once __DIR__ . "/guard.php";

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $price = (float)($_POST['price'] ?? 0);
    if ($price <= 0) {
        $err = "Seed price must be greater than zero.";
    } else {
        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            $db->prepare("
                INSERT INTO uc_market (price, created_at)
                VALUES (?, $nowExpr)
            ")->execute([$price]);

            try {
                $db->prepare("INSERT INTO logs (user_id, action, created_at) VALUES (?, ?, $nowExpr)")
                   ->execute([$admin['id'], "Seeded market price to ৳$price"]);
            } catch (Throwable $t) {}

            $msg = "Market seed price set to ৳ " . number_format($price, 2);
        } catch (Throwable $e) {
            $err = "Failed to set seed price: " . $e->getMessage();
        }
    }
}

/* FETCH CURRENT MARKET PRICE */
$row = $db->query("
    SELECT price, created_at 
    FROM uc_market 
    ORDER BY id DESC 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$currentPrice = $row['price'] ?? null;

$pageTitle = 'Market Seed';
$activeNav = 'market_seed.php';
$pageSubtitle = 'Initialize or benchmark the base internal trade price for club assets.';

require_once __DIR__ . "/layout_top.php";
?>

<?php if ($msg): ?>
    <div class="admin-alert admin-alert-success">
        <span>✅</span>
        <div><?= htmlspecialchars($msg) ?></div>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="admin-alert admin-alert-danger">
        <span>❌</span>
        <div><?= htmlspecialchars($err) ?></div>
    </div>
<?php endif; ?>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Current Market Benchmark</span>
            <div class="admin-stat-icon" style="color: #22c55e;">📈</div>
        </div>
        <div class="admin-stat-value">
            <?= $currentPrice ? "৳ " . number_format($currentPrice, 2) : "Unseeded" ?>
        </div>
        <div class="admin-stat-subtext">
            <span>Last recorded valuation rate</span>
        </div>
    </div>
</div>

<div class="admin-card" style="max-width: 540px;">
    <div class="admin-card-header">
        <h2 class="admin-card-title">🌱 Seed Internal Market Rate</h2>
    </div>

    <form method="post">
        <div class="admin-form-group">
            <label class="admin-label">Seed Price (৳ BDT per unit)</label>
            <input type="number" step="0.01" min="0.01" name="price" class="admin-input" placeholder="e.g. 10.00" value="<?= $currentPrice ? htmlspecialchars((string)$currentPrice) : '' ?>" required>
            <div style="font-size: 12px; color: var(--admin-text-dim); margin-top: 6px;">
                ⚠️ Use only during initial setup or manual market resets. The market engine will compute organic rate changes thereafter.
            </div>
        </div>

        <button type="submit" class="admin-btn admin-btn-primary admin-btn-block" style="width: 100%;" onclick="return confirm('Update market baseline seed?')">
            🌱 Inject Market Benchmark
        </button>
    </form>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
