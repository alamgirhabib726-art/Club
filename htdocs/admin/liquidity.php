<?php
/**
 * UNMOOR CLUB - ADMIN LIQUIDITY POOL & TRADING RESERVES
 * File: /admin/liquidity.php
 * Comprehensive audit & management of the BDT Liquidity Pool, Trading UC reserves,
 * fee revenue tracking, and liquidity ledger.
 */

require_once __DIR__ . "/guard.php";
require_once __DIR__ . "/../trade/engine/fee_engine.php";

$msg = $err = "";

// Fetch main liquidity pool record
$pool = $db->query("SELECT * FROM liquidity_pool WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$pool) {
    $db->exec("INSERT INTO liquidity_pool (id, bdt_reserve, tuc_reserve, total_fee_revenue_bdt, conversion_fee_revenue_bdt, realized_pnl_bdt, status)
        VALUES (1, 500000.0000, 250000.0000, 0.0000, 0.0000, 0.0000, 'active')");
    $pool = $db->query("SELECT * FROM liquidity_pool WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// Handle liquidity adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $asset = $_POST['asset'] ?? 'BDT';
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

    if ($action === 'inject_bdt' && $amount > 0) {
        $curBdt = (float)$pool['bdt_reserve'];
        $newBdt = $curBdt + $amount;
        $db->prepare("UPDATE liquidity_pool SET bdt_reserve = bdt_reserve + ?, updated_at = $nowExpr WHERE id = 1")->execute([$amount]);
        
        $db->prepare("INSERT INTO liquidity_ledger (type, amount, asset, balance_before, balance_after, source, reference, created_at)
            VALUES ('admin_inject', ?, 'BDT', ?, ?, 'Admin Panel', 'Admin manual liquidity injection', $nowExpr)")
            ->execute([$amount, $curBdt, $newBdt]);

        log_admin_action($db, $admin['id'], "Injected ৳ $amount BDT into Liquidity Pool");
        $msg = "Successfully injected ৳ " . number_format($amount, 2) . " BDT into Liquidity Pool.";
        $pool['bdt_reserve'] += $amount;

    } elseif ($action === 'withdraw_bdt' && $amount > 0 && $amount <= $pool['bdt_reserve']) {
        $curBdt = (float)$pool['bdt_reserve'];
        $newBdt = $curBdt - $amount;
        $db->prepare("UPDATE liquidity_pool SET bdt_reserve = bdt_reserve - ?, updated_at = $nowExpr WHERE id = 1")->execute([$amount]);

        $db->prepare("INSERT INTO liquidity_ledger (type, amount, asset, balance_before, balance_after, source, reference, created_at)
            VALUES ('admin_withdraw', ?, 'BDT', ?, ?, 'Admin Panel', 'Admin manual liquidity withdrawal', $nowExpr)")
            ->execute([-$amount, $curBdt, $newBdt]);

        log_admin_action($db, $admin['id'], "Withdrew ৳ $amount BDT from Liquidity Pool");
        $msg = "Successfully withdrew ৳ " . number_format($amount, 2) . " BDT from Liquidity Pool.";
        $pool['bdt_reserve'] -= $amount;

    } else {
        $err = "Invalid operation or insufficient pool reserve.";
    }
}

// Fetch ledger entries
$ledgerStmt = $db->query("SELECT * FROM liquidity_ledger ORDER BY id DESC LIMIT 30");
$ledgerEntries = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

// Overall fee metrics
$feeSumStmt = $db->query("SELECT fee_type, COUNT(*) as count, SUM(amount) as total FROM trading_fees GROUP BY fee_type");
$feeBreakdown = $feeSumStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Liquidity Pool & Fee Revenue';
$activeNav = 'liquidity.php';
$pageSubtitle = 'Monitor BDT market liquidity reserves, automated fee routing, and counterparty settlement.';

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

<!-- POOL METRIC STATS -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Liquidity Pool Reserve (BDT)</span>
            <div class="admin-stat-icon" style="color: #10b981;">💵</div>
        </div>
        <div class="admin-stat-value">৳ <?= number_format((float)$pool['bdt_reserve'], 2) ?></div>
        <div class="admin-stat-subtext">
            <span>Counterparty & settlement reserve</span>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Total Fee Revenue Earned</span>
            <div class="admin-stat-icon" style="color: #3b82f6;">📈</div>
        </div>
        <div class="admin-stat-value">৳ <?= number_format((float)$pool['total_fee_revenue_bdt'], 2) ?></div>
        <div class="admin-stat-subtext">
            <span>Includes 1.345% conv & trade fees</span>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Conversion Fee Revenue</span>
            <div class="admin-stat-icon" style="color: #f59e0b;">🔄</div>
        </div>
        <div class="admin-stat-value">৳ <?= number_format((float)$pool['conversion_fee_revenue_bdt'], 2) ?></div>
        <div class="admin-stat-subtext">
            <span>From Club UC ↔ BDT conversions</span>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Pool Realized PnL</span>
            <div class="admin-stat-icon" style="color: #8b5cf6;">⚡</div>
        </div>
        <div class="admin-stat-value" style="color: <?= (float)$pool['realized_pnl_bdt'] >= 0 ? '#10b981' : '#ef4444' ?>;">
            ৳ <?= number_format((float)$pool['realized_pnl_bdt'], 2) ?>
        </div>
        <div class="admin-stat-subtext">
            <span>Trading counterparty net settlement</span>
        </div>
    </div>
</div>

<!-- INJECT / DRAIN LIQUIDITY FORMS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <!-- INJECT BDT -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">➕ Inject BDT Liquidity</h2>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="inject_bdt">
            <div class="admin-form-group">
                <label class="admin-label">BDT Amount to Inject</label>
                <input type="number" step="0.01" min="100" name="amount" class="admin-input" placeholder="e.g. 50000.00" required>
            </div>
            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%;">
                Inject BDT into Pool
            </button>
        </form>
    </div>

    <!-- WITHDRAW BDT -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">➖ Withdraw BDT from Pool</h2>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="withdraw_bdt">
            <div class="admin-form-group">
                <label class="admin-label">BDT Amount to Withdraw</label>
                <input type="number" step="0.01" min="100" name="amount" class="admin-input" placeholder="e.g. 10000.00" required>
            </div>
            <button type="submit" class="admin-btn admin-btn-danger" style="width: 100%;">
                Withdraw BDT from Pool
            </button>
        </form>
    </div>
</div>

<!-- LIQUIDITY LEDGER AUDIT LOG -->
<div class="admin-card">
    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="admin-card-title">📜 Liquidity Pool Audit Ledger</h2>
        <span style="font-size: 12px; color: var(--text-dim);">Live Fee Routing & Settlement Records</span>
    </div>
    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Amount (BDT)</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Source</th>
                    <th>Reference</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledgerEntries)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--text-dim);">No ledger records yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($ledgerEntries as $entry): ?>
                        <tr>
                            <td style="color: var(--text-dim);">#<?= $entry['id'] ?></td>
                            <td><span class="badge badge-pending"><?= htmlspecialchars(strtoupper($entry['type'])) ?></span></td>
                            <td style="font-weight: 800; color: <?= (float)$entry['amount'] >= 0 ? '#10b981' : '#ef4444' ?>;">
                                <?= (float)$entry['amount'] >= 0 ? '+' : '' ?>৳ <?= number_format((float)$entry['amount'], 2) ?>
                            </td>
                            <td>৳ <?= number_format((float)$entry['balance_before'], 2) ?></td>
                            <td>৳ <?= number_format((float)$entry['balance_after'], 2) ?></td>
                            <td style="color: var(--text-main); font-weight: 600;"><?= htmlspecialchars($entry['source']) ?></td>
                            <td style="color: var(--text-muted); font-size: 11.5px;"><?= htmlspecialchars($entry['reference']) ?></td>
                            <td style="color: var(--text-dim); font-size: 11.5px;"><?= date('d M, h:i A', strtotime($entry['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
