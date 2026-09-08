<?php
session_start();
require_once __DIR__ . "/../db.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH USER */
$stmt = $db->prepare("
    SELECT name, phone, photo
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$avatar = !empty($user['photo'])
    ? "../uploads/avatars/".$user['photo']
    : "assets/default-avatar.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Settings</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
/* ================= RESET ================= */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: system-ui, -apple-system, BlinkMacSystemFont;
}

/* ================= BASE ================= */
body{
    background:#0b141a;
    color:#e9edef;
}

.settings-container{
    max-width:480px;
    margin:0 auto;
    min-height:100vh;
}

/* ================= HEADER ================= */
.header{
    height:56px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 16px;
    background:#0b141a;
}

.header-left{
    display:flex;
    align-items:center;
    gap:16px;
}

.icon-back{
    font-size:22px;
    color:#00a884;
    text-decoration:none;
}

.header h1{
    font-size:18px;
    font-weight:600;
}

.logout-btn{
    color:#ef4444;
    font-size:14px;
    font-weight:600;
    text-decoration:none;
}

/* ================= PROFILE ================= */
.profile-section{
    display:flex;
    align-items:center;
    gap:16px;
    padding:16px;
    border-bottom:1px solid #202c33;
}

.avatar{
    width:64px;
    height:64px;
    border-radius:50%;
    overflow:hidden;
    background:#3b4a54;
}

.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.profile-info h2{
    font-size:16px;
    font-weight:700;
}

.phone-number{
    font-size:14px;
    color:#8696a0;
    margin-top:2px;
}

.mood-pill{
    margin-top:6px;
    font-size:13px;
    color:#00a884;
}

/* ================= LIST ================= */
.setting-item{
    display:flex;
    gap:16px;
    padding:14px 16px;
    text-decoration:none;
    color:#e9edef;
    align-items:center;
}

.setting-item:active{
    background:#111b21;
}

.item-icon{
    width:24px;
    text-align:center;
    font-size:20px;
    color:#8696a0;
}

.item-text h3{
    font-size:15px;
    font-weight:500;
}

.item-text p{
    font-size:13px;
    color:#8696a0;
    margin-top:2px;
}
</style>

</head>
<body>

<div class="settings-container">

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <a class="icon-back" href="../dashboard.php">←</a>
            <h1>Settings</h1>
        </div>

        <a class="logout-btn" href="../logout.php">Log out</a>
    </div>

    <!-- PROFILE -->
    <div class="profile-section">
        <div class="avatar">
            <img src="<?= htmlspecialchars($avatar) ?>">
        </div>

        <div class="profile-info">
            <h2><?= htmlspecialchars($user['name']) ?></h2>
            <div class="phone-number">+880 <?= htmlspecialchars($user['phone']) ?></div>
            <div class="mood-pill">🙂 Available</div>
        </div>
    </div>

    <!-- SETTINGS -->
    <a class="setting-item" href="account_settings.php">
        <div class="item-icon">🔑</div>
        <div class="item-text">
            <h3>Account</h3>
            <p>Change password, change number</p>
        </div>
    </a>

    <a class="setting-item" href="privacy.php">
        <div class="item-icon">🔒</div>
        <div class="item-text">
            <h3>Privacy</h3>
            <p>Last login, IP history</p>
        </div>
    </a>

    <a class="setting-item" href="avatar.php">
        <div class="item-icon">👤</div>
        <div class="item-text">
            <h3>Avatar</h3>
            <p>Create, edit profile photo</p>
        </div>
    </a>

    <a class="setting-item" href="lists.php">
        <div class="item-icon">📋</div>
        <div class="item-text">
            <h3>Lists</h3>
            <p>Manage people and groups</p>
        </div>
    </a>

    <a class="setting-item" href="notifications.php">
        <div class="item-icon">🔔</div>
        <div class="item-text">
            <h3>Notifications</h3>
            <p>Deposits, approvals, alerts</p>
        </div>
    </a>

    <a class="setting-item" href="storage.php">
        <div class="item-icon">💾</div>
        <div class="item-text">
            <h3>Storage and data</h3>
            <p>History, cleanup</p>
        </div>
    </a>

</div>

</body>
</html>