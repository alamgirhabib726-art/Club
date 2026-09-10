<?php
/**
 * UNMOOR CLUB - TOPUP / BALANCE RECHARGE
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? '');

    if ($amount < 50) {
        $error = "Minimum top-up amount is ৳50.";
    } elseif (!in_array($method, ['bkash', 'nagad', 'rocket'])) {
        $error = "Please select a valid payment method.";
    } else {
        $_SESSION['topup_amount'] = $amount;
        $_SESSION['topup_method'] = $method;
        header("Location: topup_invoice.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Top Up Balance • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Top Up Balance", "/dashboard.php") ?>

        <div class="card">
            <h3 style="font-size: 16px; font-weight: 900; color: #ffffff; margin-bottom: 12px;">
                💳 Add Funds to Account
            </h3>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 14px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label class="form-label">Top-up Amount (BDT ৳)</label>
                    <input type="number" name="amount" class="form-control" placeholder="Min ৳50" min="50" step="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="method" class="form-control" required>
                        <option value="">-- Select Payment Method --</option>
                        <option value="bkash">bKash (Personal)</option>
                        <option value="nagad">Nagad (Personal)</option>
                        <option value="rocket">Rocket (Personal)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-gold btn-block" style="padding: 14px; margin-top: 10px;">
                    Continue to Payment Invoice ›
                </button>
            </form>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
