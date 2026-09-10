<?php
/**
 * UNMOOR CLUB - PURCHASE STORE & ORDER LOCKING
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$balances = get_user_balances($db, $uid);

if (($balances['status'] !== 'active' && $balances['status'] !== 'premium') || $balances['apply_status'] !== 'approved' || $balances['role'] === 'system') {
    die("ACCESS DENIED");
}

/* ================= PRODUCTS ================= */
$products = [
    'power' => [
        'id'    => 1,
        'name'  => 'Power Click',
        'icon'  => '⚡',
        'coins' => 2000,
        'tag'   => 'VIP Premium',
        'desc'  => 'High-tier automated mining boost with priority fulfillment.'
    ],
    'joint' => [
        'id'    => 2,
        'name'  => 'Joint Click',
        'icon'  => '🤝',
        'coins' => 1000,
        'tag'   => 'Most Popular',
        'desc'  => 'Syndicate co-operative multi-node click pack.'
    ],
    'paper' => [
        'id'    => 3,
        'name'  => 'Paper Click',
        'icon'  => '📄',
        'coins' => 500,
        'tag'   => 'Starter Pack',
        'desc'  => 'Essential entry-level member utility credit.'
    ]
];

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

$msg = '';
$error = '';
$orderSuccessData = null;

/* ================= HANDLE PURCHASE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_key'])) {

    $key = trim($_POST['product_key'] ?? '');

    if (!isset($products[$key])) {
        $error = "Invalid product selected.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    } else {
        $p = $products[$key];
        $need = (float)$p['coins'];

        $res = lock_user_order($db, $uid, $need, $p['id'], $key);

        if (!$res['success']) {
            $error = $res['error'];
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
        } else {
            $balances = $res['balances'];
            $orderSuccessData = [
                'order_id' => $res['order_id'],
                'product_name' => $p['name'],
                'locked_amount' => $need,
                'balances' => $balances
            ];
            $msg = "Order #" . $res['order_id'] . " placed! 🪙 " . number_format($need, 2) . " has been moved to Locked Balance pending admin approval.";

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'order_id' => $res['order_id'],
                    'product_name' => $p['name'],
                    'locked_amount' => $need,
                    'balances' => $balances,
                    'message' => $msg
                ]);
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Store • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* IN-APP MODAL STYLES */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-backdrop.active {
            display: flex;
        }
        .modal-dialog {
            background: #0f172a;
            border: 1px solid rgba(250, 204, 21, 0.3);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 420px;
            padding: 22px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
            position: relative;
            animation: modalFadeIn 0.2s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(12px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 20px;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close-btn:hover {
            color: #ffffff;
        }
        .balance-pill-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed rgba(255,255,255,0.08);
            font-size: 13.5px;
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Club Store", "/dashboard.php") ?>

        <!-- BALANCE OVERVIEW CARD -->
        <div id="balanceCardContainer">
            <?= render_balance_card($balances, false) ?>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <div style="font-size: 13px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">
                Available Packages
            </div>
            <a href="purchase_history.php" class="btn btn-secondary btn-sm" style="text-decoration: none; padding: 6px 12px; font-size: 12px;">
                📜 Order Status
            </a>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success" style="margin-bottom: 14px;">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 14px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- PRODUCT CARDS GRID -->
        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
            <?php foreach ($products as $k => $p): ?>
                <?php 
                    $canAfford = ($balances['coins'] >= $p['coins']);
                ?>
                <div class="card" style="position: relative; overflow: hidden; border: 1px solid <?= $canAfford ? 'var(--border-color)' : 'rgba(239, 68, 68, 0.2)' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="font-size: 28px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: var(--bg-dark); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                                <?= $p['icon'] ?>
                            </div>
                            <div>
                                <span class="badge badge-warning" style="font-size: 10px; padding: 2px 8px; margin-bottom: 4px; display: inline-block;">
                                    <?= htmlspecialchars($p['tag']) ?>
                                </span>
                                <h3 style="font-size: 16px; font-weight: 900; color: #ffffff; margin: 0;">
                                    <?= htmlspecialchars($p['name']) ?>
                                </h3>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <div style="font-size: 19px; font-weight: 900; color: var(--accent-gold);">
                                🪙 <?= number_format($p['coins']) ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-dim);">Coins required</div>
                        </div>
                    </div>

                    <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; line-height: 1.4;">
                        <?= htmlspecialchars($p['desc']) ?>
                    </p>

                    <button type="button" 
                            class="btn <?= $canAfford ? 'btn-gold' : 'btn-secondary' ?> btn-block" 
                            style="padding: 12px; font-weight: 800;"
                            onclick="openPurchaseModal('<?= $k ?>', '<?= addslashes($p['name']) ?>', '<?= $p['icon'] ?>', <?= (float)$p['coins'] ?>)">
                        <?= $canAfford ? '🛒 Place Order' : '⚠️ Insufficient Available Balance' ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <!-- IN-APP ORDER CONFIRMATION MODAL -->
    <div class="modal-backdrop" id="purchaseModal">
        <div class="modal-dialog">
            <button type="button" class="modal-close-btn" onclick="closePurchaseModal()">✕</button>
            
            <!-- STEP 1: CONFIRMATION FORM -->
            <div id="modalStepConfirm">
                <div style="text-align: center; margin-bottom: 16px;">
                    <div id="modalProductIcon" style="font-size: 42px; margin-bottom: 6px;">📦</div>
                    <h3 id="modalProductName" style="font-size: 18px; font-weight: 900; color: #ffffff; margin: 0 0 4px;">Product Name</h3>
                    <div style="font-size: 13px; color: var(--accent-gold); font-weight: 800;" id="modalProductPrice">🪙 0.00</div>
                </div>

                <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                    <div class="balance-pill-row">
                        <span style="color: var(--text-muted);">Current Available:</span>
                        <span style="font-weight: 800; color: #ffffff;" id="modalAvailBal">🪙 <?= number_format($balances['coins'], 2) ?></span>
                    </div>
                    <div class="balance-pill-row">
                        <span style="color: var(--text-muted);">Amount to Lock:</span>
                        <span style="font-weight: 800; color: #f59e0b;" id="modalLockAmount">🪙 0.00</span>
                    </div>
                    <div class="balance-pill-row" style="border-bottom: none;">
                        <span style="color: var(--text-muted);">Remaining Available:</span>
                        <span style="font-weight: 800; color: #22c55e;" id="modalRemainingBal">🪙 0.00</span>
                    </div>
                </div>

                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-md); padding: 12px; margin-bottom: 18px; font-size: 12px; color: #fbbf24; line-height: 1.4;">
                    🔒 <strong>Locked Balance Notice:</strong> This amount will be moved from your Available Balance to Locked Balance immediately. Coins are permanently deducted once an admin reviews and approves the order.
                </div>

                <div id="modalErrorMsg" class="alert alert-danger" style="display: none; margin-bottom: 12px; font-size: 12.5px;"></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closePurchaseModal()" style="padding: 12px;">
                        Cancel
                    </button>
                    <button type="button" id="modalConfirmBtn" class="btn btn-gold" onclick="executeOrderPurchase()" style="padding: 12px; font-weight: 800;">
                        Confirm &amp; Lock
                    </button>
                </div>
            </div>

            <!-- STEP 2: LOADING SPINNER -->
            <div id="modalStepLoading" style="display: none; text-align: center; padding: 30px 10px;">
                <div style="font-size: 36px; animation: spin 1s linear infinite; display: inline-block;">⏳</div>
                <div style="font-size: 16px; font-weight: 800; color: #ffffff; margin-top: 12px;">Locking Coins &amp; Submitting Order...</div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Please wait while the transaction is safely processed.</div>
            </div>

            <!-- STEP 3: SUCCESS CONFIRMATION -->
            <div id="modalStepSuccess" style="display: none; text-align: center; padding: 10px 0;">
                <div style="font-size: 48px; margin-bottom: 8px;">✅</div>
                <h3 style="font-size: 18px; font-weight: 900; color: #22c55e; margin: 0 0 6px;">Order Submitted!</h3>
                <div style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;" id="modalSuccessText">
                    Your coins have been moved to Locked Balance. An admin will verify and complete your order shortly.
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <a href="purchase_history.php" class="btn btn-gold" style="padding: 12px; text-decoration: none; text-align: center; font-size: 13px;">
                        View Status
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="closePurchaseModal(); location.reload();" style="padding: 12px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>

    <script>
    let currentProductKey = '';
    let currentProductPrice = 0;
    let userAvailableCoins = <?= (float)$balances['coins'] ?>;
    let isSubmitting = false;

    function openPurchaseModal(key, name, icon, price) {
        currentProductKey = key;
        currentProductPrice = price;

        document.getElementById('modalProductIcon').innerText = icon;
        document.getElementById('modalProductName').innerText = name;
        document.getElementById('modalProductPrice').innerText = '🪙 ' + price.toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('modalLockAmount').innerText = '🪙 ' + price.toLocaleString('en-US', {minimumFractionDigits: 2});
        
        const rem = userAvailableCoins - price;
        document.getElementById('modalAvailBal').innerText = '🪙 ' + userAvailableCoins.toLocaleString('en-US', {minimumFractionDigits: 2});
        
        const remEl = document.getElementById('modalRemainingBal');
        remEl.innerText = '🪙 ' + rem.toLocaleString('en-US', {minimumFractionDigits: 2});
        remEl.style.color = rem < 0 ? '#ef4444' : '#22c55e';

        const btn = document.getElementById('modalConfirmBtn');
        const errEl = document.getElementById('modalErrorMsg');
        errEl.style.display = 'none';

        if (rem < 0) {
            btn.disabled = true;
            btn.innerText = 'Insufficient Balance';
            btn.classList.remove('btn-gold');
            btn.classList.add('btn-secondary');
        } else {
            btn.disabled = false;
            btn.innerText = 'Confirm & Lock';
            btn.classList.add('btn-gold');
            btn.classList.remove('btn-secondary');
        }

        document.getElementById('modalStepConfirm').style.display = 'block';
        document.getElementById('modalStepLoading').style.display = 'none';
        document.getElementById('modalStepSuccess').style.display = 'none';
        document.getElementById('purchaseModal').classList.add('active');
    }

    function closePurchaseModal() {
        if (isSubmitting) return;
        document.getElementById('purchaseModal').classList.remove('active');
    }

    function executeOrderPurchase() {
        if (isSubmitting || !currentProductKey) return;
        isSubmitting = true;

        document.getElementById('modalStepConfirm').style.display = 'none';
        document.getElementById('modalStepLoading').style.display = 'block';

        const formData = new FormData();
        formData.append('product_key', currentProductKey);
        formData.append('ajax', '1');

        fetch('purchase.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            isSubmitting = false;
            document.getElementById('modalStepLoading').style.display = 'none';

            if (data.success) {
                userAvailableCoins = data.balances.coins;
                document.getElementById('modalSuccessText').innerHTML = 
                    'Order <strong>#' + data.order_id + '</strong> for <strong>' + data.product_name + '</strong> placed!<br><br>' +
                    '🔒 <strong>🪙 ' + Number(data.locked_amount).toFixed(2) + '</strong> locked.<br>' +
                    'New Available: <strong>🪙 ' + Number(data.balances.coins).toFixed(2) + '</strong>';
                
                document.getElementById('modalStepSuccess').style.display = 'block';
            } else {
                document.getElementById('modalStepConfirm').style.display = 'block';
                const errEl = document.getElementById('modalErrorMsg');
                errEl.innerText = data.error || 'Failed to place order.';
                errEl.style.display = 'block';
            }
        })
        .catch(err => {
            isSubmitting = false;
            document.getElementById('modalStepLoading').style.display = 'none';
            document.getElementById('modalStepConfirm').style.display = 'block';
            const errEl = document.getElementById('modalErrorMsg');
            errEl.innerText = 'Network error. Please retry.';
            errEl.style.display = 'block';
        });
    }
    </script>
</body>
</html>
