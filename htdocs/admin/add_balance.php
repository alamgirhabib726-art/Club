<?php
/**
 * UNMOOR CLUB - ADMIN BALANCE & COIN ADJUSTMENT ENGINE
 */

require_once __DIR__ . "/guard.php";

$msg = '';
$err = '';

$prefillPhone = trim($_GET['phone'] ?? '');
$prefillUserId = (int)($_GET['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = trim($_POST['phone'] ?? '');
    $userId   = (int)($_POST['user_id'] ?? 0);
    $action   = $_POST['action'] ?? 'add'; // 'add' or 'deduct'
    $amount   = (float)($_POST['amount'] ?? 0);
    $note     = trim($_POST['note'] ?? '');

    if ($amount <= 0) {
        $err = "Amount must be greater than zero.";
    } else {
        $user = null;
        if ($userId > 0) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($phone !== '') {
            $stmt = $db->prepare("SELECT * FROM users WHERE phone=? LIMIT 1");
            $stmt->execute([$phone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$user) {
            $err = "Specified member could not be found.";
        } else {
            $delta = ($action === 'deduct') ? -$amount : $amount;

            if ($action === 'deduct' && (float)$user['coins'] < $amount) {
                $err = "User only has 🪙 " . number_format($user['coins'], 2) . " Coins. Cannot deduct " . number_format($amount, 2);
            } else {
                $db->beginTransaction();
                try {
                    $now = date('Y-m-d H:i:s');
                    $stmt = $db->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                    $stmt->execute([$delta, $user['id']]);

                    $stmt = $db->prepare("
                        INSERT INTO coin_history (user_id, amount, type, source, reference, created_at)
                        VALUES (?, ?, 'admin_adjustment', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user['id'],
                        $delta,
                        ($action === 'add' ? 'ADMIN_CREDIT' : 'ADMIN_DEBIT'),
                        ($note ?: ($action === 'add' ? 'Admin Credited Balance' : 'Admin Debited Balance')),
                        $now
                    ]);

                    $stmt = $db->prepare("
                        INSERT INTO admin_balance_logs (admin_id, user_id, amount, note, created_at)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $memo = "[COINS " . strtoupper($action) . "] " . ($note ?: 'Admin coin adjustment');
                    $stmt->execute([$admin['id'], $user['id'], $delta, $memo, $now]);

                    log_admin_action($db, $admin['id'], "Adjusted user #{$user['id']} coins by $delta");

                    $db->commit();
                    $msg = "Successfully " . ($action === 'add' ? 'credited' : 'debited') . " 🪙 " . number_format($amount, 2) . " Coins for " . htmlspecialchars($user['name']);
                } catch (Throwable $e) {
                    $db->rollBack();
                    $err = "Adjustment error: " . $e->getMessage();
                }
            }
        }
    }
}

/* FETCH RECENT USERS FOR QUICK SELECTION */
$users = $db->query("SELECT id, name, phone, coins FROM users ORDER BY id DESC LIMIT 150")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Adjust Member Balance';
$activeNav = 'add_balance.php';
$pageSubtitle = 'Safely inject or deduct club coins (🪙) from member accounts.';

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

<div class="admin-card" style="max-width: 650px; margin: 0 auto;">
    <div class="admin-card-header">
        <h2 class="admin-card-title">💸 Coin Adjustment Tool</h2>
        <a href="balance_logs.php" class="admin-btn admin-btn-secondary admin-btn-sm">
            📜 Adjustment Logs
        </a>
    </div>

    <form method="post" id="balanceAdjustForm">
        
        <div class="admin-form-group">
            <label class="admin-label">Select Target Member</label>
            <select name="user_id" class="admin-select" required id="userSelect">
                <option value="">-- Choose Member from Registry --</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($prefillUserId === (int)$u['id'] || $prefillPhone === $u['phone']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['phone']) ?>) • 🪙 <?= number_format($u['coins'], 2) ?> Coins
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="admin-form-group">
            <label class="admin-label">Adjustment Direction</label>
            <select name="action" class="admin-select" id="actionSelect">
                <option value="add">➕ Credit / Inject Coins (+)</option>
                <option value="deduct">➖ Debit / Deduct Coins (-)</option>
            </select>
        </div>

        <div class="admin-form-group">
            <label class="admin-label">Adjustment Amount (🪙 Coins)</label>
            <input type="number" step="0.01" min="0.01" name="amount" id="amountInput" class="admin-input" placeholder="e.g. 500.00" required>
        </div>

        <div class="admin-form-group">
            <label class="admin-label">Internal Audit Memo / Reason</label>
            <input type="text" name="note" class="admin-input" placeholder="e.g. Tournament reward, manual refund, corrections">
        </div>

        <button type="submit" class="admin-btn admin-btn-primary admin-btn-block" style="width: 100%; height: 46px;">
            ⚡ Execute Coin Adjustment
        </button>
    </form>
</div>

<script>
document.getElementById('balanceAdjustForm').addEventListener('submit', function(e) {
    if (this.dataset.confirmed === 'true') return;
    e.preventDefault();

    const userSel = document.getElementById('userSelect');
    const userText = userSel.options[userSel.selectedIndex]?.text || 'Selected Member';
    const act = document.getElementById('actionSelect').value === 'add' ? 'Credit (+)' : 'Debit (-)';
    const amt = parseFloat(document.getElementById('amountInput').value || 0).toFixed(2);

    window.showAppConfirm({
        title: 'Confirm Coin Adjustment',
        message: `Execute <strong>${act}</strong> of <strong>🪙 ${amt} Coins</strong> for:<br><br><span style="color:#ffffff; font-size:13px;">${userText}</span>`,
        icon: '⚡',
        danger: act.includes('Debit'),
        type: act.includes('Debit') ? 'danger' : 'primary',
        confirmText: 'Confirm & Execute',
        cancelText: 'Review Form',
        onConfirm: () => {
            this.dataset.confirmed = 'true';
            this.submit();
        }
    });
});
</script>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
