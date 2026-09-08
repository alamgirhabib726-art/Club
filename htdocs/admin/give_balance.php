<?php
session_start();
require_once "../db.php";

/* ADMIN CHECK */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

$msg = '';
$err = '';

/* HANDLE FORM */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $uid    = (int)($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if ($uid <= 0) {
        $err = "Invalid user selected";
    } elseif ($amount <= 0 || $amount > 100000) {
        $err = "Invalid amount";
    } else {

        /* VERIFY USER EXISTS */
        $stmt = $db->prepare("SELECT id FROM users WHERE id=?");
        $stmt->execute([$uid]);
        if (!$stmt->fetch()) {
            $err = "User not found";
        } else {

            /* ADD BALANCE */
            $db->prepare("
                UPDATE users
                SET balance = balance + ?
                WHERE id = ?
            ")->execute([$amount, $uid]);

            /* LOG ACTION */
            $db->prepare("
                INSERT INTO admin_balance_logs
                (admin_id, user_id, amount, action, note)
                VALUES (?, ?, ?, 'credit', ?)
            ")->execute([
                $_SESSION['user_id'],
                $uid,
                $amount,
                $note
            ]);

            $msg = "✅ ৳{$amount} balance added successfully";
        }
    }
}

/* USERS LIST */
$users = $db->query("
    SELECT id, name, phone, balance
    FROM users
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Give Balance • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{
  max-width:420px;
  margin:auto;
  background:#fff;
  padding:20px;
  border-radius:18px;
  box-shadow:0 20px 40px rgba(0,0,0,.15)
}
input,select{
  width:100%;
  padding:12px;
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
  background:#16a34a;
  color:#fff;
  font-weight:600;
  cursor:pointer
}
.msg{color:#16a34a;font-weight:600}
.err{color:#dc2626;font-weight:600}
</style>
</head>

<body>

<div class="card">
<h3>💸 Give Balance</h3>

<?php if ($msg): ?><p class="msg"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endif; ?>

<form method="post">
    <select name="user_id" required>
        <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>">
            <?= htmlspecialchars($u['name']) ?>
            (<?= htmlspecialchars($u['phone']) ?>)
            — ৳<?= number_format($u['balance'],2) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <input type="number" step="0.01" name="amount" placeholder="Amount (BDT)" required>
    <input name="note" placeholder="Admin note (optional)">
    <button type="submit">Add Balance</button>
</form>

<p style="margin-top:12px">
<a href="dashboard.php">← Admin Dashboard</a>
</p>
</div>

</body>
</html>
