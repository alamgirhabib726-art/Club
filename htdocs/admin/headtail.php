<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ADMIN CHECK */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);

if ($stmt->fetchColumn() !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

/* FETCH BETS */
$rows = $db->query("
    SELECT h.*, u.phone
    FROM headtail_bets h
    JOIN users u ON u.id = h.user_id
    ORDER BY h.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Head / Tail Bets • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:10px;border:1px solid #e5e7eb;text-align:center}
th{background:#020617;color:#fff}
</style>
</head>

<body>

<h3>🎲 Head / Tail Bets</h3>

<table>
<tr>
<th>User</th>
<th>Choice</th>
<th>Result</th>
<th>Bet (৳)</th>
<th>Profit (৳)</th>
<th>Date</th>
</tr>

<?php foreach($rows as $r): ?>
<tr>
<td><?= htmlspecialchars($r['phone']) ?></td>
<td><?= htmlspecialchars($r['choice']) ?></td>
<td><?= htmlspecialchars($r['result']) ?></td>
<td><?= $r['bet_amount'] ?></td>
<td><?= $r['profit'] ?></td>
<td><?= $r['created_at'] ?></td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>
