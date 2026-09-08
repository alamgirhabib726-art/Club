<?php
session_start();
require_once __DIR__ . "/../db.php";

/* =========================
   ADMIN / SUB-ADMIN CHECK
========================= */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['admin','sub_admin'])) {
    die("ACCESS DENIED");
}

/* =========================
   FETCH PURCHASE ORDERS
========================= */
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
    WHERE p.type = 'purchase'
    ORDER BY p.id DESC
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Orders</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

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
    padding:20px;
}
.card{
    background:#121826;
    border:1px solid #1f2937;
    border-radius:20px;
    padding:18px;
}
h2{margin:0 0 14px}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:14px;
}
th,td{
    padding:12px;
    border-bottom:1px solid #1f2937;
    text-align:left;
    font-size:14px;
}
th{
    color:#9ca3af;
    font-weight:700;
}
.status{
    font-weight:800;
}
.pending{color:#facc15}
.approved{color:#22c55e}
.rejected{color:#ef4444}

.btn{
    padding:6px 12px;
    border-radius:10px;
    text-decoration:none;
    font-weight:800;
    font-size:13px;
    margin-right:6px;
}
.approve{background:#22c55e;color:#022c22}
.reject{background:#ef4444;color:#fff}

.back{
    display:block;
    margin-top:16px;
    text-align:center;
    color:#9ca3af;
    text-decoration:none;
}
</style>
</head>

<body>

<div class="wrap">
<div class="card">

<h2>🛒 Purchase Orders</h2>

<table>
<tr>
    <th>User</th>
    <th>Phone</th>
    <th>Coins</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php if($orders): foreach($orders as $o): ?>
<tr>
    <td><?=htmlspecialchars($o['name'])?></td>
    <td><?=htmlspecialchars($o['phone'])?></td>
    <td>🪙 <?=number_format($o['amount'],2)?></td>
    <td class="status <?=$o['status']?>"><?=strtoupper($o['status'])?></td>
    <td><?=date("d M Y, h:i A", strtotime($o['created_at']))?></td>
    <td>
        <?php if($o['status']==='pending'): ?>
            <a class="btn approve"
               href="purchase_action.php?id=<?=$o['id']?>&action=approve">
               Approve
            </a>
            <a class="btn reject"
               href="purchase_action.php?id=<?=$o['id']?>&action=reject">
               Reject
            </a>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; else: ?>
<tr>
    <td colspan="6">No purchase orders</td>
</tr>
<?php endif; ?>
</table>

<a class="back" href="dashboard.php">← Admin Dashboard</a>

</div>
</div>

</body>
</html>
