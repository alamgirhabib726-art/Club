<?php
/**
 * UNMOOR CLUB - ADMIN LIQUIDITY POOL & TRADING RESERVES
 * File: /admin/liquidity.php
 * Comprehensive audit & management of the BDT Liquidity Pool, Trading UC reserves,
 * dynamic market price impacts on UC Coin, fee revenue tracking, and liquidity ledger.
 */

require_once __DIR__ . "/guard.php";
require_once __DIR__ . "/../trade/engine/fee_engine.php";
require_once __DIR__ . "/../trade/engine/market_engine.php";

$msg = $err = "";

// Fetch main liquidity pool record
$pool = $db->query("SELECT * FROM liquidity_pool WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$pool) {
    $db->exec("INSERT INTO liquidity_pool (id, bdt_reserve, tuc_reserve, total_fee_revenue_bdt, conversion_fee_revenue_bdt, realized_pnl_bdt, status)
        VALUES (1, 500000.0000, 250000.0000, 0.0000, 0.0000, 0.0000, 'active')");
    $pool = $db->query("SELECT * FROM liquidity_pool WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// Current reserves
$curBdt = (float)($pool['bdt_reserve'] ?? 500000.0);
$curTuc = (float)(($pool['tuc_reserve'] ?? 0) > 0 ? $pool['tuc_reserve'] : 250000.0);
$currentBackingPrice = round($curBdt / $curTuc, 4);

// Current live market state
$marketState = MarketEngine::getMarketState($db);
$currentMarketPrice = (float)($marketState['price'] ?? $currentBackingPrice);

// Handle liquidity adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

    if ($action === 'inject_bdt' && $amount > 0) {
        $newBdt = $curBdt + $amount;
        $newPrice = round($newBdt / $curTuc, 4);
        
        $db->prepare("UPDATE liquidity_pool SET bdt_reserve = ?, updated_at = $nowExpr WHERE id = 1")->execute([$newBdt]);
        
        // Apply instant market shock to UC Coin
        $shock = MarketEngine::applyLiquidityShock($db, $newPrice, "Admin BDT Liquidity Injection (+৳$amount BDT)");
        $pctChange = $shock['percent_change'] ?? 0;
        $sign = ($pctChange >= 0) ? '+' : '';

        $refNote = "Admin BDT Injection (+৳" . number_format($amount, 2) . ") ➔ UC Price shifted from ৳" . number_format($currentMarketPrice, 4) . " to ৳" . number_format($newPrice, 4) . " ({$sign}{$pctChange}%)";
        
        $db->prepare("INSERT INTO liquidity_ledger (type, amount, asset, balance_before, balance_after, source, reference, created_at)
            VALUES ('admin_inject', ?, 'BDT', ?, ?, 'Admin Panel', ?, $nowExpr)")
            ->execute([$amount, $curBdt, $newBdt, $refNote]);

        log_admin_action($db, $admin['id'], "Injected ৳ $amount BDT into Liquidity Pool (UC Coin: ৳$currentMarketPrice ➔ ৳$newPrice)");
        $msg = "Successfully injected ৳ " . number_format($amount, 2) . " BDT into Liquidity Pool. UC Coin market price rose: ৳ " . number_format($currentMarketPrice, 4) . " ➔ ৳ " . number_format($newPrice, 4) . " ({$sign}{$pctChange}%).";
        
        $pool['bdt_reserve'] = $newBdt;
        $curBdt = $newBdt;
        $currentMarketPrice = $newPrice;
        $currentBackingPrice = $newPrice;

    } elseif ($action === 'withdraw_bdt' && $amount > 0 && $amount <= $curBdt) {
        $newBdt = $curBdt - $amount;
        $newPrice = round($newBdt / $curTuc, 4);
        
        $db->prepare("UPDATE liquidity_pool SET bdt_reserve = ?, updated_at = $nowExpr WHERE id = 1")->execute([$newBdt]);

        // Apply instant market shock to UC Coin (price drop)
        $shock = MarketEngine::applyLiquidityShock($db, $newPrice, "Admin BDT Liquidity Withdrawal (-৳$amount BDT)");
        $pctChange = $shock['percent_change'] ?? 0;
        $sign = ($pctChange >= 0) ? '+' : '';

        $refNote = "Admin BDT Withdrawal (-৳" . number_format($amount, 2) . ") ➔ UC Price shifted from ৳" . number_format($currentMarketPrice, 4) . " to ৳" . number_format($newPrice, 4) . " ({$sign}{$pctChange}%)";

        $db->prepare("INSERT INTO liquidity_ledger (type, amount, asset, balance_before, balance_after, source, reference, created_at)
            VALUES ('admin_withdraw', ?, 'BDT', ?, ?, 'Admin Panel', ?, $nowExpr)")
            ->execute([-$amount, $curBdt, $newBdt, $refNote]);

        log_admin_action($db, $admin['id'], "Withdrew ৳ $amount BDT from Liquidity Pool (UC Coin: ৳$currentMarketPrice ➔ ৳$newPrice)");
        $msg = "Successfully withdrew ৳ " . number_format($amount, 2) . " BDT from Liquidity Pool. UC Coin market price adjusted: ৳ " . number_format($currentMarketPrice, 4) . " ➔ ৳ " . number_format($newPrice, 4) . " ({$sign}{$pctChange}%).";
        
        $pool['bdt_reserve'] = $newBdt;
        $curBdt = $newBdt;
        $currentMarketPrice = $newPrice;
        $currentBackingPrice = $newPrice;

    } elseif ($action === 'inject_tuc' && $amount > 0) {
        $newTuc = $curTuc + $amount;
        $newPrice = round($curBdt / $newTuc, 4);
        
        $db->prepare("UPDATE liquidity_pool SET tuc_reserve = ?, updated_at = $nowExpr WHERE id = 1")->execute([$newTuc]);
        
        // Apply instant market shock to UC Coin (supply increase -> price drop)
        $shock = MarketEngine::applyLiquidityShock($db, $newPrice, "Admin UC Liquidity Injection (+{$amount} UC)");
        $pctChange = $shock['percent_change'] ?? 0;
        $sign = ($pctChange >= 0) ? '+' : '';

        $refNote = "Admin UC Supply Injection (+{$amount} UC) ➔ UC Price shifted from ৳" . number_format($currentMarketPrice, 4) . " to ৳" . number_format($newPrice, 4) . " ({$sign}{$pctChange}%)";

        $db->prepare("INSERT INTO liquidity_ledger (type, amount, asset, balance_before, balance_after, source, reference, created_at)
            VALUES ('admin_inject_uc', ?, 'tUC', ?, ?, 'Admin Panel', ?, $nowExpr)")
            ->execute([$amount, $curTuc, $newTuc, $refNote]);

        log_admin_action($db, $admin['id'], "Injected $amount UC into Liquidity Pool (UC Coin: ৳$currentMarketPrice ➔ ৳$newPrice)");
        $msg = "Successfully injected " . number_format($amount, 2) . " UC into Liquidity Pool. UC Coin market price adjusted: ৳ " . number_format($currentMarketPrice, 4) . " ➔ ৳ " . number_format($newPrice, 4) . " ({$sign}{$pctChange}%).";
        
        $pool['tuc_reserve'] = $newTuc;
        $curTuc = $newTuc;
        $currentMarketPrice = $newPrice;
        $currentBackingPrice = $newPrice;

    } elseif ($action === 'withdraw_tuc' && $amount > 0 && $amount <= $curTuc) {
        $newTuc = $curTuc - $amount;
        $newPrice = round($curBdt / $newTuc, 4);
        
        $db->prepare("UPDATE liquidity_pool SET tuc_reserve = ?, updated_at = $nowExpr WHERE id = 1")->execute([$newTuc]);

        // Apply instant market shock to UC Coin (supply reduction -> price rise)
        $shock = MarketEngine::applyLiquidityShock($db, $newPrice, "Admin UC Liquidity Burn/Withdrawal (-{$amount} UC)");
        $pctChange = $shock['percent_change'] ?? 0;
        $sign = ($pctChange >= 0) ? '+' : '';

        $refNote = "Admin UC Supply Burn (-{$amount} UC) ➔ UC Price shifted from ৳" . number_format($currentMarketPrice, 4) . " to ৳" . number_format($newPrice, 4) . " ({$sign}{$pctChange}%)";

        $db->prepare("INSERT INTO liquidity_ledger (type, amount, asset, balance_before, balance_after, source, reference, created_at)
            VALUES ('admin_withdraw_uc', ?, 'tUC', ?, ?, 'Admin Panel', ?, $nowExpr)")
            ->execute([-$amount, $curTuc, $newTuc, $refNote]);

        log_admin_action($db, $admin['id'], "Withdrew/Burned $amount UC from Liquidity Pool (UC Coin: ৳$currentMarketPrice ➔ ৳$newPrice)");
        $msg = "Successfully withdrew " . number_format($amount, 2) . " UC from Liquidity Pool. UC Coin market price rose: ৳ " . number_format($currentMarketPrice, 4) . " ➔ ৳ " . number_format($newPrice, 4) . " ({$sign}{$pctChange}%).";
        
        $pool['tuc_reserve'] = $newTuc;
        $curTuc = $newTuc;
        $currentMarketPrice = $newPrice;
        $currentBackingPrice = $newPrice;

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

$pageTitle = 'Liquidity Pool & UC Coin Impact';
$activeNav = 'liquidity.php';
$pageSubtitle = 'Manage BDT & UC market liquidity reserves, automated fee routing, and direct UC Coin market price impacts.';

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

<!-- LIVE UC COIN MARKET IMPACT HERO CARD -->
<div style="background: linear-gradient(135deg, rgba(24, 26, 32, 0.95), rgba(30, 35, 41, 0.95)); border: 1px solid rgba(252, 213, 53, 0.25); border-radius: var(--admin-radius-lg); padding: 22px 24px; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <span style="font-size: 20px;">🪙</span>
                <h3 style="font-size: 18px; font-weight: 800; color: #ffffff; margin: 0;">UC Coin / BDT Automated Liquidity Link</h3>
                <span class="admin-badge admin-badge-success" style="font-size: 11px;">Active Market Link</span>
            </div>
            <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0; max-width: 680px; line-height: 1.5;">
                Every BDT and UC liquidity injection or withdrawal directly alters the backing ratio (<code style="color: var(--admin-gold); font-weight: 700;">Price = BDT Reserve ÷ UC Reserve</code>) and executes an instant price impact on live UC trading and user balances.
            </p>
        </div>
        <div style="display: flex; gap: 20px; align-items: center; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--admin-border); border-radius: 12px; padding: 12px 18px;">
            <div>
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--admin-text-dim);">Live UC Price</div>
                <div style="font-size: 22px; font-weight: 900; color: var(--admin-gold);">
                    ৳ <?= number_format($currentMarketPrice, 4) ?>
                </div>
            </div>
            <div style="width: 1px; height: 32px; background: var(--admin-border);"></div>
            <div>
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--admin-text-dim);">Pool Backing Ratio</div>
                <div style="font-size: 16px; font-weight: 800; color: #10b981;">
                    1 UC = ৳ <?= number_format($currentBackingPrice, 4) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- POOL METRIC STATS -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Liquidity Pool Reserve (BDT)</span>
            <div class="admin-stat-icon" style="color: #10b981;">💵</div>
        </div>
        <div class="admin-stat-value">৳ <?= number_format((float)$pool['bdt_reserve'], 2) ?></div>
        <div class="admin-stat-subtext">
            <span>Fiat settlement backing</span>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Trading UC Reserve in Pool</span>
            <div class="admin-stat-icon" style="color: #f59e0b;">🪙</div>
        </div>
        <div class="admin-stat-value"><?= number_format((float)$pool['tuc_reserve'], 2) ?> UC</div>
        <div class="admin-stat-subtext">
            <span>Circulating pool supply</span>
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

<!-- INJECT / DRAIN LIQUIDITY FORMS WITH LIVE SIMULATORS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <!-- 1. INJECT BDT -->
    <div class="admin-card">
        <div class="admin-card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <h2 class="admin-card-title">➕ Inject BDT Liquidity</h2>
            <span class="admin-badge admin-badge-success" style="font-size: 11px;">UC Price 📈 UP</span>
        </div>
        <form method="post" id="form-inject-bdt">
            <input type="hidden" name="action" value="inject_bdt">
            <div class="admin-form-group">
                <label class="admin-label">BDT Amount to Inject (৳)</label>
                <input type="number" step="0.01" min="10" name="amount" id="input-inject-bdt" class="admin-input" placeholder="e.g. 50000.00" required oninput="calcImpact('inject_bdt')">
                <div style="font-size: 11.5px; color: var(--admin-text-dim); margin-top: 4px;">Adds fiat backing; raises UC Coin market price.</div>
            </div>
            
            <!-- Live Impact Preview Box -->
            <div id="preview-inject-bdt" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 12.5px; display: none;">
                <div style="color: var(--admin-text-muted);">Simulated UC Price Impact:</div>
                <div id="text-inject-bdt" style="font-weight: 800; color: #10b981; font-size: 14px; margin-top: 2px;"></div>
            </div>

            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%;">
                Inject BDT & Boost UC Price
            </button>
        </form>
    </div>

    <!-- 2. WITHDRAW BDT -->
    <div class="admin-card">
        <div class="admin-card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <h2 class="admin-card-title">➖ Withdraw BDT from Pool</h2>
            <span class="admin-badge admin-badge-danger" style="font-size: 11px;">UC Price 📉 DOWN</span>
        </div>
        <form method="post" id="form-withdraw-bdt">
            <input type="hidden" name="action" value="withdraw_bdt">
            <div class="admin-form-group">
                <label class="admin-label">BDT Amount to Withdraw (৳)</label>
                <input type="number" step="0.01" min="10" max="<?= $curBdt ?>" name="amount" id="input-withdraw-bdt" class="admin-input" placeholder="e.g. 20000.00" required oninput="calcImpact('withdraw_bdt')">
                <div style="font-size: 11.5px; color: var(--admin-text-dim); margin-top: 4px;">Max withdrawable: ৳ <?= number_format($curBdt, 2) ?>. Lowers UC Coin market price.</div>
            </div>

            <!-- Live Impact Preview Box -->
            <div id="preview-withdraw-bdt" style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 12.5px; display: none;">
                <div style="color: var(--admin-text-muted);">Simulated UC Price Impact:</div>
                <div id="text-withdraw-bdt" style="font-weight: 800; color: #ef4444; font-size: 14px; margin-top: 2px;"></div>
            </div>

            <button type="submit" class="admin-btn admin-btn-danger" style="width: 100%;">
                Withdraw BDT from Pool
            </button>
        </form>
    </div>

    <!-- 3. INJECT UC RESERVE -->
    <div class="admin-card">
        <div class="admin-card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <h2 class="admin-card-title">🪙 Inject UC Supply</h2>
            <span class="admin-badge admin-badge-warning" style="font-size: 11px;">Dilution 📉 DOWN</span>
        </div>
        <form method="post" id="form-inject-tuc">
            <input type="hidden" name="action" value="inject_tuc">
            <div class="admin-form-group">
                <label class="admin-label">UC Amount to Inject into Pool</label>
                <input type="number" step="0.01" min="1" name="amount" id="input-inject-tuc" class="admin-input" placeholder="e.g. 50000.00" required oninput="calcImpact('inject_tuc')">
                <div style="font-size: 11.5px; color: var(--admin-text-dim); margin-top: 4px;">Increases circulating pool UC; adjusts price per UC down.</div>
            </div>

            <!-- Live Impact Preview Box -->
            <div id="preview-inject-tuc" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 12.5px; display: none;">
                <div style="color: var(--admin-text-muted);">Simulated UC Price Impact:</div>
                <div id="text-inject-tuc" style="font-weight: 800; color: #f59e0b; font-size: 14px; margin-top: 2px;"></div>
            </div>

            <button type="submit" class="admin-btn admin-btn-secondary" style="width: 100%;">
                Inject UC Supply into Pool
            </button>
        </form>
    </div>

    <!-- 4. WITHDRAW / BURN UC RESERVE -->
    <div class="admin-card">
        <div class="admin-card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <h2 class="admin-card-title">🔥 Withdraw / Burn UC</h2>
            <span class="admin-badge admin-badge-success" style="font-size: 11px;">Scarcity 📈 UP</span>
        </div>
        <form method="post" id="form-withdraw-tuc">
            <input type="hidden" name="action" value="withdraw_tuc">
            <div class="admin-form-group">
                <label class="admin-label">UC Amount to Withdraw / Burn</label>
                <input type="number" step="0.01" min="1" max="<?= $curTuc ?>" name="amount" id="input-withdraw-tuc" class="admin-input" placeholder="e.g. 25000.00" required oninput="calcImpact('withdraw_tuc')">
                <div style="font-size: 11.5px; color: var(--admin-text-dim); margin-top: 4px;">Max withdrawable: <?= number_format($curTuc, 2) ?> UC. Increases scarcity & price.</div>
            </div>

            <!-- Live Impact Preview Box -->
            <div id="preview-withdraw-tuc" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 12.5px; display: none;">
                <div style="color: var(--admin-text-muted);">Simulated UC Price Impact:</div>
                <div id="text-withdraw-tuc" style="font-weight: 800; color: #10b981; font-size: 14px; margin-top: 2px;"></div>
            </div>

            <button type="submit" class="admin-btn admin-btn-secondary" style="width: 100%;">
                Withdraw / Burn UC from Pool
            </button>
        </form>
    </div>
</div>

<!-- LIQUIDITY LEDGER AUDIT LOG -->
<div class="admin-card">
    <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 class="admin-card-title">📜 Liquidity Pool Audit Ledger</h2>
            <span style="font-size: 12px; color: var(--text-dim);">Real-time recording of injections, withdrawals, fee routings, and market impacts</span>
        </div>
    </div>
    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Asset</th>
                    <th>Amount</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Source</th>
                    <th>Reference & Market Effect</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledgerEntries)): ?>
                    <tr><td colspan="9" style="text-align: center; color: var(--text-dim); padding: 24px;">No ledger records yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($ledgerEntries as $entry): 
                        $isPositive = ((float)$entry['amount'] >= 0);
                        $assetSymbol = ($entry['asset'] === 'tUC' || $entry['asset'] === 'UC') ? '🪙 UC' : '৳ BDT';
                    ?>
                        <tr>
                            <td style="color: var(--text-dim);">#<?= $entry['id'] ?></td>
                            <td><span class="badge badge-pending"><?= htmlspecialchars(strtoupper($entry['type'])) ?></span></td>
                            <td style="font-weight: 700; color: var(--admin-gold);"><?= htmlspecialchars($entry['asset'] ?? 'BDT') ?></td>
                            <td style="font-weight: 800; color: <?= $isPositive ? '#10b981' : '#ef4444' ?>;">
                                <?= $isPositive ? '+' : '' ?><?= number_format((float)$entry['amount'], 2) ?>
                            </td>
                            <td><?= number_format((float)$entry['balance_before'], 2) ?></td>
                            <td><?= number_format((float)$entry['balance_after'], 2) ?></td>
                            <td style="color: var(--text-main); font-weight: 600;"><?= htmlspecialchars($entry['source']) ?></td>
                            <td style="color: var(--text-muted); font-size: 12px; max-width: 300px;"><?= htmlspecialchars($entry['reference']) ?></td>
                            <td style="color: var(--text-dim); font-size: 11.5px; white-space: nowrap;"><?= date('d M, h:i A', strtotime($entry['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const curBdt = <?= (float)$curBdt ?>;
const curTuc = <?= (float)$curTuc ?>;
const curPrice = <?= (float)$currentMarketPrice ?>;

function calcImpact(type) {
    let inputVal = 0;
    let newBdt = curBdt;
    let newTuc = curTuc;
    let prevBox = document.getElementById('preview-' + type.replace('_', '-'));
    let textBox = document.getElementById('text-' + type.replace('_', '-'));
    let inputElem = document.getElementById('input-' + type.replace('_', '-'));

    if (!inputElem || !prevBox || !textBox) return;

    inputVal = parseFloat(inputElem.value) || 0;
    if (inputVal <= 0) {
        prevBox.style.display = 'none';
        return;
    }

    if (type === 'inject_bdt') {
        newBdt = curBdt + inputVal;
    } else if (type === 'withdraw_bdt') {
        newBdt = Math.max(0, curBdt - inputVal);
    } else if (type === 'inject_tuc') {
        newTuc = curTuc + inputVal;
    } else if (type === 'withdraw_tuc') {
        newTuc = Math.max(1, curTuc - inputVal);
    }

    const newPrice = newTuc > 0 ? (newBdt / newTuc) : 0;
    const diff = newPrice - curPrice;
    const pct = curPrice > 0 ? ((diff / curPrice) * 100).toFixed(2) : '0.00';
    const sign = diff >= 0 ? '+' : '';

    prevBox.style.display = 'block';
    textBox.innerHTML = `৳ ${curPrice.toFixed(4)} ➔ ৳ ${newPrice.toFixed(4)} (${sign}${pct}%)`;
}
</script>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
