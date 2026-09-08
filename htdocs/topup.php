<?php
require_once "db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$methods = require __DIR__ . "/config/payment_methods.php";
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Top Up Balance</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:system-ui;background:#f5f6fa}
.card{max-width:420px;margin:40px auto;background:#fff;padding:20px;border-radius:16px}
input,select,button{
    width:100%;padding:14px;margin-top:12px;
    border-radius:12px;border:1px solid #e5e7eb
}
button{background:#7c3aed;color:#fff;font-weight:600;border:none}
</style>
</head>
<body>

<div class="card">
<h3>➕ Balance Top-Up</h3>

<form method="post" action="topup_invoice.php">
    <input type="number" name="amount" placeholder="এমাউন্ট লিখুন (BDT)" required min="50">

    <select name="method" required>
        <option value="">পেমেন্ট মেথড নির্বাচন করুন</option>
        <?php foreach($methods as $key=>$m): ?>
            <option value="<?=$key?>"><?=$m['name']?></option>
        <?php endforeach; ?>
    </select>

    <button>Continue</button>
</form>

<a href="dashboard.php">← Back</a>
</div>

</body>
</html>
