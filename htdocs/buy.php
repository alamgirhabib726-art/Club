<?php
/**
 * UNMOOR CLUB - BUY PRODUCT DETAILS
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$product = $_GET['product'] ?? '';

if ($product === '') {
    header("Location: dashboard.php");
    exit;
}

/* FETCH ACTIVE PRODUCT */
$stmt = $db->prepare("
    SELECT *
    FROM products
    WHERE (type = ? OR slug = ?) AND active = 1
    LIMIT 1
");
$stmt->execute([$product, $product]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("Product not available");
}

/* PRICE CALCULATION */
$price    = (float)$p['price'];
$discount = (float)($p['discount'] ?? 0);
$final    = max(0, round($price - ($discount > 1 ? $discount : ($price * $discount / 100)), 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($p['title'] ?? strtoupper($p['type'] ?? 'Product')) ?> • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Product Checkout", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 18px; font-weight: 900; color: #ffffff; margin-bottom: 12px;">
                📦 <?= htmlspecialchars($p['title'] ?? strtoupper($p['type'] ?? 'Product')) ?>
            </h3>

            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 13.5px;">
                    <span style="color: var(--text-muted);">Regular Price:</span>
                    <span style="text-decoration: line-through; color: var(--text-dim);">৳<?= number_format($price, 2) ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <div style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 13.5px; color: var(--accent-gold);">
                        <span>Discount:</span>
                        <span>- ৳<?= number_format($price - $final, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; padding: 8px 0 0; border-top: 1px dashed var(--border-color); margin-top: 6px; font-size: 16px; font-weight: 900;">
                    <span style="color: #ffffff;">Final Price:</span>
                    <span style="color: var(--accent-gold);">৳<?= number_format($final, 2) ?></span>
                </div>
            </div>

            <?php if (!empty($p['delivery_time'])): ?>
                <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                    🚚 <b>Delivery Time:</b> <?= htmlspecialchars($p['delivery_time']) ?>
                </div>
            <?php endif; ?>

            <a href="purchase_pay.php?product=<?= urlencode($p['slug'] ?? $p['type'] ?? '') ?>" class="btn btn-gold btn-block" style="padding: 14px; text-decoration: none; text-align: center;">
                Proceed to Payment ›
            </a>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
