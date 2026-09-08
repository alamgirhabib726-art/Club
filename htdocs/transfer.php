<?php
session_start();
require_once __DIR__ . "/db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$senderId = (int)$_SESSION['user_id'];

/* ================= FETCH SENDER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, coins, status, apply_status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$senderId]);
$sender = $stmt->fetch(PDO::FETCH_ASSOC);

/* SECURITY */
if (!$sender || $sender['status'] !== 'active' || $sender['apply_status'] !== 'approved') {
    die("ACCESS DENIED");
}

/* ================= FLASH ================= */
$msg   = $_SESSION['transfer_msg'] ?? '';
$error = $_SESSION['transfer_err'] ?? '';
unset($_SESSION['transfer_msg'], $_SESSION['transfer_err']);

/* ================= POST LOCK ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_SESSION['transfer_lock'])) {
        header("Location: transfer.php");
        exit;
    }
    $_SESSION['transfer_lock'] = true;

    $toPhone = trim($_POST['phone'] ?? '');
    $amount  = (float)($_POST['coins'] ?? 0);

    /* VALIDATION */
    if (!preg_match('/^01\d{9}$/', $toPhone)) {
        $_SESSION['transfer_err'] = "❌ Invalid receiver phone number";
        goto REDIRECT;
    }

    if ($amount < 1) {
        $_SESSION['transfer_err'] = "❌ Minimum transfer is 1 coin";
        goto REDIRECT;
    }

    if ($toPhone === $sender['phone']) {
        $_SESSION['transfer_err'] = "❌ You cannot transfer to yourself";
        goto REDIRECT;
    }

    /* FETCH RECEIVER */
    $stmt = $db->prepare("
        SELECT id, name, phone, status, apply_status
        FROM users
        WHERE phone = ?
        LIMIT 1
    ");
    $stmt->execute([$toPhone]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receiver) {
        $_SESSION['transfer_err'] = "❌ Receiver not found";
        goto REDIRECT;
    }

    if ($receiver['status'] !== 'active' || $receiver['apply_status'] !== 'approved') {
        $_SESSION['transfer_err'] = "❌ Receiver is not eligible";
        goto REDIRECT;
    }

    /* ================= ATOMIC TRANSFER ================= */
    $db->beginTransaction();
    try {

        // sender deduct (safe)
        $stmt = $db->prepare("
            UPDATE users
            SET coins = coins - ?
            WHERE id = ? AND coins >= ?
        ");
        $stmt->execute([$amount, $senderId, $amount]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception("INSUFFICIENT_BALANCE");
        }

        // receiver add
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id = ?
        ")->execute([$amount, $receiver['id']]);

        // sender history
        $db->prepare("
            INSERT INTO coin_history
            (user_id, amount, type, source_user_id, source_name, source_number)
            VALUES (?, ?, 'transfer_out', ?, ?, ?)
        ")->execute([
            $senderId,
            -$amount,
            $receiver['id'],
            $receiver['name'],
            $receiver['phone']
        ]);

        // receiver history
        $db->prepare("
            INSERT INTO coin_history
            (user_id, amount, type, source_user_id, source_name, source_number)
            VALUES (?, ?, 'transfer_in', ?, ?, ?)
        ")->execute([
            $receiver['id'],
            $amount,
            $sender['id'],
            $sender['name'],
            $sender['phone']
        ]);

        $db->commit();

        $_SESSION['transfer_msg'] = "✅ Transfer successful";

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['transfer_err'] = "❌ Transfer failed";
    }

REDIRECT:
    unset($_SESSION['transfer_lock']);
    header("Location: transfer.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Transfer Coins • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
    --bg:#0b0f19;
    --card:#121826;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --accent:#22c55e;
    --danger:#ef4444;
}
*{box-sizing:border-box;font-family:system-ui}
body{margin:0;background:var(--bg);color:var(--text)}
.wrapper{max-width:420px;margin:auto;padding:18px}
.card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:22px;
}
h2{text-align:center;margin-bottom:14px}
.balance{text-align:center;font-weight:800;margin-bottom:12px}
input{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:1px solid var(--border);
    background:#020617;
    color:var(--text);
    margin-bottom:14px;
}
button{
    width:100%;
    padding:16px;
    border-radius:18px;
    border:none;
    font-weight:900;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
}
.msg{color:var(--accent);text-align:center;font-weight:700}
.error{color:var(--danger);text-align:center;font-weight:700}
.back{
    display:block;
    margin-top:16px;
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

<h2>🔁 Transfer Coins</h2>
<div class="balance">🪙 Your Coins: <?=number_format($sender['coins'],2)?></div>

<?php if($msg): ?><div class="msg"><?=$msg?></div><?php endif; ?>
<?php if($error): ?><div class="error"><?=$error?></div><?php endif; ?>

<form method="post">
    <input name="phone" placeholder="Receiver Phone (01XXXXXXXXX)" required>
    <input name="coins" type="number" step="0.01" min="1" placeholder="Coins to Transfer" required>
    <button type="submit">Send Coins</button>
</form>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

</div>
</div>
</body>
</html>
