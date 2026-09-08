<?php
session_start();
require_once "../db.php";

/* ===== ADMIN GUARD ===== */
if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.php");
    exit;
}

$stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$role = $stmt->fetchColumn();

if ($role !== 'admin') {
    die("ACCESS DENIED");
}

/* ===== CURRENT MARKET INFO ===== */
$row = $db->query("
    SELECT price, created_at 
    FROM uc_market 
    ORDER BY id DESC 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$currentPrice = $row['price'] ?? null;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Market Seed • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{
    background:#020617;
    color:#e5e7eb;
    font-family:system-ui;
    padding:20px;
}
.card{
    max-width:420px;
    margin:auto;
    background:#0b1220;
    border-radius:18px;
    padding:20px;
    border:1px solid #1f2937;
}
h2{margin-top:0}
input{
    width:100%;
    padding:12px;
    border-radius:12px;
    border:1px solid #1f2937;
    background:#020617;
    color:#fff;
    margin-bottom:12px;
}
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    font-weight:900;
    cursor:pointer;
}
.status{
    margin-top:10px;
    font-size:13px;
    opacity:.9;
}
</style>
</head>
<body>

<div class="card">
    <h2>📊 Market Seed</h2>

    <div class="status">
        Current Price:
        <strong>
            <?= $currentPrice ? "৳ ".number_format($currentPrice,2) : "Not seeded" ?>
        </strong>
    </div>

    <form method="post" action="market_seed_action.php">
        <input type="number" step="0.01" name="price"
               placeholder="Seed price (e.g. 10.00)" required>

        <button type="submit">🌱 Seed Market</button>
    </form>

    <div class="status">
        ⚠️ Use once or few times only.  
        Market engine will move price later.
    </div>
</div>

</body>
</html>
