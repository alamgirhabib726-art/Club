<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Premium Plan • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{
    max-width:380px;margin:auto;
    background:#fff;padding:20px;border-radius:18px
}
.btn{
    width:100%;padding:14px;margin-bottom:12px;
    border:none;border-radius:14px;
    background:#7c3aed;color:#fff;font-weight:600;
    cursor:pointer
}
</style>
</head>
<body>

<div class="card">
<h3>⭐ Premium Plan নির্বাচন করুন</h3>

<form method="post" action="premium_pay.php">
    <button class="btn" name="plan" value="day">
        1 Day Premium — ৳10
    </button>

    <button class="btn" name="plan" value="month">
        1 Month Premium — ৳300
    </button>
</form>

<a href="dashboard.php">← ড্যাশবোর্ড</a>
</div>

</body>
</html>
