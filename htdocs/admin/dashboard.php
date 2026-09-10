<?php
session_start();
require_once __DIR__ . "/../db.php";

/* =========================
   ADMIN GUARD
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.php");
    exit;
}

$stmt = $db->prepare("SELECT name, role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || $admin['role'] !== 'admin') {
    die("ACCESS DENIED");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard • Unmoor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
/* =========================
   THEME VARIABLES
========================= */
:root{
    --bg:#0b0f19;
    --sidebar:#020617;
    --panel:#0f172a;
    --border:#1f2937;

    --text:#e5e7eb;
    --muted:#94a3b8;

    --accent:#22c55e;
    --accent-soft:rgba(34,197,94,.15);

    --gold:#facc15;
    --danger:#ef4444;
}

/* RESET */
*{
    box-sizing:border-box;
    font-family:system-ui,-apple-system,Segoe UI,Roboto;
}

body{
    margin:0;
    background:var(--bg);
    color:var(--text);
}

/* =========================
   SIDEBAR
========================= */
.sidebar{
    position:fixed;
    inset:0 auto 0 0;
    width:260px;

    background:linear-gradient(180deg,#020617,#020617);
    border-right:1px solid var(--border);

    padding:22px 16px;
    overflow-y:auto;
}

/* BRAND */
.brand{
    font-size:19px;
    font-weight:900;
    letter-spacing:1.5px;
    margin-bottom:26px;
    color:var(--gold);
}

/* MENU LINK */
.sidebar a{
    display:flex;
    align-items:center;
    gap:12px;

    padding:12px 14px;
    margin-bottom:6px;

    border-radius:12px;
    text-decoration:none;

    font-size:14px;
    font-weight:800;
    color:#cbd5f5;

    transition:all .2s ease;
}

.sidebar a:hover{
    background:#111827;
    transform:translateX(2px);
}

/* SECTION TITLE */
.sidebar .section{
    margin:18px 0 6px;
    padding-left:8px;

    font-size:11px;
    letter-spacing:.6px;
    text-transform:uppercase;

    color:var(--muted);
    pointer-events:none;
}

/* LOGOUT */
.sidebar a.danger{
    color:#fecaca;
}
.sidebar a.danger:hover{
    background:rgba(239,68,68,.15);
}

/* =========================
   MAIN AREA
========================= */
.main{
    margin-left:260px;
    padding:32px;
}

/* HEADER */
.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
}

.header h1{
    margin:0;
    font-size:24px;
    font-weight:900;
}

.header span{
    font-size:13px;
    color:var(--muted);
}

/* =========================
   DASH CARDS
========================= */
.card{
    background:linear-gradient(135deg,#0f172a,#020617);
    border:1px solid var(--border);
    border-radius:22px;

    padding:24px;
    margin-bottom:22px;

    box-shadow:
        0 20px 40px rgba(0,0,0,.45),
        inset 0 1px 0 rgba(255,255,255,.04);
}

.card h3{
    margin:0 0 8px;
    font-size:16px;
    font-weight:900;
}

.card p{
    margin:0;
    font-size:14px;
    color:var(--muted);
}

/* STATUS BADGE */
.status{
    display:inline-flex;
    align-items:center;
    gap:8px;

    margin-top:14px;
    padding:6px 14px;

    border-radius:999px;
    font-size:12px;
    font-weight:900;

    background:var(--accent-soft);
    color:var(--accent);
}

/* STATUS DOT */
.status::before{
    content:"";
    width:8px;
    height:8px;
    border-radius:50%;
    background:var(--accent);
    box-shadow:0 0 10px rgba(34,197,94,.8);
}

/* =========================
   MOBILE RESPONSIVE
========================= */
@media (max-width: 768px){
    .sidebar{
        position:static;
        width:100%;
        border-right:none;
        border-bottom:1px solid var(--border);
        padding:16px;
    }
    .main{
        margin-left:0;
        padding:18px 14px;
    }
    .header{
        flex-direction:column;
        align-items:flex-start;
        gap:6px;
    }
    .brand{
        margin-bottom:14px;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand">UNMOOR ADMIN</div>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="users.php">👥 Users</a>

    <div class="section">Payments</div>
    <a href="payments.php">💳 Payments</a>
    <a href="donations.php">💚 Donations</a>

    <div class="section">Events</div>
    <a href="events.php">🎉 Events</a>
    <a href="event_participants.php">📋 Event Participants</a>

    <div class="section">Coupons</div>
    <a href="coupons.php">🎟 All Coupons</a>
    <a href="coupon_history.php">📜 Coupon History</a>

    <div class="section">System</div>
    <a href="system_ledger.php">📊 System Ledger</a>
    <a href="headtail_stats.php">🎲 Head/Tail P&amp;L</a>
    <a href="liquidity.php">💧 Liquidity Control</a>

    <div class="section">Tools</div>
    <a href="earn.php">💰 Earn Buttons</a>
    <a href="notices.php">📢 Notice Board</a>
    <a href="export_csv.php">📤 Export CSV</a>
    <a href="maintenance.php">🛠 Maintenance</a>

    <a class="danger" href="logout.php">🚪 Logout</a>
</aside>

<!-- MAIN -->
<main class="main">

    <div class="header">
        <h1>Welcome, <?= htmlspecialchars($admin['name']) ?></h1>
        <span>Administrator Control Panel</span>
    </div>

    <div class="card">
        <h3>System Status</h3>
        <p>All core services are operating normally.</p>
        <div class="status">ONLINE</div>
    </div>

    <div class="card">
        <h3>Admin Privileges</h3>
        <p>You have full access to system controls, financial data, and user management.</p>
    </div>

</main>

</body>
</html>
