<?php
session_start();
require_once "../db.php";

/* =========================
   ADMIN AUTH
========================= */
$stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id'] ?? 0]);

if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

/* =========================
   FETCH COUPON HISTORY
========================= */
$history = $db->query("
    SELECT
        c.code,
        c.amount,
        c.type,
        c.status,
        c.used_at,
        u.name,
        u.phone
    FROM coupons c
    LEFT JOIN users u ON u.id = c.used_by
    ORDER BY c.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coupon History • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#0b0f19;
    --panel:#0f172a;
    --panel-2:#020617;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;

    --green:#22c55e;
    --gray:#64748b;
    --blue:#38bdf8;
    --purple:#a78bfa;
}

*{box-sizing:border-box;font-family:system-ui}

body{
    margin:0;
    background:radial-gradient(circle at top,#0b0f19,#020617);
    color:var(--text);
}

.wrap{
    max-width:1280px;
    margin:auto;
    padding:28px;
}

/* ================= CARD ================= */
.card{
    background:linear-gradient(135deg,var(--panel),var(--panel-2));
    border:1px solid var(--border);
    border-radius:26px;
    padding:26px;
    box-shadow:0 30px 70px rgba(0,0,0,.65);
}

h2{
    margin:0 0 20px;
    font-size:22px;
    font-weight:900;
}

/* ================= TABLE WRAP ================= */
.table-wrap{
    overflow-x:auto;
    border-radius:18px;
}

/* ================= TABLE ================= */
table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    min-width:900px;
}

th{
    padding:14px 18px;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.5px;
    color:var(--muted);
    background:#020617;
    border-bottom:1px solid var(--border);
    text-align:left;
}

td{
    padding:18px;
    font-size:14px;
    border-bottom:1px solid var(--border);
    vertical-align:middle;
}

tr:hover{
    background:rgba(255,255,255,.035);
}

tr:last-child td{
    border-bottom:none;
}

/* ================= CELLS ================= */
.code{
    font-weight:900;
    letter-spacing:1px;
    color:#facc15;
}

/* ================= BADGES ================= */
.badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
    display:inline-block;
}

.used{
    background:rgba(34,197,94,.18);
    color:var(--green);
}

.unused{
    background:rgba(100,116,139,.25);
    color:#cbd5f5;
}

.reg{
    background:rgba(56,189,248,.18);
    color:var(--blue);
}

.dep{
    background:rgba(167,139,250,.18);
    color:var(--purple);
}

/* ================= EMPTY ================= */
.empty{
    text-align:center;
    color:var(--muted);
    padding:40px;
    font-weight:700;
}

/* ================= FOOTER LINK ================= */
.back{
    display:block;
    margin-top:22px;
    text-align:center;
    color:var(--muted);
    text-decoration:none;
    font-weight:800;
}
.back:hover{
    color:#c7d2fe;
}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<h2>🎟 Coupon History</h2>

<div class="table-wrap">
<table>
<tr>
    <th>Code</th>
    <th>Type</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Used By</th>
    <th>Used At</th>
</tr>

<?php if ($history): foreach ($history as $c): ?>
<tr>
    <td class="code"><?= htmlspecialchars($c['code']) ?></td>

    <td>
        <?php if ($c['type'] === 'apply'): ?>
            <span class="badge reg">REGISTRATION</span>
        <?php else: ?>
            <span class="badge dep">DEPOSIT</span>
        <?php endif; ?>
    </td>

    <td><?= number_format($c['amount'],2) ?> BDT</td>

    <td>
        <?php if ($c['status'] === 'used'): ?>
            <span class="badge used">USED</span>
        <?php else: ?>
            <span class="badge unused">UNUSED</span>
        <?php endif; ?>
    </td>

    <td>
        <?= $c['name']
            ? htmlspecialchars($c['name'])." <span style='color:#9ca3af'>(".$c['phone'].")</span>"
            : "—"
        ?>
    </td>

    <td>
        <?= $c['used_at']
            ? date("d M Y, h:i A", strtotime($c['used_at']))
            : "—"
        ?>
    </td>
</tr>
<?php endforeach; else: ?>
<tr>
    <td colspan="6" class="empty">No coupon history found</td>
</tr>
<?php endif; ?>
</table>
</div>

<a class="back" href="dashboard.php">← Back to Admin</a>

</div>
</div>
</body>
</html>
