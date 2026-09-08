<?php
session_start();
require_once "../db.php";

/* ================= ADMIN CHECK ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$role = $stmt->fetchColumn();

if ($role !== 'admin') {
    die("ACCESS DENIED");
}

/* ================= FILTER ================= */
$type = $_GET['type'] ?? 'all';

/* REMOVE DONATION PERMANENTLY */
$where  = "WHERE p.type != 'donation'";
$params = [];

if ($type !== 'all') {
    $where .= " AND p.type = ?";
    $params[] = $type;
}

/* ================= FETCH PAYMENTS ================= */
$stmt = $db->prepare("
    SELECT
        p.id,
        p.type,
        p.amount,
        p.status,
        p.created_at,
        p.proof,
        p.method,
        u.name,
        u.phone
    FROM payments p
    JOIN users u ON u.id = p.user_id
    $where
    ORDER BY p.id DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payments • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#020617;
    --panel:#0b1020;
    --card:#0f172a;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#94a3b8;

    --green:#22c55e;
    --red:#ef4444;
    --yellow:#facc15;
}

*{
    box-sizing:border-box;
    font-family:system-ui;
}

body{
    margin:0;
    background:radial-gradient(circle at top,#020617,#000);
    color:var(--text);
    padding:20px;
}

/* ================= HEADER ================= */
h2{
    margin:0 0 16px;
    font-size:22px;
    font-weight:900;
}

/* ================= FILTER ================= */
.filter{
    margin-bottom:16px;
}
.filter a{
    margin-right:16px;
    text-decoration:none;
    font-weight:800;
    font-size:14px;
    color:var(--muted);
    padding-bottom:6px;
}
.filter a.active{
    color:var(--yellow);
    border-bottom:2px solid var(--yellow);
}

/* ================= TABLE WRAP (FIX SCROLL) ================= */
.table-scroll{
    overflow-x:auto;            /* 🔥 FIX */
    -webkit-overflow-scrolling:touch;
}

.table-wrap{
    min-width:1100px;           /* 🔥 FORCE WIDTH */
    background:linear-gradient(135deg,#0f172a,#020617);
    border:1px solid var(--border);
    border-radius:22px;
    overflow:hidden;
    box-shadow:0 30px 60px rgba(0,0,0,.7);
}

table{
    width:100%;
    border-collapse:collapse;
}

/* ================= TABLE ================= */
th{
    background:#020617;
    padding:14px 16px;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.5px;
    color:var(--muted);
    border-bottom:1px solid var(--border);
    text-align:left;
    white-space:nowrap;
}

td{
    padding:16px;
    font-size:14px;
    border-bottom:1px solid var(--border);
    white-space:nowrap;
}

tr:hover{
    background:rgba(255,255,255,.03);
}

tr:last-child td{
    border-bottom:none;
}

/* ================= BADGES ================= */
.badge{
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
}

.pending{
    background:rgba(250,204,21,.18);
    color:var(--yellow);
}
.approved{
    background:rgba(34,197,94,.2);
    color:var(--green);
}
.rejected{
    background:rgba(239,68,68,.2);
    color:var(--red);
}

/* ================= ACTIONS ================= */
.actions{
    display:flex;
    gap:12px;
}

.btn{
    padding:8px 16px;
    border-radius:12px;
    text-decoration:none;
    font-size:13px;
    font-weight:900;
    min-width:90px;
    text-align:center;
}

.approve{
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
}
.reject{
    background:linear-gradient(135deg,#ef4444,#dc2626);
    color:#fff;
}

/* ================= PROOF ================= */
.proof img{
    height:44px;
    border-radius:10px;
    border:1px solid var(--border);
}
.no-proof{
    color:var(--red);
    font-weight:900;
}

/* ================= MISC ================= */
.small{
    font-size:12px;
    color:var(--muted);
}
</style>
</head>

<body>

<h2>💳 Payments</h2>

<div class="filter">
    <a class="<?= $type==='all'?'active':'' ?>" href="?type=all">All</a>
    <a class="<?= $type==='apply'?'active':'' ?>" href="?type=apply">Apply</a>
    <a class="<?= $type==='deposit'?'active':'' ?>" href="?type=deposit">Deposit</a>
    <a class="<?= $type==='purchase'?'active':'' ?>" href="?type=purchase">Purchase</a>
</div>

<div class="table-scroll">
<div class="table-wrap">
<table>
<tr>
    <th>ID</th>
    <th>User</th>
    <th>Phone</th>
    <th>Type</th>
    <th>Amount (BDT)</th>
    <th>Method</th>
    <th>Proof</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php if (!$payments): ?>
<tr>
    <td colspan="10">No payments found</td>
</tr>
<?php endif; ?>

<?php foreach ($payments as $p): ?>

<?php
/* FIX PURCHASE AMOUNT */
$amountBDT = ($p['type'] === 'purchase')
    ? $p['amount'] * 10
    : $p['amount'];
?>

<tr>
    <td><?= (int)$p['id'] ?></td>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['phone']) ?></td>
    <td><?= strtoupper($p['type']) ?></td>
    <td>৳<?= number_format($amountBDT,2) ?></td>
    <td><?= $p['method'] ?: '—' ?></td>

    <td class="proof">
        <?php if (!empty($p['proof'])):
            $proofPath = "../uploads/".$p['type']."/".$p['proof']; ?>
            <a href="<?= htmlspecialchars($proofPath) ?>" target="_blank">
                <img src="<?= htmlspecialchars($proofPath) ?>">
            </a>
        <?php else: ?>
            <span class="no-proof">No Proof</span>
        <?php endif; ?>
    </td>

    <td>
        <span class="badge <?= $p['status'] ?>">
            <?= strtoupper($p['status']) ?>
        </span>
    </td>

    <td class="small">
        <?= date("d M Y, h:i A", strtotime($p['created_at'])) ?>
    </td>

    <td>
        <?php if ($p['status'] === 'pending'): ?>
            <div class="actions">
                <?php if (!empty($p['proof'])): ?>
                    <a class="btn approve"
                       href="payment_action.php?id=<?= $p['id'] ?>&action=approve">
                        Approve
                    </a>
                <?php endif; ?>
                <a class="btn reject"
                   href="payment_action.php?id=<?= $p['id'] ?>&action=reject">
                    Reject
                </a>
            </div>
        <?php else: ?>
            —
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>

</body>
</html>
