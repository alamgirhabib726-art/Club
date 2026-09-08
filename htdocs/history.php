<?php
session_start();
require_once "db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= FETCH LEDGER (FIXED) ================= */
$stmt = $db->prepare("
    SELECT amount, type,
           source_name, source_number,
           reference, created_at
    FROM coin_history
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 50
");
$stmt->execute([$uid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ledger • Unmoor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrap{
    max-width:520px;
    margin:auto;
    padding:16px;
}
.card{
    background:#121826;
    border:1px solid #1f2937;
    border-radius:22px;
    padding:16px;
}
h3{margin:0 0 12px}

.row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px dashed #1f2937;
    padding:12px 0;
}
.row:last-child{border-bottom:none}

.type{
    font-size:12px;
    font-weight:800;
    color:#9ca3af;
    text-transform:uppercase;
}
.ref{
    font-size:14px;
    margin-top:4px;
}
.time{
    font-size:11px;
    color:#6b7280;
    margin-top:4px;
}

.plus{
    color:#22c55e;
    font-weight:900;
    font-size:15px;
}
.minus{
    color:#ef4444;
    font-weight:900;
    font-size:15px;
}

.back{
    display:block;
    text-align:center;
    margin-top:14px;
    color:#9ca3af;
    text-decoration:none;
    font-weight:700;
}
</style>
</head>

<body>

<div class="wrap">
<div class="card">

<h3>📒 Account Ledger</h3>

<?php if ($rows): foreach ($rows as $r): ?>
<div class="row">
    <div>
        <div class="type"><?= htmlspecialchars($r['type']) ?></div>

        <?php if (!empty($r['source_name'])): ?>
            <div class="ref">
                <?= htmlspecialchars($r['source_name']) ?>
                <?php if (!empty($r['source_number'])): ?>
                    (<?= htmlspecialchars($r['source_number']) ?>)
                <?php endif; ?>
            </div>
        <?php elseif (!empty($r['reference'])): ?>
            <div class="ref"><?= htmlspecialchars($r['reference']) ?></div>
        <?php endif; ?>

        <div class="time">
            <?= date("d M Y, h:i A", strtotime($r['created_at'])) ?>
        </div>
    </div>

    <div class="<?= $r['amount'] >= 0 ? 'plus' : 'minus' ?>">
        <?= $r['amount'] >= 0 ? '+' : '−' ?>
        <?= number_format(abs((float)$r['amount']), 2) ?>
    </div>
</div>
<?php endforeach; else: ?>
<p style="color:#9ca3af">No transactions yet</p>
<?php endif; ?>

</div>

<a class="back" href="dashboard.php">← Back to Dashboard</a>
</div>

</body>
</html>
