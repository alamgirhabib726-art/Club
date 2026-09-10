<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT amount,
           COALESCE(action, 'credit') AS action,
           note,
           created_at
    FROM admin_balance_logs
    WHERE user_id = ?
    ORDER BY id DESC
");
$stmt->execute([$uid]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Balance History</title>
<style>
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0b0f19;color:#e5e7eb;padding:20px;margin:0}
.wrap{max-width:800px;margin:0 auto;background:#111827;border-radius:16px;padding:24px;border:1px solid #1f2937}
.table-responsive{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{padding:12px;border-bottom:1px solid #1f2937;text-align:left}
th{background:#1f2937;color:#9ca3af;font-size:13px;text-transform:uppercase;letter-spacing:0.5px}
.credit{color:#22c55e;font-weight:600}
.debit{color:#ef4444;font-weight:600}
.back-btn{display:inline-block;margin-top:20px;color:#38bdf8;text-decoration:none;font-weight:500}
.back-btn:hover{text-decoration:underline}
@media (max-width:640px){
    body{padding:12px}
    .wrap{padding:16px}
    th,td{padding:8px 10px;font-size:14px}
}
</style>
</head>
<body>

<div class="wrap">
<h3>💰 My Balance History</h3>

<?php if (!$logs): ?>
    <p style="color:#9ca3af">No balance activity yet.</p>
<?php else: ?>
<div class="table-responsive">
<table>
<tr>
    <th>Type</th>
    <th>Amount</th>
    <th>Note</th>
    <th>Date</th>
</tr>
<?php foreach ($logs as $l): ?>
<tr>
    <td class="<?= $l['action'] === 'debit' ? 'debit' : 'credit' ?>">
        <?= strtoupper($l['action']) ?>
    </td>
    <td>৳<?= number_format($l['amount'], 2) ?></td>
    <td><?= htmlspecialchars($l['note'] ?: '-') ?></td>
    <td><?= date("d M Y, h:i A", strtotime($l['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<a class="back-btn" href="dashboard.php">← Back to Dashboard</a>
</div>

</body>
</html>
