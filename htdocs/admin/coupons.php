<?php
/**
 * UNMOOR CLUB - ADMIN COUPONS MANAGEMENT & GENERATOR
 */

require_once __DIR__ . "/guard.php";

/* ================= COUPON CODE GENERATOR ================= */
function generateUniqueCoupon(PDO $db, string $type): string {
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '123456789';

    do {
        $rand = $letters[rand(0,25)] . $numbers[rand(0,8)] . $letters[rand(0,25)] . $numbers[rand(0,8)];
        $code = 'UNM-' . ($type === 'apply' ? 'REG' : 'DEP') . '-' . $rand;

        $chk = $db->prepare("SELECT id FROM coupons WHERE code=? LIMIT 1");
        $chk->execute([$code]);
    } while ($chk->fetch());

    return $code;
}

$msg = $err = "";

if (isset($_SESSION['coupon_success'])) {
    $msg = $_SESSION['coupon_success'];
    unset($_SESSION['coupon_success']);
}

/* ================= CREATE COUPON ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $type   = $_POST['type'] ?? '';

    if ($amount <= 0) {
        $err = "Please enter a valid amount.";
    } elseif (!in_array($type, ['apply','deposit'], true)) {
        $err = "Invalid coupon type selected.";
    } else {
        try {
            $code = generateUniqueCoupon($db, $type);

            $now = date('Y-m-d H:i:s');
            $db->prepare("
                INSERT INTO coupons (code, amount, type, status, created_at)
                VALUES (?, ?, ?, 'active', ?)
            ")->execute([$code, $amount, $type, $now]);

            log_admin_action($db, $admin['id'], "Created coupon $code for 🪙$amount Coins");

            $_SESSION['coupon_success'] = "Generated coupon code: $code";
            header("Location: coupons.php");
            exit;
        } catch (Throwable $e) {
            $err = "Failed to generate coupon: " . $e->getMessage();
        }
    }
}

/* ================= FETCH ACTIVE & RECENT COUPONS ================= */
$coupons = $db->query("
    SELECT c.id, c.code, c.amount, c.type, c.status, c.created_at, c.used_at, u.name as used_by_name, u.phone as used_by_phone
    FROM coupons c
    LEFT JOIN users u ON u.id = c.used_by
    ORDER BY c.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Coupon Management';
$activeNav = 'coupons.php';
$pageSubtitle = 'Create unique prepaid registration and deposit redemption coupons.';

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

<!-- GENERATE COUPON -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">🎟️ Generate New Redeemable Coupon</h2>
    </div>

    <form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; align-items: flex-end;">
        <div class="admin-form-group" style="margin-bottom: 0;">
            <label class="admin-label">Coupon Purpose</label>
            <select name="type" class="admin-select" required>
                <option value="apply">Account Application / Registration</option>
                <option value="deposit">Account Coin Deposit</option>
            </select>
        </div>

        <div class="admin-form-group" style="margin-bottom: 0;">
            <label class="admin-label">Value Amount (🪙 Coins)</label>
            <input type="number" step="1" min="1" name="amount" class="admin-input" placeholder="e.g. 500" required>
        </div>

        <div>
            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; height: 46px;">
                ⚡ Generate Unique Code
            </button>
        </div>
    </form>
</div>

<!-- ALL COUPONS TABLE -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📜 All Generated Coupons (<?= count($coupons) ?>)</h2>
        <a href="coupon_history.php" class="admin-btn admin-btn-secondary admin-btn-sm">
            📜 Redemption History
        </a>
    </div>

    <?php if (empty($coupons)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No coupons created yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Coupon Code</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Redeemed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $c): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--admin-gold); font-family: monospace; font-size: 15px; letter-spacing: 1px;">
                                    <?= htmlspecialchars($c['code']) ?>
                                </strong>
                                <button type="button" data-copy="<?= htmlspecialchars($c['code']) ?>" class="admin-btn admin-btn-secondary admin-btn-sm" style="margin-left: 8px; padding: 2px 8px; font-size: 11px;">
                                    📋 Copy
                                </button>
                            </td>
                            <td>
                                <?php if ($c['type'] === 'apply'): ?>
                                    <span class="admin-badge admin-badge-info">REGISTRATION</span>
                                <?php else: ?>
                                    <span class="admin-badge" style="background: rgba(168, 85, 247, 0.2); color: #c084fc;">DEPOSIT</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #ffffff;">🪙 <?= number_format($c['amount'], 2) ?> Coins</strong>
                            </td>
                            <td>
                                <?php if ($c['status'] === 'used'): ?>
                                    <span class="admin-badge admin-badge-success">REDEEMED</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-warning">ACTIVE / UNUSED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--admin-text-muted);">
                                    <?= date("d M Y • h:i A", strtotime($c['created_at'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($c['used_by_name'])): ?>
                                    <strong><?= htmlspecialchars($c['used_by_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--admin-text-dim);"><?= htmlspecialchars($c['used_by_phone'] ?? '') ?></div>
                                <?php else: ?>
                                    <span style="color: var(--admin-text-dim); font-size: 12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
