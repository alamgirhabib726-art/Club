<?php
session_start();
require_once __DIR__ . "/../db.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH RECENT TRANSFER CONTACTS */
$stmt = $db->prepare("
    SELECT DISTINCT u.id, u.name, u.phone, u.photo
    FROM coin_history ch
    JOIN users u ON (ch.source_user_id = u.id OR ch.user_id = u.id)
    WHERE (ch.user_id = ? OR ch.source_user_id = ?)
      AND u.id != ?
    LIMIT 20
");
$stmt->execute([$uid, $uid, $uid]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Lists • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui}
body{background:#0b141a;color:#e9edef}

.container{max-width:480px;margin:auto;min-height:100vh}

/* HEADER */
.header{
    height:56px;
    display:flex;
    align-items:center;
    padding:0 16px;
}
.header a{
    color:#00a884;
    font-size:22px;
    text-decoration:none;
    margin-right:16px;
}
.header h1{font-size:18px;font-weight:600}

/* SECTION */
.section{
    padding:16px;
    border-top:1px solid #202c33;
}
.section h2{
    font-size:14px;
    color:#8696a0;
    margin-bottom:12px;
    text-transform:uppercase;
}

.item{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 0;
    border-bottom:1px solid #202c33;
}
.avatar{
    width:40px;
    height:40px;
    border-radius:50%;
    background:#202c33;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
    overflow:hidden;
}
.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}
.info h3{
    font-size:15px;
    font-weight:500;
}
.info p{
    font-size:13px;
    color:#8696a0;
}
.empty{
    padding:24px 0;
    text-align:center;
    color:#8696a0;
    font-size:14px;
}
</style>
</head>

<body>
<div class="container">

    <div class="header">
        <a href="account.php">←</a>
        <h1>Lists & Contacts</h1>
    </div>

    <div class="section">
        <h2>👥 Recent Transfer Contacts</h2>
        <?php if ($contacts): ?>
            <?php foreach ($contacts as $c): ?>
                <div class="item">
                    <div class="avatar">
                        <?php if (!empty($c['photo'])): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($c['photo']) ?>" alt="">
                        <?php else: ?>
                            👤
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <h3><?= htmlspecialchars($c['name']) ?></h3>
                        <p><?= htmlspecialchars($c['phone']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty">No recent contact history found.</div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🌐 Club Community Channels</h2>
        <div class="item">
            <div class="avatar">✈️</div>
            <div class="info">
                <h3>Official Telegram</h3>
                <p>Join announcements and trader groups</p>
            </div>
        </div>
        <div class="item">
            <div class="avatar">💬</div>
            <div class="info">
                <h3>Support Desk</h3>
                <p>24/7 Member assistance</p>
            </div>
        </div>
    </div>

</div>
</body>
</html>
