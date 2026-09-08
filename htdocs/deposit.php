<?php
session_start();
require_once __DIR__ . "/db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, name, status, apply_status, coins
    FROM users
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= SECURITY ================= */
if (!$user || $user['status'] === 'banned') {
    session_destroy();
    die("ACCESS DENIED");
}

/* Deposit only after approval */
if ($user['status'] !== 'active' || $user['apply_status'] !== 'approved') {
    header("Location: application_pending.php");
    exit;
}

/* ================= CONFIG ================= */
$PAY_NUMBER = "01611906722";
$methods = [
    'bkash' => 'bKash',
    'nagad' => 'Nagad'
];

$msg = '';
$error = '';

/* =================================================
   APPLY COUPON (DEPOSIT ONLY)
================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {

    $couponCode = trim($_POST['coupon'] ?? '');

    if ($couponCode === '') {
        $error = "Enter a coupon code.";
    } else {

        $stmt = $db->prepare("
            SELECT id, amount
            FROM coupons
            WHERE code = ?
              AND type = 'deposit'
              AND used_by IS NULL
            LIMIT 1
        ");
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            $error = "❌ Invalid or already used coupon.";
        } else {

$coins = $coupon['amount'] / 10;

$db->beginTransaction();
try {

    // ADD COINS TO USER
    $db->prepare("
        UPDATE users
        SET coins = coins + ?
        WHERE id = ?
    ")->execute([
        $coins,
        $user['id']
    ]);

    // HISTORY
    $db->prepare("
        INSERT INTO coin_history
            (user_id, amount, type, reference, created_at)
        VALUES
            (?, ?, 'credit', 'Coupon deposit', NOW())
    ")->execute([
        $user['id'],
        $coins
    ]);

    // MARK COUPON USED
    $db->prepare("
        UPDATE coupons
        SET status = 'used',
            used_by = ?,
            used_at = NOW()
        WHERE id = ?
    ")->execute([
        $user['id'],
        $coupon['id']
    ]);

    $db->commit();
    header("Location: dashboard.php");
    exit;

} catch (Exception $e) {
    $db->rollBack();
    $error = "❌ Coupon failed. Try again.";
}
        }
    }
}

/* =================================================
   MANUAL DEPOSIT (ADMIN APPROVAL)
================================================= */
if (
    isset($_POST['submit_deposit']) &&
    !empty($_POST['coupon'])
) {
    $error = "Use coupon OR manual deposit, not both.";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit'])) {

    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? '';

    if ($amount <= 0) {
        $error = "Enter a valid amount.";
    }
    elseif (!isset($methods[$method])) {
        $error = "Invalid payment method.";
    }
    elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
        $error = "Payment screenshot required.";
    }
    else {

        $dir = __DIR__ . "/uploads/deposit";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $allowed = ['jpg','jpeg','png','webp'];
$ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    $error = "Only image files allowed.";
}
        $file = "deposit_" . time() . "_" . rand(100,999) . "." . $ext;

        if (!move_uploaded_file($_FILES['proof']['tmp_name'], $dir . "/" . $file)) {
            $error = "Upload failed.";
        } else {

            $db->prepare("
                INSERT INTO payments
                (user_id, type, amount, method, proof, status, created_at)
                VALUES (?, 'deposit', ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
            ")->execute([
                $user['id'],
                $amount,
                $method,
                $file
            ]);

            $msg = "✅ Deposit submitted. Waiting for admin approval.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Deposit • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#0b0f19;
    --bg2:#020617;
    --card:#121826;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --accent:#22c55e;
    --accent2:#16a34a;
    --danger:#ef4444;
}
*{box-sizing:border-box;font-family:system-ui}
body{
    margin:0;
    background:linear-gradient(180deg,var(--bg),var(--bg2));
    color:var(--text);
}
.wrapper{
    max-width:420px;
    margin:24px auto;
    padding:16px;
}
.card{
    background:linear-gradient(180deg,#121826,#020617);
    border:1px solid var(--border);
    border-radius:24px;
    padding:22px;
    box-shadow:0 25px 60px rgba(0,0,0,.65);
}
h2{text-align:center;margin-bottom:16px;font-weight:900}

input,select{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:1px solid var(--border);
    background:#020617;
    color:var(--text);
    margin-bottom:14px;
}

.amount-box{
    background:rgba(34,197,94,.08);
    border:1px solid rgba(34,197,94,.25);
    border-radius:18px;
    padding:14px;
    margin-bottom:16px;
    text-align:center;
}
.amount-box small{color:var(--muted)}

.pay-box{
    background:#020617;
    border:2px dashed var(--border);
    border-radius:18px;
    padding:16px;
    text-align:center;
    margin-bottom:16px;
}
.pay-number{
    font-size:20px;
    font-weight:900;
    margin:6px 0;
}
.copy{
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#022c22;
    border:none;
    border-radius:999px;
    padding:6px 18px;
    font-weight:900;
    cursor:pointer;
}

button.submit{
    width:100%;
    padding:16px;
    border-radius:18px;
    border:none;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#022c22;
    font-weight:900;
    font-size:16px;
    cursor:pointer;
}

button.coupon{
    width:100%;
    padding:14px;
    border-radius:18px;
    border:none;
    background:#2563eb;
    color:#fff;
    font-weight:900;
    margin-bottom:14px;
    cursor:pointer;
}

.msg{color:var(--accent);text-align:center;font-weight:800}
.error{color:var(--danger);text-align:center;font-weight:800}

.back{
    margin-top:16px;
    display:block;
    text-align:center;
    color:var(--muted);
    text-decoration:none;
    font-weight:700;
}
</style>
</head>

<body>
<div class="wrapper">
<div class="card">

<h2>💳 Deposit Balance</h2>

<form method="post" enctype="multipart/form-data">

<input type="text" name="coupon" placeholder="Have a coupon? (optional)">

<button type="submit" name="apply_coupon" class="coupon">
🎟 Apply Coupon
</button>

<div class="amount-box">
    <small>Enter Amount (৳)</small>
    <input type="number" name="amount" placeholder="e.g. 100">
</div>

<div class="pay-box">
    <div>Send Money To</div>
    <div class="pay-number" id="num"><?=$PAY_NUMBER?></div>
    <button type="button" class="copy" onclick="copyNum()">Copy</button>
</div>

<?php if($msg): ?><div class="msg"><?=$msg?></div><?php endif; ?>
<?php if($error): ?><div class="error"><?=$error?></div><?php endif; ?>

<select name="method">
    <option value="">Select Payment Method</option>
    <option value="bkash">bKash</option>
    <option value="nagad">Nagad</option>
</select>

<input type="file" name="proof" accept="image/*">

<button class="submit" name="submit_deposit">
Submit Deposit
</button>

</form>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

</div>
</div>

<script>
function copyNum(){
    navigator.clipboard.writeText(
        document.getElementById("num").innerText
    );
}
</script>
</body>
</html>
