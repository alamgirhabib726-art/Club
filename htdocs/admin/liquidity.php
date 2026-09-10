<?php
/**
 * UNMOOR CLUB - ADMIN LIQUIDITY POOL & RESERVE FLOORS
 */

require_once __DIR__ . "/guard.php";

/* ================= ENSURE LIQUIDITY ACCOUNT ================= */
$liq = $db->query("
    SELECT id, coins FROM users WHERE role='liquidity' LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$liq) {
    $now = date('Y-m-d H:i:s');
    $db->prepare("
        INSERT INTO users (name, phone, role, coins, status, created_at)
        VALUES ('Liquidity Pool', '00000000001', 'liquidity', 0, 'active', ?)
    ")->execute([$now]);
    $liq = $db->query("
        SELECT id, coins FROM users WHERE role='liquidity' LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
}

$msg = $err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && $amount > 0) {
        $db->prepare("UPDATE users SET coins = coins + ? WHERE id=?")
           ->execute([$amount, $liq['id']]);
        
        log_admin_action($db, $admin['id'], "Injected 🪙 $amount into liquidity pool");

        $msg = "Liquidity added successfully: +🪙 $amount";
        $liq['coins'] += $amount;

    } elseif ($action === 'remove' && $amount > 0 && $amount <= $liq['coins']) {
        $db->prepare("UPDATE users SET coins = coins - ? WHERE id=?")
           ->execute([$amount, $liq['id']]);

        log_admin_action($db, $admin['id'], "Withdrew 🪙 $amount from liquidity pool");

        $msg = "Liquidity withdrawn successfully: -🪙 $amount";
        $liq['coins'] -= $amount;

    } else {
        $err = "Invalid amount or insufficient pool balance.";
    }
}

$pageTitle = 'Liquidity Control';
$activeNav = 'liquidity.php';
$pageSubtitle = 'Manage club reserve liquidity balances and internal coin backing.';

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
            <span class="admin-stat-title">Current Liquidity Pool Reserve</span>
            <div class="admin-stat-icon" style="color: #38bdf8;">💧</div>
        </div>
        <div class="admin-stat-value">🪙 <?= number_format($liq['coins'], 2) ?></div>
        <div class="admin-stat-subtext">
            <span>Market backing & settlement capital</span>
        </div>
    </div>
</div>

<!-- INJECT / DRAIN LIQUIDITY FORMS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
    
    <!-- ADD LIQUIDITY -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">➕ Inject Liquidity</h2>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="admin-form-group">
                <label class="admin-label">Coin Amount to Inject</label>
                <input type="number" step="0.01" min="1" name="amount" class="admin-input" placeholder="e.g. 1000.00" required>
            </div>
            <button type="submit" class="admin-btn admin-btn-success admin-btn-block" style="width: 100%;">
                💧 Inject Coins to Pool
            </button>
        </form>
    </div>

    <!-- REMOVE LIQUIDITY -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">➖ Withdraw Liquidity</h2>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="remove">
            <div class="admin-form-group">
                <label class="admin-label">Coin Amount to Withdraw</label>
                <input type="number" step="0.01" min="1" max="<?= (float)$liq['coins'] ?>" name="amount" class="admin-input" placeholder="e.g. 500.00" required>
            </div>
            <button type="submit" class="admin-btn admin-btn-danger admin-btn-block" style="width: 100%;" onclick="return confirm('Withdraw liquidity from reserve?')">
                ⚠️ Drain from Pool
            </button>
        </form>
    </div>

</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
