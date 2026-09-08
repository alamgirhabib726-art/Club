<?php
session_start();
require_once __DIR__ . "/db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, status, apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

/* ================= ROUTING (NO LOOP) ================= */

if ($user['apply_status'] === 'approved') {
    header("Location: dashboard.php");
    exit;
}

if ($user['apply_status'] === 'pending') {

    $chk = $db->prepare("
        SELECT id FROM payments
        WHERE user_id = ?
          AND type = 'apply'
          AND status = 'pending'
        LIMIT 1
    ");
    $chk->execute([$uid]);

    if ($chk->fetch()) {
        header("Location: application_pending.php");
        exit;
    }
}

/* ================= CONFIG ================= */
$BASE_AMOUNT = 150;
$PAY_NUMBER  = "01611906722";

$methods = [
    'bkash' => 'bKash',
    'nagad' => 'Nagad'
];

$error = "";

/* ================= SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $coupon = trim($_POST['coupon'] ?? '');

    /* ================= COUPON FLOW ================= */
    if ($coupon !== '') {

        $stmt = $db->prepare("
            SELECT id, amount
            FROM coupons
            WHERE code = ?
              AND type = 'apply'
              AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$coupon]);
        $cp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cp || $cp['amount'] < $BASE_AMOUNT) {
            $error = "Invalid coupon code.";
        } else {

            $db->beginTransaction();
            try {

                /* ===== COIN CALC ===== */
                $baseCoins  = 5;
                $extraCoins = max(0, floor(($cp['amount'] - 150) / 10));
                $totalCoins = $baseCoins + $extraCoins;

                /* ===== ACTIVATE USER ===== */
                $db->prepare("
                    UPDATE users
                    SET
                        status = 'active',
                        apply_status = 'approved',
                        coins = coins + ?,
                        coin_cycle_start = NOW(),
                        last_coin_cut = NULL
                    WHERE id = ?
                ")->execute([$totalCoins, $uid]);

                /* ===== SYSTEM +10 COINS ===== */
                $db->prepare("
                    UPDATE users
                    SET coins = coins + 10
                    WHERE role = 'system'
                ")->execute();

                /* ===== MARK COUPON USED ===== */
                $db->prepare("
                    UPDATE coupons
                    SET status='used',
                        used_by=?,
                        used_at=NOW()
                    WHERE id=?
                ")->execute([$uid, $cp['id']]);

                /* ================= COIN HISTORY ================= */

                /* USER APPLY BONUS */
                $db->prepare("
                    INSERT INTO coin_history
                    (user_id, amount, type, reference, created_at)
                    VALUES (?, ?, 'apply_bonus', 'Application Approved via Coupon', NOW())
                ")->execute([$uid, $totalCoins]);

                /* SYSTEM REGISTRATION INCOME */
                $systemId = $db->query("
                    SELECT id FROM users WHERE role='system' LIMIT 1
                ")->fetchColumn();

                if ($systemId) {
                    $db->prepare("
                        INSERT INTO coin_history
                        (user_id, amount, type, reference, created_at)
                        VALUES (?, 10, 'registration_income', 'User Application (Coupon)', NOW())
                    ")->execute([$systemId]);
                }

                $db->commit();
                header("Location: dashboard.php");
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $error = "Coupon processing failed.";
            }
        }
    }

    /* ================= MANUAL PAYMENT ================= */
    else {

        $method = $_POST['method'] ?? '';

        if (!isset($methods[$method])) {
            $error = "Select a valid payment method.";
        }
        elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            $error = "Payment screenshot required.";
        }
        else {

            $dir = __DIR__ . "/uploads/apply";
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $ext  = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
            $file = "apply_" . time() . "_" . rand(1000,9999) . "." . $ext;

            if (!move_uploaded_file($_FILES['proof']['tmp_name'], "$dir/$file")) {
                $error = "Upload failed.";
            } else {

                $db->prepare("
                    INSERT INTO payments
                    (user_id, type, amount, method, proof, status, created_at)
                    VALUES (?, 'apply', ?, ?, ?, 'pending', NOW())
                ")->execute([
                    $uid,
                    $BASE_AMOUNT,
                    $method,
                    $file
                ]);

                $db->prepare("
                    UPDATE users
                    SET apply_status = 'pending'
                    WHERE id = ?
                ")->execute([$uid]);

                header("Location: application_pending.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Application Payment • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{
    margin:0;
    background:#020617;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrapper{
    max-width:420px;
    margin:28px auto;
    padding:16px;
}
.card{
    background:#121826;
    border-radius:26px;
    padding:24px;
    box-shadow:0 30px 80px rgba(0,0,0,.7);
}
h2{text-align:center;font-weight:900}

.amount{
    background:#0b1220;
    border-radius:18px;
    padding:14px;
    text-align:center;
    margin-bottom:16px;
}
.send-box{
    border:2px dashed #1f2937;
    border-radius:18px;
    padding:16px;
    text-align:center;
    margin-bottom:16px;
}
.number{
    font-size:22px;
    font-weight:900;
    margin:6px 0;
}
.copy{
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    border:none;
    border-radius:999px;
    padding:8px 22px;
    font-weight:900;
}
input,select{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:1px solid #1f2937;
    background:#020617;
    color:#fff;
    margin-bottom:14px;
}
button.submit{
    width:100%;
    padding:16px;
    border:none;
    border-radius:18px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    font-weight:900;
    color:#022c22;
}
.error{
    color:#ef4444;
    font-weight:800;
    text-align:center;
    margin-bottom:12px;
}
.back{
    display:block;
    text-align:center;
    margin-top:16px;
    color:#9ca3af;
    text-decoration:none;
}
.toast{
    position:fixed;
    bottom:30px;
    left:50%;
    transform:translateX(-50%);
    background:#22c55e;
    color:#022c22;
    padding:14px 22px;
    border-radius:999px;
    font-weight:900;
    display:none;
}
</style>
</head>

<body>
<div class="wrapper">
<div class="card">

<h2>📝 Application Payment</h2>

<div class="amount">
    💰 Amount: <b>৳<?= $BASE_AMOUNT ?></b><br>
    📲 bKash / Nagad Supported
</div>

<div class="send-box">
    Send Money To
    <div class="number" id="num"><?= $PAY_NUMBER ?></div>
    <button class="copy" type="button" onclick="copyNum()">Copy</button>
</div>

<?php if($error): ?>
<div class="error">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <input type="text" name="coupon" placeholder="Have a coupon? (optional)">
    <select name="method">
        <option value="">Select Payment Method</option>
        <option value="bkash">bKash</option>
        <option value="nagad">Nagad</option>
    </select>
    <input type="file" name="proof" accept="image/*">
    <button class="submit">Submit Application Payment</button>
</form>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

</div>
</div>

<div class="toast" id="toast">✅ Number copied</div>

<script>
function copyNum(){
    navigator.clipboard.writeText(
        document.getElementById("num").innerText
    );
    const t = document.getElementById("toast");
    t.style.display="block";
    setTimeout(()=>t.style.display="none",2000);
}
</script>
</body>
</html>
