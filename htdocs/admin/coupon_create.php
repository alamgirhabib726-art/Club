<?php
session_start();
require_once "../db.php";

/* =========================
   ADMIN AUTH
========================= */
$stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id'] ?? 0]);

if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

/* =========================
   CREATE COUPON
========================= */
$msg = $err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = (float)($_POST['amount'] ?? 0);
    $type   = $_POST['type'] ?? '';

    if ($amount <= 0) {
        $err = "❌ Invalid coupon amount";
    }
    elseif (!in_array($type, ['registration','deposit'], true)) {
        $err = "❌ Invalid coupon type";
    }
    else {

        // Secure random coupon (8 chars)
        $code = strtoupper(bin2hex(random_bytes(4)));

        $db->prepare("
            INSERT INTO coupons
            (code, amount, type, status, created_at)
            VALUES (?, ?, ?, 'unused', NOW())
        ")->execute([
            $code,
            $amount,
            $type
        ]);

        $msg = "✅ Coupon Created: <strong>$code</strong>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Coupon • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrap{
    max-width:420px;
    margin:40px auto;
    padding:24px;
}
.card{
    background:#121826;
    border-radius:24px;
    padding:26px;
    box-shadow:0 25px 60px rgba(0,0,0,.45);
}
h2{text-align:center;margin:0 0 14px}
input,select{
    width:100%;
    padding:16px;
    border-radius:16px;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    margin-bottom:14px;
    font-size:15px;
}
button{
    width:100%;
    padding:18px;
    border:none;
    border-radius:20px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    font-weight:900;
    font-size:16px;
    box-shadow:0 18px 40px rgba(34,197,94,.45);
}
.msg{
    background:rgba(34,197,94,.15);
    color:#22c55e;
    padding:12px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:14px;
    text-align:center;
}
.err{
    background:rgba(239,68,68,.15);
    color:#ef4444;
    padding:12px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:14px;
    text-align:center;
}
.note{
    font-size:12px;
    color:#9ca3af;
    margin-top:10px;
    text-align:center;
}
.back{
    display:block;
    margin-top:18px;
    text-align:center;
    color:#9ca3af;
    text-decoration:none;
    font-weight:700;
}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<h2>🎟 Create Coupon</h2>

<?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="err"><?= $err ?></div><?php endif; ?>

<form method="post">
    <input type="number" step="0.01" name="amount"
           placeholder="Coupon Amount (BDT)" required>

    <select name="type" required>
        <option value="">Select Coupon Type</option>
        <option value="registration">Registration Coupon</option>
        <option value="deposit">Deposit Coupon</option>
    </select>

    <button>Create Coupon</button>
</form>

<div class="note">
• Registration coupon auto-approves if ≥ 150 BDT  
<br>
• Deposit coupon converts directly to coins
</div>

<a class="back" href="dashboard.php">← Back to Admin</a>

</div>
</div>
</body>
</html>
