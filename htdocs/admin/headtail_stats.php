<?php
session_start();
require_once "../db.php";

/* ================= ADMIN GUARD ================= */
if (!isset($_SESSION['user_id'])) {
    die("NO SESSION");
}

$stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ADMIN ONLY");
}

/* ================= GET SYSTEM ID ================= */
$systemId = (int)$db->query("
    SELECT id FROM users WHERE role='system' LIMIT 1
")->fetchColumn();

if (!$systemId) {
    die("SYSTEM ACCOUNT NOT FOUND");
}

/* ================= CALCULATE STATS (SYSTEM ONLY) ================= */

/* SYSTEM PROFIT = system gains (amount > 0) */
$profitStmt = $db->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM coin_history
    WHERE user_id = ?
      AND type = 'game_win'
");
$profitStmt->execute([$systemId]);
$systemProfit = (float)$profitStmt->fetchColumn();

/* SYSTEM LOSS = system payouts (amount < 0) */
$lossStmt = $db->prepare("
    SELECT COALESCE(SUM(ABS(amount)),0)
    FROM coin_history
    WHERE user_id = ?
      AND type = 'game_loss'
");
$lossStmt->execute([$systemId]);
$systemLoss = (float)$lossStmt->fetchColumn();

/* NET RESULT */
$net = $systemProfit - $systemLoss;

/* ================= FETCH SYSTEM GAME HISTORY ================= */
$stmt = $db->prepare("
    SELECT amount, type, source_name, source_number, created_at
    FROM coin_history
    WHERE user_id = ?
      AND type IN ('game_win','game_loss')
    ORDER BY id DESC
    LIMIT 200
");
$stmt->execute([$systemId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Head / Tail Stats • Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrap{
    max-width:900px;
    margin:auto;
    padding:24px;
}
.card{
    background:#121826;
    border-radius:22px;
    padding:22px;
    margin-bottom:20px;
}
.stat-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
}
.stat{
    background:#020617;
    border-radius:18px;
    padding:18px;
    text-align:center;
}
.stat h3{margin:0;font-size:14px;color:#9ca3af}
.stat div{margin-top:6px;font-size:20px;font-weight:900}

.green{color:#22c55e}
.red{color:#ef4444}

.row{
    display:flex;
    justify-content:space-between;
    padding:14px 0;
    border-bottom:1px dashed #1f2937;
}
.row:last-child{border-bottom:none}

.type{
    font-size:12px;
    font-weight:800;
    color:#9ca3af;
    text-transform:uppercase;
}
.from{
    font-size:13px;
    margin-top:4px;
}
.time{
    font-size:11px;
    color:#64748b;
}
.plus{color:#22c55e;font-weight:900}
.minus{color:#ef4444;font-weight:900}

.back{
    display:block;
    margin-top:18px;
    text-align:center;
    color:#9ca3af;
    text-decoration:none;
    font-weight:700;
}
</style>
</head>

<body>
<div class="wrap">

<div class="card">
<h2>🎲 Head / Tail — System Stats</h2>

<div class="stat-grid">
    <div class="stat">
        <h3>SYSTEM PROFIT</h3>
        <div class="green">🪙 <?=number_format($systemProfit,2)?></div>
    </div>
    <div class="stat">
        <h3>SYSTEM LOSS</h3>
        <div class="red">🪙 <?=number_format($systemLoss,2)?></div>
    </div>
    <div class="stat">
        <h3>NET RESULT</h3>
        <div class="<?= $net >= 0 ? 'green':'red' ?>">
            🪙 <?=number_format($net,2)?>
        </div>
    </div>
</div>
</div>

<div class="card">
<h3>📜 Recent Head / Tail Activity</h3>

<?php if ($rows): foreach ($rows as $r): ?>
<div class="row">
    <div>
        <div class="type">
            <?= $r['amount'] > 0 ? 'USER LOST (SYSTEM +)' : 'USER WON (SYSTEM -)' ?>
        </div>
        <?php if($r['source_name']): ?>
            <div class="from">
                From: <?= htmlspecialchars($r['source_name']) ?>
                <?= $r['source_number'] ? '(' . htmlspecialchars($r['source_number']) . ')' : '' ?>
            </div>
        <?php endif; ?>
        <div class="time"><?= date("d M Y, h:i A", strtotime($r['created_at'])) ?></div>
    </div>
    <div class="<?= $r['amount'] > 0 ? 'plus' : 'minus' ?>">
        <?= $r['amount'] > 0 ? '+' : '−' ?>
        <?= number_format(abs($r['amount']),2) ?>
    </div>
</div>
<?php endforeach; else: ?>
<p style="color:#9ca3af">No head/tail records yet.</p>
<?php endif; ?>
</div>

<a class="back" href="dashboard.php">← Back to Admin Dashboard</a>

</div>
</body>
</html>