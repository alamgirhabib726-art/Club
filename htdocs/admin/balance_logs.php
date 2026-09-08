<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ===============================
   ADMIN AUTH CHECK
================================ */
if (!isset($_SESSION['user_id'])) {
    die("NO SESSION");
}

$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);

if ($stmt->fetchColumn() !== 'admin') {
    die("ADMIN ONLY");
}

/* ===============================
   FETCH LOGS
================================ */
$rows = $db->query("
    SELECT
        l.amount,
        l.note,
        l.created_at,
        u.phone
    FROM admin_balance_logs l
    JOIN users u ON u.id = l.user_id
    ORDER BY l.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Balance Logs • Admin</title>
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:10px;border:1px solid #e5e7eb;text-align:left}
th{background:#f9fafb}
</style>
</head>

<body>

<h3>💳 Balance Logs</h3>

<table>
<tr>
    <th>User Phone</th>
    <th>Amount</th>
    <th>Note</th>
    <th>Date</th>
</tr>

<?php if (!$rows): ?>
<tr>
    <td colspan="4">No balance actions yet</td>
</tr>
<?php else: ?>
<?php foreach ($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r['phone']) ?></td>
    <td>৳<?= number_format($r['amount'],2) ?></td>
    <td><?= htmlspecialchars($r['note'] ?: '—') ?></td>
    <td><?= htmlspecialchars($r['created_at']) ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>

</table>

</body>
</html>
