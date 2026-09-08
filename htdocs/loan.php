<?php
session_start();
require_once __DIR__."/db.php";

$stmt = $db->prepare("SELECT coins FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$coins = (float)$stmt->fetchColumn();

if ($coins >= 0) {
    header("Location: dashboard.php");
    exit;
}

$need = abs($coins);
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Account On Hold</title>
<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.box{
    max-width:420px;
    margin:60px auto;
    padding:24px;
    background:linear-gradient(135deg,#0f172a,#020617);
    border:1px solid #1f2937;
    border-radius:22px;
    text-align:center;
}
.amount{
    font-size:28px;
    font-weight:900;
    color:#ef4444;
    margin:12px 0;
}
.btn{
    display:block;
    margin-top:18px;
    padding:16px;
    border-radius:18px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    font-weight:900;
    text-decoration:none;
}
</style>
</head>
<body>

<div class="box">
    <h2>⚠ Account On Hold</h2>
    <p>Your balance is negative</p>
    <div class="amount">🪙 -<?= number_format($need,2) ?></div>
    <p>Please deposit at least this amount to unlock all features.</p>
    <a class="btn" href="deposit.php">💳 Deposit Now</a>
</div>

</body>
</html>
