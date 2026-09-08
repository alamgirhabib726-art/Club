<?php
session_start();
require_once "db.php";

/* ===============================
   LOGIN REQUIRED
================================ */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ===============================
   CHECK CURRENT STATUS
================================ */
$stmt = $db->prepare("SELECT status FROM users WHERE id=?");
$stmt->execute([$uid]);
if ($stmt->fetchColumn() === 'premium') {
    header("Location: dashboard.php");
    exit;
}

$error = '';

/* ===============================
   HANDLE PLAN SUBMIT
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $plan = $_POST['plan'] ?? '';

    if (!in_array($plan, ['day','month'])) {
        $error = "Invalid plan selected";
    } else {

        $amount = ($plan === 'day') ? 10 : 300;

        $stmt = $db->prepare("
            INSERT INTO payments
            (user_id, type, amount, plan, status)
            VALUES (?, 'premium', ?, ?, 'pending')
        ");
        $stmt->execute([
            $uid,
            $amount,
            $plan
        ]);

        header("Location: premium_payment.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Upgrade Premium • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{
    max-width:380px;
    margin:auto;
    background:#fff;
    padding:20px;
    border-radius:18px;
}
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:14px;
    font-weight:600;
    margin-bottom:12px;
    cursor:pointer;
}
.day{background:#22c55e;color:#fff}
.month{background:#7c3aed;color:#fff}
.error{color:#dc2626;font-size:14px;margin-bottom:10px}
</style>
</head>

<body>

<div class="card">
<h3>⭐ Upgrade to Unmoor Premium</h3>

<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post">
    <button class="day" name="plan" value="day">
        1 Day Premium — ৳10
    </button>

    <button class="month" name="plan" value="month">
        1 Month Premium — ৳300
    </button>
</form>

<a href="dashboard.php">← ড্যাশবোর্ড</a>
</div>

</body>
</html>
