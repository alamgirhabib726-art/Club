<?php
/**
 * UNMOOR CLUB - ADMIN SYSTEM LEDGER & TREASURY
 */

require_once __DIR__ . "/guard.php";

/* ================= SYSTEM USER ================= */
$stmt = $db->prepare("
    SELECT id, coins
    FROM users
    WHERE role = 'system'
    LIMIT 1
");
$stmt->execute();
$system = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$system) {
    // If system account doesn't exist, create it
    $now = date('Y-m-d H:i:s');
    $stmtSys = $db->prepare("INSERT INTO users (name, phone, role, status, coins, created_at) VALUES ('SYSTEM', '00000000000', 'system', 'active', 1000000, ?)");
    $stmtSys->execute([$now]);
    $system = ['id' => $db->lastInsertId(), 'coins' => 1000000];
}

$msg = $err = "";

/* ================= CASH OUT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cash = round((float)($_POST['cashout'] ?? 0), 2);

    if ($cash <= 0) {
        $err = "Invalid cashout amount.";
    } elseif ($cash > $system['coins']) {
        $err = "Insufficient system treasury balance.";
    } else {
        $db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("
                UPDATE users
                SET coins = coins - ?
                WHERE id = ?
            ");
            $stmt->execute([$cash, $system['id']]);

            $stmt = $db->prepare("
                INSERT INTO coin_history (user_id, amount, type, source, reference, created_at)
                VALUES (?, ?, 'system_cashout', 'SYSTEM_CASHOUT', 'System Treasury Cashout', ?)
            ");
            $stmt->execute([$system['id'], -$cash, $now]);

            $stmt = $db->prepare("
                INSERT INTO admin_balance_logs (admin_id, user_id, amount, note, created_at)
                VALUES (?, ?, ?, 'System Treasury Cashout', ?)
            ");
            $stmt->execute([$admin['id'], $system['id'], -$cash, $now]);

            $db->commit();
            $msg = "System treasury cashout of 🪙 $cash processed successfully.";
            $system['coins'] -= $cash;
        } catch (Throwable $e) {
            $db->rollBack();
            $err = "Cashout failed: " . $e->getMessage();
        }
    }
}

/* ================= FETCH SYSTEM LEDGER ================= */
$stmt = $db->prepare("
    SELECT ch.id, ch.amount, ch.source, ch.created_at
    FROM coin_history ch
    WHERE ch.user_id = ?
    ORDER BY ch.id DESC
    LIMIT 100
");
$stmt->execute([$system['id']]);
$ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'System Ledger';
$activeNav = 'system_ledger.php';
$pageSubtitle = 'Master protocol treasury, liquidity reserves, and administrative coin audits.';

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

<!-- TREASURY STATS -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">System Treasury Vault</span>
            <div class="admin-stat-icon" style="color: #facc15;">🏛️</div>
        </div>
        <div class="admin-stat-value">🪙 <?= number_format($system['coins'], 2) ?></div>
        <div class="admin-stat-subtext">
            <span>Protocol reserve balance</span>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Ledger Transactions</span>
            <div class="admin-stat-icon" style="color: #38bdf8;">📊</div>
        </div>
        <div class="admin-stat-value"><?= count($ledger) ?></div>
        <div class="admin-stat-subtext">
            <span>Recent treasury journal entries</span>
        </div>
    </div>
</div>

<!-- CASHOUT / REBALANCE ACTION -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">💸 System Treasury Cashout / Rebalance</h2>
    </div>

    <form method="post" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <div class="admin-form-group" style="margin-bottom: 0; flex: 1; max-width: 320px;">
            <label class="admin-label">Coin Amount to Cashout</label>
            <input type="number" step="0.01" min="1" max="<?= (float)$system['coins'] ?>" name="cashout" class="admin-input" placeholder="e.g. 500.00" required>
        </div>

        <button type="submit" class="admin-btn admin-btn-danger" style="height: 46px;" onclick="return confirm('Execute system vault cashout?')">
            ⚡ Withdraw from Treasury
        </button>
    </form>
</div>

<!-- LEDGER TABLE -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📜 Treasury Journal Entries</h2>
    </div>

    <?php if (empty($ledger)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No system ledger entries recorded yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Entry ID</th>
                        <th>Amount</th>
                        <th>Transaction Source / Memo</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ledger as $l): ?>
                        <tr>
                            <td>#<?= $l['id'] ?></td>
                            <td>
                                <strong style="color: <?= $l['amount'] < 0 ? '#ef4444' : '#22c55e' ?>; font-size: 14px;">
                                    <?= $l['amount'] > 0 ? '+' : '' ?><?= number_format($l['amount'], 2) ?> UC
                                </strong>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-info" style="font-family: monospace;">
                                    <?= htmlspecialchars($l['source'] ?? 'SYSTEM') ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--admin-text-muted);">
                                    <?= date("d M Y • h:i A", strtotime($l['created_at'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
