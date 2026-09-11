<?php
/**
 * UNMOOR CLUB - ADMIN PRODUCTS MANAGEMENT
 */

require_once __DIR__ . "/guard.php";

$msg = '';
$err = '';

/* UPDATE OR CREATE PRODUCT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $delivery_time = trim($_POST['delivery_time'] ?? 'Instant');
        $active = isset($_POST['active']) ? 1 : 0;
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
        $type = preg_replace('/[^a-zA-Z0-9_]+/', '_', strtolower(trim($name)));

        if ($name === '' || $price <= 0) {
            $err = "Product name and positive price are required.";
        } else {
            $ins = $db->prepare("
                INSERT INTO products (name, title, slug, type, price, discount, delivery_time, active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            $ins->execute([$name, $name, $slug, $type, $price, $discount, $delivery_time, $active]);
            $newId = $db->lastInsertId();
            log_admin_action($db, $admin['id'], "Created new product #$newId: $name");
            $msg = "New product '$name' created and linked to user buy page!";
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        log_admin_action($db, $admin['id'], "Deleted product #$id");
        $msg = "Product #$id removed.";
    } else {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $price = (float)$_POST['price'];
        $discount = (float)$_POST['discount'];
        $delivery_time = trim($_POST['delivery_time'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        $db->prepare("
            UPDATE products
            SET name = ?, title = ?, price = ?, discount = ?, delivery_time = ?, active = ?
            WHERE id = ?
        ")->execute([$name, $name, $price, $discount, $delivery_time, $active, $id]);

        log_admin_action($db, $admin['id'], "Updated product #$id settings ($name, ৳$price)");

        $msg = "Product '$name' updated! Linked instantly with user store (/buy.php).";
    }
}

/* FETCH PRODUCTS */
$products = $db->query("SELECT * FROM products ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Products Catalog & Store Settings';
$activeNav = 'products.php';
$pageSubtitle = 'Configure items, pricing, discounts, and live store availability linked with user panel (/buy.php).';

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
        <span>⚠️</span>
        <div><?= htmlspecialchars($err) ?></div>
    </div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom: 24px;">
    <div class="admin-card-header">
        <h2 class="admin-card-title">➕ Add New Store Product</h2>
    </div>
    <form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; align-items: end;">
        <input type="hidden" name="action" value="create">
        
        <div class="admin-form-group" style="margin: 0;">
            <label class="admin-label">Product Name</label>
            <input type="text" name="name" placeholder="e.g. ⚡ Mega Click Pack" class="admin-input" required>
        </div>

        <div class="admin-form-group" style="margin: 0;">
            <label class="admin-label">Price (৳ BDT)</label>
            <input type="number" step="0.01" name="price" placeholder="100.00" class="admin-input" required>
        </div>

        <div class="admin-form-group" style="margin: 0;">
            <label class="admin-label">Discount (৳ BDT)</label>
            <input type="number" step="0.01" name="discount" value="0.00" class="admin-input">
        </div>

        <div class="admin-form-group" style="margin: 0;">
            <label class="admin-label">Delivery Schedule</label>
            <input type="text" name="delivery_time" value="Instant" class="admin-input">
        </div>

        <div class="admin-form-group" style="margin: 0; display: flex; align-items: center; gap: 8px; padding-bottom: 8px;">
            <input type="checkbox" name="active" id="new_prod_active" checked style="width: 18px; height: 18px; accent-color: var(--admin-accent);">
            <label for="new_prod_active" class="admin-label" style="margin: 0; cursor: pointer; color: #ffffff;">Active in Store</label>
        </div>

        <div>
            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%;">
                ➕ Add Product to Store
            </button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📦 Linked Store Products (<?= count($products) ?>)</h2>
        <div style="display: flex; gap: 8px;">
            <a href="/buy.php" target="_blank" class="admin-btn admin-btn-secondary admin-btn-sm">
                👁️ Open User Store (/buy.php)
            </a>
            <a href="purchase_orders.php" class="admin-btn admin-btn-secondary admin-btn-sm">
                🛍️ Purchase Orders Queue
            </a>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No products configured.</p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px;">
            <?php foreach ($products as $p): ?>
                <?php 
                $pPrice = (float)($p['price'] ?? 0);
                $pDisc = (float)($p['discount'] ?? 0);
                $pFinal = max(0, round($pPrice - ($pDisc > 1 ? $pDisc : ($pPrice * $pDisc / 100)), 2));
                $pCoins = round($pFinal * 10, 2);
                ?>
                <div style="background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-lg); padding: 20px;">
                    <form method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                            <strong style="font-size: 16px; color: #ffffff;">
                                #<?= $p['id'] ?> <?= htmlspecialchars($p['name'] ?? 'Product') ?>
                            </strong>
                            <?php if (!empty($p['active'])): ?>
                                <span class="admin-badge admin-badge-success">ACTIVE IN STORE</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-danger">HIDDEN</span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Product Name / Title</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($p['name'] ?? $p['title'] ?? '') ?>" class="admin-input" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div class="admin-form-group">
                                <label class="admin-label">Regular Price (৳)</label>
                                <input type="number" step="0.01" name="price" value="<?= $pPrice ?>" class="admin-input" required>
                            </div>

                            <div class="admin-form-group">
                                <label class="admin-label">Discount (৳)</label>
                                <input type="number" step="0.01" name="discount" value="<?= $pDisc ?>" class="admin-input" required>
                            </div>
                        </div>

                        <!-- Live User Display Calculation -->
                        <div style="background: rgba(255, 255, 255, 0.04); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 12.5px; line-height: 1.5;">
                            <div style="color: var(--admin-text-muted);">User Store Preview:</div>
                            <div style="color: var(--admin-accent); font-weight: 800;">
                                Final: ৳<?= number_format($pFinal, 2) ?> | 🪙 <?= number_format($pCoins, 2) ?> Coins
                            </div>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Delivery Schedule / Time</label>
                            <input type="text" name="delivery_time" value="<?= htmlspecialchars($p['delivery_time'] ?? 'Instant') ?>" placeholder="e.g. Instant / 24 Hours" class="admin-input">
                        </div>

                        <div class="admin-form-group" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="active" id="prod_active_<?= $p['id'] ?>" <?= !empty($p['active']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: var(--admin-accent);">
                            <label for="prod_active_<?= $p['id'] ?>" class="admin-label" style="margin: 0; cursor: pointer; color: #ffffff;">Visible &amp; Purchasable in User Store</label>
                        </div>

                        <div style="display: flex; gap: 8px; margin-top: 14px;">
                            <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1;">
                                💾 Save Settings
                            </button>
                            <a href="/buy.php?id=<?= $p['id'] ?>" target="_blank" class="admin-btn admin-btn-secondary" style="padding: 0 12px; display: inline-flex; align-items: center;" title="View in User Store">
                                👁️ View
                            </a>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
