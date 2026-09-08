<?php
session_start();
require_once __DIR__ . "/db.php";


/* LOGIN CHECK */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* FETCH USER */
$stmt = $db->prepare("
    SELECT id, name, phone, password
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    die("ACCESS DENIED");
}

$msg = $err = "";

/* CHANGE PHONE */
if (isset($_POST['change_phone'])) {
    $newPhone = trim($_POST['new_phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^\d{10,11}$/', $newPhone)) {
        $err = "Invalid phone number";
    } elseif (!password_verify($password, $user['password'])) {
        $err = "Wrong account password";
    } else {
        $db->prepare("UPDATE users SET phone=? WHERE id=?")
           ->execute([$newPhone, $user['id']]);
        $msg = "✅ Phone number updated";
        $user['phone'] = $newPhone;
    }
}

/* CHANGE PASSWORD */
if (isset($_POST['change_pass'])) {
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 6) {
        $err = "Password must be at least 6 characters";
    } elseif ($newPass !== $confirm) {
        $err = "Passwords do not match";
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")
           ->execute([$hash, $user['id']]);
        $msg = "✅ Password changed successfully";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}

/* PAGE WRAP */
.wrap{
    max-width:420px;
    margin:auto;
    padding:16px;
}

/* CARD */
.card{
    background:linear-gradient(135deg,#0f172a,#020617);
    border:1px solid #1f2937;
    border-radius:22px;
    padding:18px;
    margin-bottom:16px;
}

/* FORM CARD */
.card.form-card{
    padding:14px;
}

/* TITLES */
h3{
    margin:0 0 14px;
    font-size:15px;
    font-weight:900;
}

/* INFO BOX */
.info{
    background:#020617;
    padding:14px;
    border-radius:14px;
    border:1px solid #1f2937;
    margin-bottom:10px;
    font-size:14px;
}

/* INPUTS — MATCH INFO BOX SIZE */
.form-card input{
    width:100%;
    height:42px;              /* 🔥 CONTROL HEIGHT HERE */
    padding:8px 14px;         /* 🔥 CONTROL INNER SPACE */
    margin-bottom:10px;

    background:#020617;
    border:1px solid #1f2937;
    border-radius:14px;

    color:#e5e7eb;
    font-size:14px;
    outline:none;
}

.form-card input::placeholder{
    color:#6b7280;
}

/* BUTTONS */
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:14px;
    font-weight:900;
    cursor:pointer;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    font-size:15px;
}

/* LOGOUT */
.logout-btn{
    display:block;
    text-align:center;
    padding:14px;
    border-radius:14px;
    font-weight:900;
    text-decoration:none;
    background:linear-gradient(135deg,#ef4444,#dc2626);
    color:#fff;
}

/* MESSAGES */
.msg{
    background:rgba(34,197,94,.15);
    color:#22c55e;
    padding:12px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:12px;
}
.err{
    background:rgba(239,68,68,.15);
    color:#ef4444;
    padding:12px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:12px;
}

/* SPACE FOR FIXED NAV */
.wrap > .card:last-child{
    margin-bottom:90px;
}

/* SUPPORT FLOAT (FIXED & BIGGER) */
.support-wrap{
    position:fixed;
    right:18px;
    bottom:120px; /* 🔥 moved a little upper */
    z-index:1000;
}

#supportToggle{display:none;}

.support-menu{
    display:none;
    flex-direction:column;
    gap:10px;
    margin-bottom:12px;
}

#supportToggle:checked + .support-menu{
    display:flex;
}

.support-menu a{
    background:#020617;
    border:1px solid #1f2937;
    color:#e5e7eb;
    padding:12px 16px;
    border-radius:999px;
    text-decoration:none;
    font-weight:800;
}

/* BIGGER SUPPORT BUTTON */
.support-main{
    width:64px;          /* 🔥 bigger */
    height:64px;         /* 🔥 bigger */
    border-radius:50%;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:26px;      /* 🔥 bigger icon */
    color:#fff;
    box-shadow:0 14px 36px rgba(37,99,235,.55);
}

/* =========================
   MANUAL BOTTOM NAV (HEAVY)
========================= */

