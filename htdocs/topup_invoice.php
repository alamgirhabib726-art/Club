<?php
/**
 * UNMOOR CLUB - TOPUP INVOICE
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

$payConfig = require __DIR__ . "/config/payment_numbers.php";

if (!isset($_SESSION['user_id']) || empty($_SESSION['topup_amount']) || empty($_SESSION['topup_method'])) {
    header("Location: topup.php");
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$amount = (float)$_SESSION['topup_amount'];
$method = $_SESSION['topup_method'];
$payInfo = $payConfig[$method] ?? ['name' => ucfirst($method), 'number' => '01788674353'];

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        $error = "Payment screenshot proof is required.";
    } else {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG or WEBP image files allowed.";
        } else {
            $dir = __DIR__ . "/uploads/topup";
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $filename = "topup_" . $uid . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['proof']['tmp_name'], "$dir/$filename")) {
                $db->prepare("
                    INSERT INTO payments (user_id, type, amount, method, proof, status, created_at)
                    VALUES (?, 'topup', ?, ?, ?, 'pending', NOW())
                ")->execute([
                    $uid,
                    $amount,
                    $method,
                    $filename
                ]);

                unset($_SESSION['topup_amount'], $_SESSION['topup_method']);
                $msg = "✅ Top-up request submitted! Your balance will be credited after admin review.";
            } else {
                $error = "File upload failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Top-Up Invoice • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Top-up Invoice", "/topup.php") ?>

        <div class="card">
            <h3 style="font-size: 16px; font-weight: 900; color: #ffffff; margin-bottom: 12px;">
                🧾 Top-up Payment Order
            </h3>

            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; font-size: 14px;">
                    <span style="color: var(--text-muted);">Amount:</span>
                    <span style="color: var(--accent-gold); font-size: 18px; font-weight: 900;">৳<?= number_format($amount, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13.5px; margin-top: 6px;">
                    <span style="color: var(--text-muted);">Method:</span>
                    <span style="color: #ffffff; font-weight: 700;"><?= htmlspecialchars($payInfo['name']) ?></span>
                </div>

                <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border-color); text-align: center;">
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Send Money to Number</div>
                    <div style="font-size: 18px; font-weight: 900; color: #ffffff; margin: 4px 0;" id="invoiceNum">
                        <?= htmlspecialchars($payInfo['number']) ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyInvoiceNum()">
                        📋 Copy Number
                    </button>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success" style="margin-bottom: 14px;">
                    <?= htmlspecialchars($msg) ?>
                </div>
                <a href="dashboard.php" class="btn btn-secondary btn-block" style="padding: 12px; text-align: center;">
                    ← Back to Dashboard
                </a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 14px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Upload Transaction Screenshot</label>
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
    function copyInvoiceNum() {
        const num = document.getElementById('invoiceNum').innerText.trim();
        navigator.clipboard.writeText(num).then(() => {
            alert('Payment number copied: ' + num);
        });
    }
    </script>
</body>
</html>
