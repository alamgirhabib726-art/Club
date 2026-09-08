<?php
session_start();
require_once "db.php";

/* ================= LOGIN REQUIRED ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= USER CHECK ================= */
$stmt = $db->prepare("
    SELECT status, apply_status
    FROM users
    WHERE id = ?
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active' || $user['apply_status'] !== 'approved') {
    die("ACCESS DENIED");
}

/* ================= FETCH ACTIVE EARN BUTTONS ================= */
$stmt = $db->query("
    SELECT title, link
    FROM earn_buttons
    WHERE status = 'active'
    ORDER BY id DESC
");
$buttons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Earn • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#0b0f19;
    --card:#121826;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --accent:#22c55e;
}
body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:system-ui;
}
.wrapper{
    max-width:420px;
    margin:auto;
    padding:18px;
}
.card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:20px;
}
h2{
    margin:0 0 14px;
    text-align:center;
}
.earn-btn{
    display:block;
    padding:16px;
    margin-bottom:12px;
    border-radius:18px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    font-weight:900;
    text-align:center;
    text-decoration:none;
    transition:.2s;
}
.earn-btn:hover{
    transform:scale(1.02);
}
.empty{
    text-align:center;
    color:var(--muted);
    font-size:14px;
}
.back{
    display:block;
    text-align:center;
    margin-top:16px;
    color:var(--muted);
    text-decoration:none;
    font-weight:700;
}
/* =========================
   MANUAL BOTTOM NAV (FINAL STABLE)
========================= */

.manual-nav{
    position:fixed;
    bottom:0;
    left:0;
    right:0;

    height:88px; /* 🔥 lighter than 96 */
    background:linear-gradient(
        180deg,
        rgba(15,23,42,.88),
        rgba(2,6,23,.94)
    );

    backdrop-filter:blur(12px);
    border-top:1px solid rgba(255,255,255,.07);

    display:flex;
    justify-content:space-around;
    align-items:flex-end;

    padding-bottom:10px;
    z-index:999;
}

/* ================= BUTTON BASE ================= */
.manual-nav .nav-btn{
    flex:1;
    text-decoration:none;
    color:#e5e7eb;
    font-size:12px;
    font-weight:800;

    display:flex;
    flex-direction:column;
    align-items:center;
    gap:6px;
}

/* ================= ICON BASE ================= */
.manual-nav .icon{
    width:48px;
    height:48px;
    border-radius:14px;

    background:#020617;
    color:#e5e7eb;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:21px;

    border:1px solid rgba(255,255,255,.12);

    box-shadow:
        0 6px 14px rgba(0,0,0,.45),
        inset 0 1px 0 rgba(255,255,255,.06);

    transition:all .25s ease;
}

/* ================= ACTIVE SIDE BUTTON ================= */
.manual-nav .nav-btn.active{
    color:#22c55e;
}

.manual-nav .nav-btn.active .icon{
    background:rgba(34,197,94,.2);
    color:#22c55e;

    box-shadow:
        0 0 14px rgba(34,197,94,.5),
        inset 0 0 0 1px rgba(34,197,94,.4);
}

/* ================= CENTER BUY BUTTON ================= */
.manual-nav .nav-btn.center{
    transform:translateY(-6px); /* 🔥 controlled lift */
}

/* BUY ICON */
.manual-nav .nav-btn.center .icon{
    width:70px;
    height:70px;
    font-size:30px;
    border-radius:20px;

    background:linear-gradient(180deg,#fde68a,#f59e0b);
    color:#422006;

    box-shadow:
        0 12px 28px rgba(245,158,11,.65),
        inset 0 2px 0 rgba(255,255,255,.4);

    border:none;
}

/* BUY TEXT */
.manual-nav .nav-btn.center span{
    color:#facc15;
    font-weight:900;
}

/* ================= HARD STOP RANDOM GREEN ================= */
/* PREVENT RANDOM GREEN — BUT EXCLUDE BUY */
.manual-nav .nav-btn:not(.active):not(.center) .icon{
    background:#020617;
    color:#e5e7eb;
    box-shadow:
        0 6px 14px rgba(0,0,0,.45);
}

/* ================= PAGE SAFE SPACE ================= */
.wrap{
    padding-bottom:140px;
}
</style>
</head>

<body>

<div class="wrapper">
<div class="card">

<h2>💰 Earn Coins</h2>

<?php if ($buttons): ?>
    <?php foreach ($buttons as $b): ?>
        <a class="earn-btn"
           href="<?= htmlspecialchars($b['link']) ?>"
           target="_blank">
           <?= htmlspecialchars($b['title']) ?>
        </a>
    <?php endforeach; ?>
<?php else: ?>
    <div class="empty">No earning tasks available right now</div>
<?php endif; ?>


</div>
</div>

<!-- ===== MANUAL BOTTOM NAV ===== -->
<div class="manual-nav">

    <a class="nav-btn" href="dashboard.php">
        <div class="icon">🏠</div>
        <span>Home</span>
    </a>

    <!-- ✅ EARN ACTIVE -->
    <a class="nav-btn active" href="earn.php">
        <div class="icon">💰</div>
        <span>Earn</span>
    </a>

    <a class="nav-btn center" href="purchase.php">
        <div class="icon">🛒</div>
        <span>Buy</span>
    </a>

    <a class="nav-btn" href="headtail.php">
        <div class="icon">🎲</div>
        <span>Head/Tail</span>
    </a>

    <a class="nav-btn" href="account.php">
        <div class="icon">👤</div>
        <span>Account</span>
    </a>

</div>

</body>
</html>
