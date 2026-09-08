<?php
session_start();
require_once "../db.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/*
TABLE REQUIRED (example):
notifications
- id
- user_id
- title
- message
- type (system, deposit, game, alert)
- is_read (0/1)
- created_at
*/

/* FETCH NOTIFICATIONS */
$stmt = $db->prepare("
    SELECT title, message, type, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 50
");
$stmt->execute([$uid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Notifications</title>
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

/* LIST */
.list{padding:8px 0}

/* ITEM */
.item{
    display:flex;
    gap:14px;
    padding:14px 16px;
    border-bottom:1px solid #202c33;
}
.item.unread{
    background:#111b21;
}

/* ICON */
.icon{
    width:42px;
    height:42px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
    background:#202c33;
}

/* ICON COLORS */
.system{background:#00a884;color:#002e24}
.deposit{background:#2563eb}
.game{background:#7c3aed}
.alert{background:#dc2626}

/* TEXT */
.text{flex:1}
.title{
    font-size:15px;
    font-weight:600;
}
.msg{
    font-size:13px;
    color:#8696a0;
    margin-top:2px;
}
.time{
    font-size:11px;
    color:#6b7280;
    margin-top:4px;
}
.empty{
    text-align:center;
    color:#8696a0;
    padding:40px 0;
}
</style>
</head>

<body>
<div class="container">

    <!-- HEADER -->
    <div class="header">
        <a href="account.php">←</a>
        <h1>Notifications</h1>
    </div>

    <!-- LIST -->
    <div class="list">

    <?php if($rows): foreach($rows as $n): ?>
        <div class="item <?= $n['is_read'] ? '' : 'unread' ?>">
            <div class="icon <?= htmlspecialchars($n['type']) ?>">
                <?php
                    echo match($n['type']){
                        'deposit' => '💰',
                        'game'    => '🎲',
                        'alert'   => '⚠️',
                        default   => '👋'
                    };
                ?>
            </div>

            <div class="text">
                <div class="title"><?=htmlspecialchars($n['title'])?></div>
                <div class="msg"><?=htmlspecialchars($n['message'])?></div>
                <div class="time">
                    <?=date("d M Y, h:i A", strtotime($n['created_at']))?>
                </div>
            </div>
        </div>
    <?php endforeach; else: ?>
        <div class="empty">No notifications yet</div>
    <?php endif; ?>

    </div>

</div>
</body>
</html>