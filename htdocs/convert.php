<?php
session_start();
require_once __DIR__ . "/db.php";

/* LOGIN */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* FETCH USER */
$stmt = $db->prepare("
    SELECT id, coins, purchase_balance
    FROM users
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    die("ACCESS DENIED");
}

$error = $success = "";

/* HANDLE CONVERT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coins = floatval($_POST['coins'] ?? 0);

    if ($coins <= 0) {
        $error = "Invalid coin amount.";
    } elseif ($coins > $user['coins']) {
        $error = "Not enough coins.";
    } elseif ($user['coins'] < 0) {
        $error = "Account locked. Please deposit first.";
    } else {
        $amount = $coins * 10; // 1 coin = 10 taka

        $db->prepare("
            UPDATE users
            SET coins = coins - ?,
                purchase_balance = purchase_balance + ?
            WHERE id = ?
        ")->execute([$coins, $amount, $user['id']]);

        $user['coins'] -= $coins;
        $user['purchase_balance'] += $amount;

        $success = "Converted successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Convert • Unmoor Club</title>
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
body.light{
    --bg:#f4f6fb;
    --card:#ffffff;
    --border:#e5e7eb;
    --text:#0f172a;
    --muted:#6b7280;
    --accent:#16a34a;
    --danger:#dc2626;
}
*{box-sizing:border-box;font-family:system-ui}
body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    transition:.25s;
}
.wrapper{
    max-width:420px;
    margin:auto;
    padding:16px;
}

/* CARD */
.card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:22px;
    margin-top:20px;
}

/* INPUT */
input{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:1px solid var(--border);
    background:transparent;
    color:var(--text);
    font-size:15px;
    margin-top:12px;
}

/* BUTTON */
button{
    width:100%;
    height:54px;
    margin-top:16px;
    border:none;
    border-radius:16px;
    font-weight:800;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    font-size:16px;
    cursor:pointer;
}

/* INFO */
.stat{
    display:flex;
    justify-content:space-between;
    margin-top:8px;
    font-weight:700;
}
.muted{color:var(--muted)}
.success{color:var(--accent);text-align:center;margin-top:12px}
.error{color:var(--danger);text-align:center;margin-top:12px}

/* BACK */
.back{
    display:block;
    margin-top:20px;
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
    <h2>🔁 Convert Coins</h2>
    <p class="muted">
        Coins can only be converted into purchase power.<br>
        Withdrawal is not allowed.
    </p>

    <div class="stat">
        <span>🪙 Coins</span>
        <span><?=number_format($user['coins'],2)?></span>
    </div>

    <div class="stat">
        <span>🛒 Purchase Balance</span>
        <span>৳<?=number_format($user['purchase_balance'],2)?></span>
    </div>

    <?php if($error): ?>
        <div class="error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="success">✅ <?=htmlspecialchars($success)?></div>
    <?php endif; ?>

    <form method="post">
        <input
            type="number"
            name="coins"
            step="0.01"
            placeholder="Enter coin amount"
            required
        >
        <button type="submit">Convert to Purchase</button>
    </form>
</div>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

</div>

<script>
(function(){
    const t = localStorage.getItem("theme") || "dark";
    document.body.classList.add(t);
})();
</script>

</body>
</html>
