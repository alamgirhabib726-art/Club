<?php
session_start();
require_once __DIR__ . "/db.php";

/* ===============================
   LOGIN REQUIRED
================================ */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ===============================
   CHECK USER STATUS
================================ */
$stmt = $db->prepare("SELECT status FROM users WHERE id=?");
$stmt->execute([$uid]);
if ($stmt->fetchColumn() === 'premium') {
    header("Location: dashboard.php");
    exit;
}

/* ===============================
   FETCH LATEST PENDING PREMIUM
================================ */
$stmt = $db->prepare("
    SELECT id, amount, plan
    FROM payments
    WHERE user_id=? AND type='premium' AND status='pending'
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$uid]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header("Location: update_premium.php");
    exit;
}

$error = $success = '';

/* ===============================
   HANDLE PROOF SUBMIT
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== 0) {
        $error = "পেমেন্ট স্ক্রিনশট আপলোড করুন";
    } else {

        $dir = __DIR__ . "/uploads";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $file = time() . "_" . basename($_FILES['proof']['name']);
        move_uploaded_file($_FILES['proof']['tmp_name'], "$dir/$file");

        $stmt = $db->prepare("
            UPDATE payments
            SET proof=?, status='pending'
            WHERE id=?
        ");
        $stmt->execute([$file, $payment['id']]);

        $success = "✅ পেমেন্ট জমা হয়েছে। অ্যাডমিন রিভিউ করবে।";
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Premium Payment • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f5f6fa;margin:0}
.card{
    max-width:420px;
    margin:30px auto;
    background:#fff;
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 26px rgba(0,0,0,.08)
}
button,input{width:100%;padding:14px;border-radius:12px}
button{
    border:none;
    background:linear-gradient(135deg,#8b5cf6,#7c3aed);
    color:#fff;
    font-weight:600;
}
.msg{margin-bottom:12px}
.err{color:#dc2626}
.ok{color:#16a34a}
.lock{background:#f1f5f9;border:1px solid #e5e7eb;margin-bottom:12px}
</style>
</head>

<body>
<div class="card">
<h3>⭐ Premium Payment</h3>

<div class="lock">
<strong>Plan:</strong>
<?= $payment['plan']==='day' ? '1 Day Premium' : '1 Month Premium' ?><br>
<strong>Amount:</strong> ৳<?= $payment['amount'] ?>
</div>

<p>📱 পেমেন্ট পাঠান এই নম্বরে:<br>
<strong>01XXXXXXXXX (bKash / Nagad)</strong></p>

<?php if($error): ?><div class="msg err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($success): ?><div class="msg ok"><?=htmlspecialchars($success)?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="proof" accept="image/*" required>
    <button type="submit">Submit Payment Proof</button>
</form>

<a href="dashboard.php">← ড্যাশবোর্ড</a>
</div>
</body>
</html>
