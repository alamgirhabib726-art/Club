<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ADMIN CHECK */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

/* FETCH DONATIONS */
$stmt = $db->prepare("
    SELECT 
        p.id,
        p.amount,
        p.status,
        p.created_at,
        u.name,
        u.phone
    FROM payments p
    JOIN users u ON u.id = p.user_id
    WHERE p.type = 'donation'
    ORDER BY p.id DESC
");
$stmt->execute();
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Donations • Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;background:#0b0f19;color:#e5e7eb;font-family:system-ui}
.wrap{max-width:900px;margin:auto;padding:20px}
.card{background:#121826;border-radius:20px;padding:20px}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #1f2937}
th{color:#9ca3af;font-size:13px}
.green{color:#22c55e;font-weight:700}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<h2>💚 Donation History</h2>

<table>
<tr>
    <th>User</th>
    <th>Phone</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php if($donations): foreach($donations as $d): ?>
<tr>
    <td><?=htmlspecialchars($d['name'])?></td>
    <td><?=htmlspecialchars($d['phone'])?></td>
    <td class="green">৳<?=number_format($d['amount'],2)?></td>
    <td><?=strtoupper($d['status'])?></td>
    <td><?=date("d M Y, h:i A", strtotime($d['created_at']))?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="5">No donations yet</td></tr>
<?php endif; ?>

</table>

</div>
</div>
</body>
</html>
