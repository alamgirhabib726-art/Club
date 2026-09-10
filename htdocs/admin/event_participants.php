<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ADMIN CHECK */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

/* FETCH PARTICIPANTS */
$stmt = $db->prepare("
    SELECT 
        e.title,
        e.coin_cost,
        u.name,
        u.phone,
        ep.joined_at
    FROM event_participants ep
    JOIN events e ON e.id = ep.event_id
    JOIN users u ON u.id = ep.user_id
    ORDER BY ep.joined_at DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Event Participation • Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;background:#0b0f19;color:#e5e7eb;font-family:system-ui}
.wrap{max-width:900px;margin:auto;padding:20px}
.card{background:#121826;border-radius:20px;padding:20px}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #1f2937}
th{color:#9ca3af;font-size:13px}
.yellow{color:#facc15;font-weight:700}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<h2>🎉 Event Participants</h2>

<div class="table-responsive">
<table>
<tr>
    <th>Event</th>
    <th>Cost</th>
    <th>User</th>
    <th>Phone</th>
    <th>Joined At</th>
</tr>

<?php if($rows): foreach($rows as $r): ?>
<tr>
    <td><?=htmlspecialchars($r['title'])?></td>
    <td class="yellow">🪙 <?=number_format($r['coin_cost'],2)?></td>
    <td><?=htmlspecialchars($r['name'])?></td>
    <td><?=htmlspecialchars($r['phone'])?></td>
    <td><?=date("d M Y, h:i A", strtotime($r['joined_at']))?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="5">No participants yet</td></tr>
<?php endif; ?>

</table>
</div>

<a href="dashboard.php" style="display:inline-block;margin-top:16px;color:#38bdf8;text-decoration:none;font-weight:500">← Back to Admin</a>

</div>
</div>
</body>
</html>
