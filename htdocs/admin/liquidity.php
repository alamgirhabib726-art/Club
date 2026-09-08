<?php
session_start();
require_once "../db.php";

/* ================= ADMIN GUARD ================= */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

/* ================= ENSURE LIQUIDITY ACCOUNT ================= */
$liq = $db->query("
    SELECT id, coins FROM users WHERE role='liquidity' LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$liq) {
    $db->exec("
        INSERT INTO users (name, role, coins, status)
        VALUES ('Liquidity Pool', 'liquidity', 0, 'active')
    ");
    $liq = $db->query("
        SELECT id, coins FROM users WHERE role='liquidity' LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
}

/* ================= ENSURE LIQUIDITY TABLE ================= */
$db->exec("
CREATE TABLE IF NOT EXISTS uc_liquidity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_balance DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
/* default floor */
$db->exec("
INSERT IGNORE INTO uc_liquidity (id, min_balance)
VALUES (1, 50)
");

/* ================= HANDLE ACTION ================= */
$msg = $err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = (float)($_POST['amount'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && $amount > 0) {
        $db->prepare("UPDATE users SET coins = coins + ? WHERE id=?")
           ->execute([$amount, $liq['id']]);
        $msg = "Liquidity added";

    } elseif ($action === 'remove' && $amount > 0 && $amount <= $liq['coins']) {
        $db->prepare("UPDATE users SET coins = coins - ? WHERE id=?")
           ->execute([$amount, $liq['id']]);
        $msg = "Liquidity removed";

    } elseif ($action === 'floor' && $amount > 0) {
        $db->prepare("UPDATE uc_liquidity SET min_balance=? WHERE id=1")
           ->execute([$amount]);
        $msg = "Minimum liquidity updated";

    } else {
        $err = "Invalid request";
    }

    header("Location: liquidity.php");
    exit;
}

/* ================= CURRENT VALUES ================= */
$liqCoins = (float)$db->query("
    SELECT coins FROM users WHERE role='liquidity' LIMIT 1
")->fetchColumn();

$minFloor = (float)$db->query("
    SELECT min_balance FROM uc_liquidity WHERE id=1
")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Liquidity Control</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
    margin:0;
}
.wrap{
    max-width:420px;
    margin:auto;
    padding:20px;
}
.card{
    background:#121826;
    border-radius:20px;
    padding:20px;
    margin-bottom:16px;
}
h2{margin:0 0 10px}
input,button{
    width:100%;
    padding:14px;
    margin-top:10px;
    border-radius:14px;
    border:none;
}
input{background:#020617;color:#fff}
button{
    font-weight:900;
    cursor:pointer;
}
.add{background:#22c55e;color:#022c22}
.remove{background:#ef4444;color:#fff}
.floor{background:#2563eb;color:#fff}
.stat{
    font-size:18px;
    font-weight:900;
}
.msg{color:#22c55e;font-weight:800}
.err{color:#ef4444;font-weight:800}
</style>
</head>

<body>
<div class="wrap">

<div class="card">
<h2>💧 Liquidity Pool</h2>
<div class="stat">Balance: 🪙 <?=number_format($liqCoins,2)?></div>
<div class="stat">Min Floor: 🪙 <?=number_format($minFloor,2)?></div>
</div>

<div class="card">
<form method="post">
<input type="number" step="0.01" name="amount" placeholder="Amount">
<button class="add" name="action" value="add">Add Liquidity</button>
<button class="remove" name="action" value="remove">Remove Liquidity</button>
</form>
</div>

<div class="card">
<form method="post">
<input type="number" step="0.01" name="amount" placeholder="Minimum Liquidity">
<button class="floor" name="action" value="floor">Set Min Floor</button>
</form>
</div>

<a href="dashboard.php" style="color:#9ca3af;text-align:center;display:block">← Back to Admin</a>

</div>
</body>
</html>