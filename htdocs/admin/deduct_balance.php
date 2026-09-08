<?php
session_start();
require_once "../db.php";

/* ADMIN CHECK */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
if ($stmt->fetchColumn() !== 'admin') {
    die("NO ACCESS");
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone  = trim($_POST['phone'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if (!preg_match('/^\d{10,11}$/', $phone)) {
        $err = "Invalid phone number";
    } elseif ($amount <= 0) {
        $err = "Amount must be greater than 0";
    } else {

        $stmt = $db->prepare("SELECT id, balance FROM users WHERE phone=? LIMIT 1");
        $stmt->execute([$phone]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            $err = "User not found";
        } elseif ((float)$u['balance'] < $amount) {
            $err = "Insufficient balance";
        } else {

            /* DEDUCT BALANCE */
            $db->prepare("
                UPDATE users SET balance = balance - ?
                WHERE id = ?
            ")->execute([$amount, $u['id']]);

            /* LOG */
            $db->prepare("
                INSERT INTO admin_balance_logs
                (admin_id, user_id, amount, action, note)
                VALUES (?, ?, ?, 'debit', ?)
            ")->execute([
                $_SESSION['user_id'],
                $u['id'],
                $amount,
                $note
            ]);

            $msg = "✅ ৳{$amount} deducted successfully";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Deduct Balance • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{
  max-width:420px;
  margin:40px auto;
  background:#fff;
  padding:20px;
  border-radius:18px;
  box-shadow:0 20px 40px rgba(0,0,0,.15)
}
input{
  width:100%;
  padding:14px;
  margin-bottom:12px;
  border:none;
  border-radius:12px;
  background:#f2f2f2
}
button{
  width:100%;
  padding:14px;
  border:none;
  border-radius:14px;
  background:#dc2626;
  color:#fff;
  font-weight:600;
  cursor:pointer
}
.msg{color:#16a34a;font-weight:600}
.err{color:#dc2626;font-weight:600}
a{text-decoration:none;color:#2563eb}
</style>
</head>

<body>

<div class="card">
<h3>➖ Deduct Balance</h3>

<?php if ($msg): ?><p class="msg"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endif; ?>

<form method="post">
  <input name="phone" placeholder="User Phone Number" required>
  <input name="amount" type="number" step="0.01" placeholder="Amount (BDT)" required>
  <input name="note" placeholder="Reason (optional)">
  <button>Deduct Balance</button>
</form>

<p style="margin-top:12px">
<a href="dashboard.php">← Back to Admin Dashboard</a>
</p>
</div>

</body>
</html>
