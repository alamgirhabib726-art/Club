<?php
require_once __DIR__ . "/guard.php";
require_once __DIR__ . "/../db.php";

$logs = $db->query("
 SELECT l.*, u.phone 
 FROM admin_balance_logs l
 JOIN users u ON u.id = l.user_id
 ORDER BY l.id DESC
 LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Activity Logs • Unmoor</title>
<link rel="stylesheet" href="../assets/admin.css">
<style>
.wrap{max-width:1100px;margin:24px auto;padding:16px}
.card{background:#020617;border:1px solid #1f2937;border-radius:18px;padding:24px;box-shadow:0 20px 40px rgba(0,0,0,.6)}
h2{margin:0 0 16px;color:#f8fafc;font-size:20px;display:flex;align-items:center;gap:10px}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{padding:12px 14px;border-bottom:1px solid #1f2937;font-size:14px;text-align:left}
th{color:#9ca3af;background:#0f172a;font-weight:600;text-transform:uppercase;font-size:12px;letter-spacing:0.5px}
td{color:#e5e7eb}
tr:hover{background:#0b0f19}
.back{display:inline-block;margin-top:18px;color:#38bdf8;text-decoration:none;font-weight:500}
.back:hover{text-decoration:underline}
.amount{font-weight:600;color:#22c55e}
</style>
</head>
<body>

<div class="wrap">
  <div class="card">
    <h2>📜 System Activity Logs</h2>

    <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Amount</th>
          <th>Note</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($logs): ?>
          <?php foreach($logs as $l): ?>
          <tr>
            <td><strong><?= htmlspecialchars($l['phone']) ?></strong></td>
            <td class="amount">৳<?= number_format($l['amount'], 2) ?></td>
            <td><?= htmlspecialchars($l['note'] ?: '-') ?></td>
            <td style="color:#9ca3af"><?= date("d M Y, h:i A", strtotime($l['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4" style="text-align:center;color:#64748b">No activity logs found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>

    <a class="back" href="dashboard.php">← Back to Admin</a>
  </div>
</div>

</body>
</html>
