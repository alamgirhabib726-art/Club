<?php
session_start();
require_once "../db.php";

/* ADMIN CHECK */
if (!isset($_SESSION['user_id'])) die("NO ACCESS");

$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ADMIN ONLY");
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone  = trim($_POST['phone'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if (!preg_match('/^\d{10,11}$/', $phone)) {
        $error = "Invalid phone number";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0";
    } else {

        $stmt = $db->prepare("SELECT id FROM users WHERE phone=?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "User not found";
        } else {

            /* ADD BALANCE */
            $db->prepare("
                UPDATE users SET balance = balance + ?
                WHERE id = ?
            ")->execute([$amount, $user['id']]);

            /* LOG */
            $db->prepare("
                INSERT INTO admin_balance_logs
                (admin_id, user_id, amount, note)
                VALUES (?,?,?,?)
            ")->execute([
                $_SESSION['user_id'],
                $user['id'],
                $amount,
                $note
            ]);

            $msg = "✅ ৳{$amount} added successfully";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Add Balance • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f6f7fb}
.card{
  max-width:420px;
  margin:40px auto;
  background:#fff;
  padding:20px;
  border-radius:18px;
  box-shadow:0 20px 40px rgba(0,0,0,.15)
}
input,textarea{
  width:100%;
  padding:14px;
  margin-bottom:14px;
  border:none;
  border-radius:12px;
  background:#f2f2f2
}
button{
  width:100%;
  padding:14px;
  border:none;
  border-radius:14px;
  background:#16a34a;
  color:#fff;
  font-weight:600;
  cursor:pointer
}
.msg{color:#16a34a;font-weight:600}
.err{color:#dc2626}
</style>
</head>
<body>

<div class="card">
<h3>💰 Add Balance to User</h3>

<?php if($msg): ?><p class="msg"><?=htmlspecialchars($msg)?></p><?php endif; ?>
<?php if($error): ?><p class="err"><?=htmlspecialchars($error)?></p><?php endif; ?>

<form method="post">
  <input name="phone" placeholder="User Phone Number" required>
  <input name="amount" type="number" step="0.01" placeholder="Amount (BDT)" required>
  <textarea name="note" placeholder="Admin note (optional)"></textarea>
  <button>Add Balance</button>
</form>

<a href="dashboard.php">← Admin Dashboard</a>
</div>

</body>
</html>
