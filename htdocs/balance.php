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
<title>Balance History</title>
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:10px;border-bottom:1px solid #e5e7eb}
th{background:#f1f5f9;text-align:left}
.credit{color:#16a34a;font-weight:600}
.debit{color:#dc2626;font-weight:600}
</style>
</head>
<body>

<h3>💰 My Balance History</h3>

<?php if (!$logs): ?>
    <p>No balance activity yet.</p>
<?php else: ?>
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
<?php endif; ?>

<br>
<a href="dashboard.php">← Dashboard</a>

</body>
</html>
