<?php
require_once "guard.php";
require_once "../db.php";

$logs = $db->query("
 SELECT l.*,u.phone 
 FROM admin_balance_logs l
 JOIN users u ON u.id=l.user_id
 ORDER BY l.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><body>
<h3>📜 Admin Logs</h3>
<table border="1">
<tr><th>User</th><th>Amount</th><th>Note</th><th>Date</th></tr>
<?php foreach($logs as $l): ?>
<tr>
<td><?=$l['phone']?></td>
<td><?=$l['amount']?></td>
<td><?=$l['note']?></td>
<td><?=$l['created_at']?></td>
</tr>
<?php endforeach; ?>
</table>
</body></html>