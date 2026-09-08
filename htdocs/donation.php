<?php
session_start();
require_once __DIR__ . "/db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= FETCH DONOR (FIXED) ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, coins, status, apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$user ||
    $user['status'] !== 'active' ||
    $user['apply_status'] !== 'approved'
) {
    die("ACCESS DENIED");
}

/* ================= SYSTEM RECEIVER ================= */
$receiverPhone = "01714761754";

$stmt = $db->prepare("
    SELECT id, name
    FROM users
    WHERE phone = ? AND role = 'system'
    LIMIT 1
");
$stmt->execute([$receiverPhone]);
$receiver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receiver) {
    die("SYSTEM ACCOUNT NOT FOUND");
}

$msg = $error = "";

/* ================= DONATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = round((float)($_POST['amount'] ?? 0), 2);

    if ($amount <= 0) {
        $error = "❌ Enter a valid amount";
    }
    elseif ($amount > $user['coins']) {
        $error = "❌ Insufficient balance";
    }
    else {

        $db->beginTransaction();
        try {

            /* USER → SYSTEM BALANCE */
            $db->prepare("UPDATE users SET coins = coins - ? WHERE id = ?")
               ->execute([$amount, $user['id']]);

            $db->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")
               ->execute([$amount, $receiver['id']]);

            /* USER HISTORY */
            $db->prepare("
                INSERT INTO coin_history
                    (user_id, amount, type,
                     source_user_id, source_name, source_number)
                VALUES
                    (?, ?, 'donation_out', ?, ?, ?)
            ")->execute([
                $user['id'],
                -$amount,
                $receiver['id'],
                'Unmoor Club',
                $receiverPhone
            ]);

            /* SYSTEM HISTORY (FULL NAME + NUMBER ✅) */
            $db->prepare("
                INSERT INTO coin_history
                    (user_id, amount, type,
                     source_user_id, source_name, source_number)
                VALUES
                    (?, ?, 'donation_in', ?, ?, ?)
            ")->execute([
                $receiver['id'],
                $amount,
                $user['id'],
                $user['name'],
                $user['phone']
            ]);

            /* PAYMENT LOG */
            $db->prepare("
                INSERT INTO payments
                    (user_id, type, amount, status, source, created_at)
                VALUES
                    (?, 'donation', ?, 'approved', ?, NOW())
            ")->execute([
                $user['id'],
                $amount,
                $user['name'].' ('.$user['phone'].')'
            ]);

            $db->commit();

            $_SESSION['donation_msg'] = "💚 Donation successful";
header("Location: donation.php");
exit;

        } catch (Exception $e) {
            $db->rollBack();
            error_log("DONATION ERROR: ".$e->getMessage());
            $_SESSION['donation_err'] = "❌ Donation failed";
header("Location: donation.php");
exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Donate • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.card{
    max-width:420px;
    margin:40px auto;
    background:#121826;
    border:1px solid #1f2937;
    border-radius:26px;
    padding:26px;
    box-shadow:0 30px 60px rgba(0,0,0,.45);
}
h2{text-align:center;margin:0 0 8px}
.balance{
    text-align:center;
    font-weight:900;
    color:#22c55e;
    margin-bottom:18px;
}
.balance.negative{color:#ef4444}
input{
    width:100%;
    padding:16px;
    border-radius:16px;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    font-size:16px;
}
button{
    width:100%;
    margin-top:16px;
    padding:18px;
    border:none;
    border-radius:20px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    font-size:16px;
    font-weight:900;
    box-shadow:0 18px 40px rgba(34,197,94,.45);
}
.msg{text-align:center;color:#22c55e;font-weight:900;margin-top:14px}
.err{text-align:center;color:#ef4444;font-weight:900;margin-top:14px}
.note{
    margin-top:16px;
    font-size:13px;
    text-align:center;
    color:#9ca3af;
}
.back{
    display:block;
    margin-top:18px;
    text-align:center;
    color:#9ca3af;
    text-decoration:none;
}
</style>
</head>

<body>

<div class="card">

<h2>💚 Support Unmoor Club</h2>

<div class="balance <?= $user['coins'] < 0 ? 'negative' : '' ?>">
    Available Coins: 🪙 <?=number_format($user['coins'],2)?>
</div>

<?php if ($msg): ?><div class="msg"><?=$msg?></div><?php endif; ?>
<?php if ($error): ?><div class="err"><?=$error?></div><?php endif; ?>

<form method="post">
    <input type="number" name="amount" step="0.01" min="0.01"
           placeholder="Enter donation amount" required>
    <button>Donate Now</button>
</form>

<div class="note">
    💡 Donations help improve features & stability.<br>
    Thank you for supporting the club.
</div>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

</div>
</body>
</html>
