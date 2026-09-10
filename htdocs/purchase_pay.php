<?php
/**
 * UNMOOR CLUB - PURCHASE PAYMENT
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* LOGIN REQUIRED */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH PRODUCT */
$slug = $_GET['product'] ?? '';

$stmt = $db->prepare("
    SELECT id, title, price, discount, slug, type
    FROM products
    WHERE (slug = ? OR type = ?) AND active = 1
    LIMIT 1
");
$stmt->execute([$slug, $slug]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: purchase.php");
    exit;
}

$price    = (float)$product['price'];
$discount = (float)($product['discount'] ?? 0);
$finalAmount = max(0, $price - ($discount > 1 ? $discount : ($price * $discount / 100)));

$msg = '';
$error = '';
$PAY_NUMBER = "01788674353";

/* HANDLE SUBMIT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_FILES['proof']) ||
        $_FILES['proof']['error'] !== UPLOAD_ERR_OK
    ) {
        $error = "Payment screenshot proof is required.";
    } else {

        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG or WEBP images allowed.";
        } else {

            $dir = __DIR__ . "/uploads/purchase";
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $filename = "pay_" . $uid . "_" . time() . "." . $ext;
            $path = $dir . "/" . $filename;

            if (!move_uploaded_file($_FILES['proof']['tmp_name'], $path)) {
                $error = "Screenshot upload failed.";
            } else {

                $db->prepare("
                    INSERT INTO payments
                    (user_id, type, amount, product_id, proof, status, created_at)
                    VALUES (?, 'purchase', ?, ?, ?, 'pending', NOW())
                ")->execute([
                    $uid,
                    $finalAmount,
                    $product['id'],
                    $filename
                ]);

                $msg = "✅ Payment submitted successfully! Awaiting administrator approval.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['title'] ?? 'Product') ?> Payment • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Product Payment", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 18px; font-weight: 900; color: #ffffff; margin-bottom: 8px;">
                💳 <?= htmlspecialchars($product['title'] ?? 'Product') ?>
            </h3>

            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 800;">
                    <span style="color: var(--text-muted);">Payable Amount:</span>
                    <span style="color: var(--accent-gold); font-size: 18px;">৳<?= number_format($finalAmount, 2) ?></span>
                </div>
                
                <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--border-color); text-align: center;">
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Send Money to (bKash / Nagad)</div>
                    <div style="font-size: 18px; font-weight: 900; color: #ffffff; margin: 4px 0;" id="payNum">
                        <?= $PAY_NUMBER ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyNum()">
                        📋 Copy Number
                    </button>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success" style="margin-bottom: 14px;">
                    <?= htmlspecialchars($msg) ?>
                </div>
                <a href="dashboard.php" class="btn btn-secondary btn-block" style="padding: 12px; text-align: center;">
                    ← Return to Dashboard
                </a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 14px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Payment Screenshot</label>
                        <input type="file" name="proof" class="form-control" accept="image/*" required>
                    </div>

                    <button type="submit" class="btn btn-gold btn-block" style="padding: 14px; margin-top: 10px;">
                        Submit Payment Proof
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>

    <script>
    function copyNum() {
        const n = document.getElementById('payNum').innerText.trim();
        navigator.clipboard.writeText(n).then(() => {
            alert('Payment number copied: ' + n);
        });
    }
    </script>
</body>
</html>
