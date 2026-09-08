<?php
session_start();
require_once "../db.php";

/* ================= ADMIN AUTH ================= */
$stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
$role = $stmt->fetchColumn();

if ($role !== 'admin') {
    die("ACCESS DENIED");
}

/* ================= ENSURE SETTINGS ROW ================= */
$db->exec("
    INSERT IGNORE INTO settings (id, maintenance, updated_at)
    VALUES (1, 0, CURRENT_TIMESTAMP)
");

/* ================= FETCH STATUS ================= */
$row = $db->query("
    SELECT maintenance, updated_at
    FROM settings
    WHERE id = 1
")->fetch(PDO::FETCH_ASSOC);

$maintenance = (int)$row['maintenance'];
$updatedAt  = $row['updated_at'] ?? null;

/* ================= TOGGLE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $maintenance ? 0 : 1;

    $db->prepare("
        UPDATE settings
        SET maintenance = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = 1
    ")->execute([$new]);

    header("Location: maintenance.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Maintenance Control • Unmoor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#0b0f19;
    --card:#020617;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --danger:#dc2626;
    --success:#16a34a;
}
*{box-sizing:border-box;font-family:system-ui}
body{
    margin:0;
    min-height:100vh;
    background:radial-gradient(circle at top,#111827,#020617);
    color:var(--text);
    display:flex;
    align-items:center;
    justify-content:center;
}
.card{
    width:100%;
    max-width:460px;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:28px;
    box-shadow:0 30px 60px rgba(0,0,0,.6);
}
h2{
    margin:0 0 10px;
    text-align:center;
    letter-spacing:.5px;
}
.status{
    margin:14px 0 24px;
    padding:14px;
    border-radius:14px;
    text-align:center;
    font-weight:800;
}
.status.on{
    background:rgba(220,38,38,.15);
    color:#fecaca;
}
.status.off{
    background:rgba(22,163,74,.15);
    color:#bbf7d0;
}
.meta{
    text-align:center;
    font-size:12px;
    color:var(--muted);
    margin-bottom:22px;
}
button{
    width:100%;
    padding:16px;
    border:none;
    border-radius:16px;
    font-weight:900;
    font-size:15px;
    cursor:pointer;
}
.enable{
    background:linear-gradient(135deg,#dc2626,#b91c1c);
    color:#fff;
}
.disable{
    background:linear-gradient(135deg,#16a34a,#15803d);
    color:#022c22;
}
.back{
    display:block;
    margin-top:22px;
    text-align:center;
    color:#cbd5f5;
    text-decoration:none;
    font-weight:700;
}
.lock{
    font-size:46px;
    text-align:center;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="card">

<div class="lock"><?= $maintenance ? "🔒" : "🔓" ?></div>

<h2>Maintenance Mode</h2>

<div class="status <?= $maintenance ? 'on' : 'off' ?>">
    <?= $maintenance ? "MAINTENANCE ENABLED — SITE LOCKED" : "LIVE MODE — SITE ACCESSIBLE" ?>
</div>

<div class="meta">
    Last updated:
    <?= $updatedAt ? date("d M Y, h:i A", strtotime($updatedAt)) : "Never" ?>
</div>

<form method="post">
    <?php if ($maintenance): ?>
        <button class="disable">Disable Maintenance</button>
    <?php else: ?>
        <button class="enable">Enable Maintenance</button>
    <?php endif; ?>
</form>

<a class="back" href="dashboard.php">← Back to Admin Dashboard</a>

</div>

</body>
</html>
