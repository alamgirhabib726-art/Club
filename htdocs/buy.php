<?php
/**
 * UNMOOR CLUB - USER STORE & PRODUCT CHECKOUT
 * Fully linked with Admin Products Management (admin/products.php)
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$balances = get_user_balances($db, $uid);

// Check if user is active
if (($balances['status'] !== 'active' && $balances['status'] !== 'premium') || $balances['apply_status'] !== 'approved' || $balances['role'] === 'system') {
    die("ACCESS DENIED");
}

$productIdOrSlug = trim($_GET['id'] ?? ($_GET['product'] ?? ($_GET['slug'] ?? '')));

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

// Handle Instant Coin Purchase POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buy_with_coins') {
    header('Content-Type: application/json');
    $targetId = (int)($_POST['product_id'] ?? 0);

    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([$targetId]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        echo json_encode(['success' => false, 'error' => 'Product is currently inactive or not found.']);
        exit;
    }

    $rawPrice = (float)$prod['price'];
    $rawDisc  = (float)($prod['discount'] ?? 0);
    $finalBdt = max(0, round($rawPrice - ($rawDisc > 1 ? $rawDisc : ($rawPrice * $rawDisc / 100)), 2));
    $neededCoins = round($finalBdt * 10, 2);

    $res = lock_user_order($db, $uid, $neededCoins, $prod['id'], $prod['slug'] ?: ('prod_'.$prod['id']));

    if (!$res['success']) {
        echo json_encode(['success' => false, 'error' => $res['error']]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'order_id' => $res['order_id'],
        'product_name' => $prod['name'] ?: $prod['title'],
        'locked_coins' => $neededCoins,
        'balances' => $res['balances'],
        'message' => "Order #{$res['order_id']} placed successfully! 🪙 " . number_format($neededCoins, 2) . " reserved in locked balance pending delivery."
    ]);
    exit;
}

// Single Product Mode vs. Full Store Catalog Mode
$singleProduct = null;
if ($productIdOrSlug !== '') {
    $stmt = $db->prepare("
        SELECT *
        FROM products
        WHERE (id = ? OR slug = ? OR type = ?) AND active = 1
        LIMIT 1
    ");
    $stmt->execute([$productIdOrSlug, $productIdOrSlug, $productIdOrSlug]);
    $singleProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If no single product requested or not found, load catalog
$catalog = [];
if (!$singleProduct) {
    $catalog = $db->query("SELECT * FROM products WHERE active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $singleProduct ? htmlspecialchars($singleProduct['name'] ?: $singleProduct['title']) . ' • Checkout' : 'Club Store & Products' ?> • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .store-hero {
            background: linear-gradient(135deg, rgba(212, 160, 23, 0.12), rgba(19, 23, 34, 0.9));
            border: 1px solid rgba(212, 160, 23, 0.25);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .store-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .store-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }
        .store-card:hover {
            border-color: rgba(212, 160, 23, 0.4);
            transform: translateY(-2px);
        }
        .prod-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-instant {
            background: rgba(14, 203, 129, 0.15);
            color: #0ecb81;
            border: 1px solid rgba(14, 203, 129, 0.3);
        }
        .badge-discount {
            background: rgba(246, 70, 93, 0.15);
            color: #f6465d;
            border: 1px solid rgba(246, 70, 93, 0.3);
        }
        .price-chip {
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            margin: 14px 0;
        }
        .checkout-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 22px;
            margin-bottom: 20px;
        }
        .bal-status-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: 13.5px;
        }
    </style>
</head>
<body>
    <div class="page-wrap">

        <?php if ($singleProduct): ?>
            <!-- SINGLE PRODUCT CHECKOUT VIEW -->
            <?php
            $p = $singleProduct;
            $rawPrice = (float)$p['price'];
            $rawDisc  = (float)($p['discount'] ?? 0);
            $finalBdt = max(0, round($rawPrice - ($rawDisc > 1 ? $rawDisc : ($rawPrice * $rawDisc / 100)), 2));
            $neededCoins = round($finalBdt * 10, 2);
            $userCoins = (float)($balances['coins'] ?? 0);
            $hasEnoughCoins = $userCoins >= $neededCoins;
            ?>

            <?= render_page_header("Product Checkout", "/buy.php") ?>

            <div class="checkout-box">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                    <div>
                        <span class="prod-badge badge-instant">🚚 <?= htmlspecialchars($p['delivery_time'] ?: 'Instant') ?></span>
                        <h2 style="font-size: 20px; font-weight: 900; color: #ffffff; margin-top: 8px;">
                            <?= htmlspecialchars($p['name'] ?: $p['title']) ?>
                        </h2>
                    </div>
                    <?php if ($rawDisc > 0): ?>
                        <span class="prod-badge badge-discount">Save ৳<?= number_format($rawPrice - $finalBdt, 2) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Price Breakdown Box -->
                <div class="price-chip">
                    <div style="display: flex; justify-content: space-between; font-size: 13.5px; color: var(--text-muted); margin-bottom: 6px;">
                        <span>Regular Price:</span>
                        <span style="<?= $rawDisc > 0 ? 'text-decoration: line-through; color: var(--text-dim);' : 'color: #ffffff;' ?>">
                            ৳<?= number_format($rawPrice, 2) ?>
                        </span>
                    </div>
                    <?php if ($rawDisc > 0): ?>
                        <div style="display: flex; justify-content: space-between; font-size: 13.5px; color: #0ecb81; margin-bottom: 6px;">
                            <span>Special Discount:</span>
                            <span>- ৳<?= number_format($rawPrice - $finalBdt, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 10px; margin-top: 6px;">
                        <span style="font-weight: 800; color: #ffffff; font-size: 15px;">Final Payable:</span>
                        <div style="text-align: right;">
                            <div style="font-size: 20px; font-weight: 900; color: var(--accent-gold);">৳<?= number_format($finalBdt, 2) ?></div>
                            <div style="font-size: 13px; color: #fcd535; font-weight: 700;">🪙 <?= number_format($neededCoins, 2) ?> Coins</div>
                        </div>
                    </div>
                </div>

                <!-- User Balance Status -->
                <div class="bal-status-box">
                    <div>
                        <div style="color: var(--text-muted); font-size: 12px;">Your Available Balance</div>
                        <strong style="color: #ffffff; font-size: 15px;">🪙 <?= number_format($userCoins, 2) ?> Coins</strong>
                    </div>
                    <div>
                        <?php if ($hasEnoughCoins): ?>
                            <span style="color: #0ecb81; font-weight: 700; font-size: 13px;">✓ Sufficient</span>
                        <?php else: ?>
                            <span style="color: #f6465d; font-weight: 700; font-size: 13px;">✕ Need 🪙 <?= number_format($neededCoins - $userCoins, 2) ?> more</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Checkout Actions -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- 1. Instant Coin Purchase -->
                    <button type="button" 
                            id="btn-buy-coins" 
                            class="btn btn-gold btn-block" 
                            style="padding: 14px; font-size: 15px; font-weight: 800; display: flex; justify-content: center; align-items: center; gap: 8px;"
                            onclick="handleCoinPurchase(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'] ?: $p['title'])) ?>', <?= $neededCoins ?>, <?= $userCoins ?>)">
                        ⚡ Instant Buy with Coins (🪙 <?= number_format($neededCoins, 2) ?>)
                    </button>

                    <!-- 2. Pay with BDT / Gateway -->
                    <a href="purchase_pay.php?id=<?= (int)$p['id'] ?>" 
                       class="btn btn-outline btn-block" 
                       style="padding: 13px; font-size: 14.5px; text-decoration: none; text-align: center; color: #ffffff; border-color: rgba(255, 255, 255, 0.15);">
                        💳 Pay with BDT (bKash / Nagad / Rocket) ›
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- FULL STORE CATALOG VIEW -->
            <?= render_page_header("Club Store & Services", "/dashboard.php") ?>

            <div class="store-hero">
                <div>
                    <h2 style="font-size: 18px; font-weight: 900; color: #ffffff; margin-bottom: 4px;">
                        💎 Official Store Catalog
                    </h2>
                    <p style="font-size: 13px; color: var(--text-muted); margin: 0;">
                        Purchase VIP boosts, click packages, and club utilities with Coins or BDT.
                    </p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; color: var(--text-muted);">Available Coins</div>
                    <div style="font-size: 16px; font-weight: 900; color: var(--accent-gold);">
                        🪙 <?= number_format($balances['coins'] ?? 0, 2) ?>
                    </div>
                </div>
            </div>

            <?php if (empty($catalog)): ?>
                <div class="card" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <p style="font-size: 16px; margin-bottom: 8px;">📦 No products currently active in store.</p>
                    <p style="font-size: 13px;">Please check back shortly or contact administration.</p>
                </div>
            <?php else: ?>
                <div class="store-grid">
                    <?php foreach ($catalog as $item): ?>
                        <?php
                        $iPrice = (float)$item['price'];
                        $iDisc  = (float)($item['discount'] ?? 0);
                        $iFinal = max(0, round($iPrice - ($iDisc > 1 ? $iDisc : ($iPrice * $iDisc / 100)), 2));
                        $iCoins = round($iFinal * 10, 2);
                        ?>
                        <div class="store-card">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <span class="prod-badge badge-instant">🚚 <?= htmlspecialchars($item['delivery_time'] ?: 'Instant') ?></span>
                                    <?php if ($iDisc > 0): ?>
                                        <span class="prod-badge badge-discount">Save ৳<?= number_format($iPrice - $iFinal, 2) ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3 style="font-size: 16px; font-weight: 800; color: #ffffff; margin-bottom: 6px;">
                                    <?= htmlspecialchars($item['name'] ?: $item['title']) ?>
                                </h3>
                            </div>

                            <div>
                                <div class="price-chip" style="margin: 12px 0;">
                                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                                        <div>
                                            <span style="font-size: 18px; font-weight: 900; color: var(--accent-gold);">৳<?= number_format($iFinal, 2) ?></span>
                                            <?php if ($iDisc > 0): ?>
                                                <span style="font-size: 12px; text-decoration: line-through; color: var(--text-dim); margin-left: 6px;">৳<?= number_format($iPrice, 2) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 13px; font-weight: 700; color: #fcd535;">
                                            🪙 <?= number_format($iCoins, 2) ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 8px;">
                                    <a href="buy.php?id=<?= (int)$item['id'] ?>" class="btn btn-gold" style="flex: 1; padding: 10px; font-size: 13.5px; text-decoration: none; text-align: center; font-weight: 800;">
                                        Buy Now ›
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>

    <!-- Custom Modal Script Integration (No Native Alerts) -->
    <script src="assets/modal.js"></script>
    <script>
        function handleCoinPurchase(productId, productName, needCoins, userCoins) {
            // 1. Insufficient Balance Check with Custom Modal UI
            if (userCoins < needCoins) {
                const diff = (needCoins - userCoins).toFixed(2);
                if (typeof window.showAppConfirm === 'function') {
                    window.showAppConfirm(
                        `You need 🪙 ${needCoins.toLocaleString()} Coins for ${productName}, but your available balance is 🪙 ${userCoins.toLocaleString()} Coins (Short by 🪙 ${diff}). Would you like to deposit or convert funds?`,
                        'Insufficient Balance',
                        () => { window.location.href = 'deposit.php'; },
                        null,
                        'Deposit Funds',
                        'Cancel',
                        '⚠️'
                    );
                } else {
                    alert(`Insufficient Balance: You need 🪙 ${needCoins} Coins.`);
                }
                return;
            }

            // 2. Custom Confirmation Modal UI
            if (typeof window.showAppConfirm === 'function') {
                window.showAppConfirm(
                    `Are you sure you want to purchase ${productName} for 🪙 ${needCoins.toLocaleString()} Coins? The coins will be reserved in your locked balance pending admin delivery.`,
                    'Confirm Purchase',
                    () => { executeCoinBuy(productId, productName); },
                    null,
                    'Confirm & Buy',
                    'Cancel',
                    '⚡'
                );
            } else {
                executeCoinBuy(productId, productName);
            }
        }

        async function executeCoinBuy(productId, productName) {
            const btn = document.getElementById('btn-buy-coins');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Processing Purchase...';
            }

            try {
                const formData = new FormData();
                formData.append('action', 'buy_with_coins');
                formData.append('product_id', productId);
                formData.append('ajax', '1');

                const res = await fetch('buy.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    if (typeof window.showAppAlert === 'function') {
                        window.showAppAlert(
                            `Order #${data.order_id} placed successfully! 🪙 ${data.locked_coins.toLocaleString()} Coins reserved in locked balance. Delivery is in progress.`,
                            'Order Placed Successfully!',
                            'success',
                            '🎉'
                        );
                        setTimeout(() => {
                            window.location.href = 'purchase_orders.php';
                        }, 2200);
                    } else {
                        window.location.href = 'purchase_orders.php';
                    }
                } else {
                    if (typeof window.showAppAlert === 'function') {
                        window.showAppAlert(data.error || 'Failed to place order.', 'Purchase Failed', 'error', '⚠️');
                    } else {
                        alert(data.error || 'Failed to place order.');
                    }
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = '⚡ Instant Buy with Coins';
                    }
                }
            } catch (err) {
                if (typeof window.showAppAlert === 'function') {
                    window.showAppAlert('A network error occurred. Please check your connection and retry.', 'Connection Error', 'error', '⚠️');
                }
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '⚡ Instant Buy with Coins';
                }
            }
        }
    </script>
</body>
</html>