.manual-nav{
    position:fixed;
    bottom:0;
    left:0;
    right:0;

    height:96px;
    background:linear-gradient(
        180deg,
        rgba(15,23,42,.88),
        rgba(2,6,23,.95)
    );

    backdrop-filter:blur(14px);
    border-top:1px solid rgba(255,255,255,.08);

    display:flex;
    justify-content:space-around;
    align-items:flex-end;

    padding-bottom:12px;
    z-index:999;
}

/* BUTTON */
.manual-nav .nav-btn{
    flex:1;
    text-decoration:none;
    color:#e5e7eb;
    font-size:13px;
    font-weight:900;

    display:flex;
    flex-direction:column;
    align-items:center;
    gap:8px;
}

/* ICON (HEAVY) */
.manual-nav .icon{
    width:52px;
    height:52px;
    border-radius:16px;

    background:#020617;
    color:#e5e7eb;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:22px;

    border:1px solid rgba(255,255,255,.14);

    box-shadow:
        0 10px 22px rgba(0,0,0,.6),
        inset 0 1px 0 rgba(255,255,255,.08);
}

/* ACTIVE SIDE BUTTON */
.manual-nav .nav-btn.active{
    color:#22c55e;
}
.manual-nav .nav-btn.active .icon{
    background:rgba(34,197,94,.22);
    color:#22c55e;
    box-shadow:
        0 0 18px rgba(34,197,94,.6),
        inset 0 1px 0 rgba(255,255,255,.25);
}

/* CENTER BUY */
.manual-nav .nav-btn.center{
    transform:translateY(-6px);
}

.manual-nav .nav-btn.center .icon{
    width:78px;
    height:78px;
    font-size:34px;
    border-radius:22px;

    background:linear-gradient(180deg,#fde68a,#f59e0b);
    color:#422006;

    box-shadow:
        0 16px 36px rgba(245,158,11,.75),
        inset 0 2px 0 rgba(255,255,255,.5);
}

/* BUY TEXT */
.manual-nav .nav-btn.center span{
    color:#facc15;
    font-weight:900;
}

/* SPACE FOR NAV */
.wrap{
    padding-bottom:160px;
}
</style>
</head>

<body>
<div class="wrap">

<?php if($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="err"><?= $err ?></div><?php endif; ?>

<div class="card">
    <h3>👤 Personal Information</h3>
    <div class="info"><?= htmlspecialchars($user['name']) ?></div>
    <div class="info"><?= htmlspecialchars($user['phone']) ?></div>
</div>

<div class="card">
    <h3>🆔 Serial ID</h3>
    <div class="info">User ID: <?= $user['id'] ?></div>
</div>

<div class="card form-card">
    <h3>📱 Change Phone Number</h3>
    <form method="post">
        <input type="text" name="new_phone" placeholder="New phone number" required>
        <input type="password" name="password" placeholder="Account password" required>
        <button name="change_phone">Update Phone</button>
    </form>
</div>

<div class="card form-card">
    <h3>🔑 Change Password</h3>
    <form method="post">
        <input type="password" name="new_password" placeholder="New password" required>
        <input type="password" name="confirm_password" placeholder="Confirm password" required>
        <button name="change_pass">Change Password</button>
    </form>
</div>

<div class="card">
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
</div>

</div>

<!-- SUPPORT -->
<div class="support-wrap">
    <input type="checkbox" id="supportToggle">
    <div class="support-menu">
        <a href="https://wa.me/8801788674353" target="_blank">📲 WhatsApp</a>
        <a href="https://t.me/Angkur_TouFik" target="_blank">✈️ Telegram</a>
    </div>
    <label for="supportToggle" class="support-main">💬</label>
</div>
<!-- ===== MANUAL BOTTOM NAV ===== -->
<div class="manual-nav">

    <a class="nav-btn" href="dashboard.php">
        <div class="icon">🏠</div>
        <span>Home</span>
    </a>

    <a class="nav-btn" href="earn.php">
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

    <!-- ACCOUNT ACTIVE -->
    <a class="nav-btn active" href="account.php">
        <div class="icon">👤</div>
        <span>Account</span>
    </a>

</div>
</body>
</html>
