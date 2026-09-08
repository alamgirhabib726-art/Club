<?php
session_start();
require_once "db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* Approved → dashboard */
if ($user && $user['apply_status'] === 'approved') {
    header("Location: dashboard.php");
    exit;
}

/* ================= CHECK PENDING PAYMENT ================= */
$stmt = $db->prepare("
    SELECT id
    FROM payments
    WHERE user_id = ?
      AND type = 'apply'
      AND status = 'pending'
    LIMIT 1
");
$stmt->execute([$uid]);

if (!$stmt->fetch()) {
    // No pending apply payment → back to apply page
    header("Location: apply_payment.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Application Under Review • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box;font-family:system-ui}
body{
    margin:0;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:#020617;
    color:#fff;
}
.card{
    width:92%;
    max-width:420px;
    background:rgba(255,255,255,.06);
    border-radius:26px;
    padding:28px;
    text-align:center;
    box-shadow:0 25px 60px rgba(0,0,0,.55);
    backdrop-filter: blur(14px);
}
.icon{
    font-size:44px;
    margin-bottom:14px;
}
h2{
    margin:0 0 10px;
    font-size:22px;
}
p{
    font-size:15px;
    color:#cbd5f5;
    line-height:1.6;
}
.time{
    margin-top:12px;
    font-size:14px;
    color:#94a3b8;
}
.btn{
    display:block;
    margin-top:18px;
    padding:14px;
    border-radius:18px;
    text-decoration:none;
    font-weight:800;
    color:#022c22;
    background:linear-gradient(135deg,#22c55e,#16a34a);
}
.footer{
    margin-top:18px;
    font-size:13px;
    color:#94a3b8;
}
</style>
</head>

<body>

<div class="card">

    <div class="icon">⏳</div>

    <h2>Application Under Review</h2>

    <p>
        Your application payment has been received.<br>
        Please wait for admin approval.
    </p>

    <div class="time">
        ⏱ Usually takes <b>1–12 hours</b>
    </div>

    <a class="btn" href="logout.php">🚪 Logout</a>

    <div class="footer">
        Unmoor Club © <?= date("Y") ?>
    </div>

</div>

</body>
</html>
