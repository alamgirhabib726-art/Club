<?php
session_start();
require_once "db.php";

/* LOGIN CHECK */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH USER */
$stmt = $db->prepare("
    SELECT status, apply_status
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

/* FETCH PURCHASE HISTORY */
$stmt = $db->prepare("
    SELECT source, amount, status, created_at
    FROM payments
    WHERE user_id = ?
      AND type = 'purchase'
    ORDER BY id DESC
");
$stmt->execute([$uid]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* PRODUCT LABELS */
$labels = [
    'power' => '⚡ Power Click',
    'joint' => '🤝 Joint Click',
    'paper' => '📄 Paper Click'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase History • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#0b0f19;
    --card:#121826;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --green:#22c55e;
    --yellow:#facc15;
    --red:#ef4444;
}
*{box-sizing:border-box;font-family:system-ui}
body{
    margin:0;
    background:var(--bg);
    color:var(--text);
}
.wrap{
    max-width:480px;
    margin:auto;
    padding:16px;
}
.card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:18px;
}
h2{
    margin:0 0 14px;
    text-align:center;
}
.item{
    border:1px solid var(--border);
    border-radius:18px;
    padding:14px;
    margin-top:12px;
}
.row{
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.product{
    font-weight:800;
    font-size:15px;
}
.coins{
    font-weight:800;
}
.status{
    margin-top:6px;
    font-size:13px;
    font-weight:800;
}
.pending{color:var(--yellow)}
.approved{color:var(--green)}
.rejected{color:var(--red)}
.time{
    font-size:12px;
    color:var(--muted);
    margin-top:4px;
}
.back{
    display:block;
    margin-top:18px;
    text-align:center;
    text-decoration:none;
    color:var(--muted);
    font-weight:700;
}
.empty{
    text-align:center;
    color:var(--muted);
    padding:30px 0;
}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<h2>📜 Purchase History</h2>

<?php if ($orders): foreach ($orders as $o): ?>
<div class="item">
    <div class="row">
        <div class="product">
            <?= htmlspecialchars($labels[$o['source']] ?? ucfirst($o['source'])) ?>
        </div>
        <div class="coins">
            🪙 <?= number_format($o['amount'],2) ?>
        </div>
    </div>

    <div class="status <?= $o['status'] ?>">
        <?= strtoupper($o['status']) ?>
    </div>

    <div class="time">
        <?= date("d M Y • h:i A", strtotime($o['created_at'])) ?>
    </div>
</div>
<?php endforeach; else: ?>
    <div class="empty">No purchases yet</div>
<?php endif; ?>

<a class="back" href="purchase.php">← Back to Purchase</a>

</div>
</div>
</body>
</html>
