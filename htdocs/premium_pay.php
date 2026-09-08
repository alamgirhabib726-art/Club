<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$plan = $_POST['plan'] ?? '';

if (!in_array($plan, ['day','month'])) {
    die("Invalid plan");
}

$amount = ($plan === 'day') ? 10 : 300;

$_SESSION['premium_plan']   = $plan;
$_SESSION['premium_amount'] = $amount;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Premium Payment</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{
    max-width:380px;margin:auto;
    background:#fff;padding:20px;border-radius:18px
}
.copy{
    background:#e5e7eb;padding:12px;border-radius:12px;
    margin:10px 0;font-weight:600
}
.btn{
    width:100%;padding:14px;
    border:none;border-radius:14px;
    background:#7c3aed;color:#fff;font-weight:600
}
</style>
</head>
<body>

<div class="card">
<h3>💳 Premium Payment</h3>

<p>পরিমাণ: <b>৳<?= $amount ?></b></p>

<div class="copy">
📱 bKash / Nagad<br>
<b>01788674353</b>
</div>

<p style="font-size:14px;color:#374151">
Payment করার পরে Screenshot আপলোড করুন
</p>

<a href="premium_upload.php">
<button class="btn">📤 Upload Payment Proof</button>
</a>

</div>
</body>
</html>
