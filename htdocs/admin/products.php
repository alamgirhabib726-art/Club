<?php
/**
 * UNMOOR CLUB - ADMIN PRODUCTS MANAGEMENT
 */

require_once __DIR__ . "/guard.php";

$msg = '';
$err = '';

/* UPDATE PRODUCT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $price = (float)$_POST['price'];
    $discount = (float)$_POST['discount'];
    $delivery_time = trim($_POST['delivery_time'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;

    $db->prepare("
        UPDATE products
        SET price = ?, discount = ?, delivery_time = ?, active = ?
        WHERE id = ?
    ")->execute([$price, $discount, $delivery_time, $active, $id]);

    log_admin_action($db, $admin['id'], "Updated product #$id settings");

    $msg = "Product updated successfully.";
}

/* FETCH PRODUCTS */
$products = $db->query("SELECT * FROM products ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Products Catalog';
$activeNav = 'products.php';
$pageSubtitle = 'Configure items, pricing, delivery schedules, and availability.';

require_once __DIR__ . "/layout_top.php";
?>

<?php if ($msg): ?>
    <div class="admin-alert admin-alert-success">
        <span>✅</span>
        <div><?= htmlspecialchars($msg) ?></div>
    </div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📦 Store Products &amp; Services (<?= count($products) ?>)</h2>
        <a href="purchase_orders.php" class="admin-btn admin-btn-secondary admin-btn-sm">
            🛍️ Purchase Orders Queue
        </a>
    </div>

    <?php if (empty($products)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No products configured.</p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px;">
            <?php foreach ($products as $p): ?>
                <div style="background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-lg); padding: 20px;">
                    <form method="post">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                            <strong style="font-size: 16px; color: #ffffff;">
                                <?= htmlspecialchars($p['name'] ?? 'Product #'.$p['id']) ?>
                            </strong>
                            <?php if (!empty($p['active'])): ?>
                                <span class="admin-badge admin-badge-success">ACTIVE</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-danger">INACTIVE</span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Price (৳ BDT)</label>
                            <input type="number" step="0.01" name="price" value="<?= (float)($p['price'] ?? 0) ?>" class="admin-input" required>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Discount (৳ BDT)</label>
                            <input type="number" step="0.01" name="discount" value="<?= (float)($p['discount'] ?? 0) ?>" class="admin-input" required>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Delivery Schedule / Time</label>
                            <input type="text" name="delivery_time" value="<?= htmlspecialchars($p['delivery_time'] ?? '') ?>" placeholder="e.g. Instant / 24 Hours" class="admin-input">
                        </div>

                        <div class="admin-form-group" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="active" id="prod_active_<?= $p['id'] ?>" <?= !empty($p['active']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: var(--admin-accent);">
                            <label for="prod_active_<?= $p['id'] ?>" class="admin-label" style="margin: 0; cursor: pointer; color: #ffffff;">Enable Product in Store</label>
                        </div>

                        <button type="submit" class="admin-btn admin-btn-primary admin-btn-block" style="width: 100%;">
                            💾 Save Product Settings
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
