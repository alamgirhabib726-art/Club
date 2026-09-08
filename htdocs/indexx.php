<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = (int)$_SESSION['user_id'];

$db->prepare("UPDATE users SET last_seen=NOW() WHERE id=?")->execute([$uid]);

$chatUserId = isset($_GET['u']) ? (int)$_GET['u'] : 0;

/* SEND MESSAGE */
if ($_SERVER['REQUEST_METHOD']==='POST' && $chatUserId) {
    $msg = $_POST['msg'] ?? '';
    if ($msg !== '') {
        $db->prepare("
            INSERT INTO chat_messages (sender_id,receiver_id,message,seen)
            VALUES (?,?,?,0)
        ")->execute([$uid,$chatUserId,$msg]);
    }
    header("Location: indexx.php?u=".$chatUserId."#bottom");
    exit;
}

/* CHAT USER */
$chatUser = null;
if ($chatUserId) {
    $stmt = $db->prepare("SELECT id,name,photo FROM users WHERE id=?");
    $stmt->execute([$chatUserId]);
    $chatUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $db->prepare("
        UPDATE chat_messages SET seen=1
        WHERE sender_id=? AND receiver_id=?
    ")->execute([$chatUserId,$uid]);
}

/* USERS */
$users = $db->query("
SELECT u.*,
(
 SELECT COUNT(*) FROM chat_messages
 WHERE sender_id=u.id AND receiver_id=$uid AND seen=0
) unread
FROM users u
WHERE u.id!=$uid
AND u.role NOT IN ('SYSTEM','LIQUIDITY')
ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* MESSAGES */
$messages=[];
if($chatUserId){
    $stmt=$db->prepare("
        SELECT sender_id,message,seen
        FROM chat_messages
        WHERE (sender_id=? AND receiver_id=?)
           OR (sender_id=? AND receiver_id=?)
        ORDER BY id ASC
    ");
    $stmt->execute([$uid,$chatUserId,$chatUserId,$uid]);
    $messages=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chat</title>

<style>
*{
    box-sizing:border-box;
    font-family:
        system-ui,
        "Noto Sans Bengali",
        "Segoe UI Emoji",
        "Apple Color Emoji",
        sans-serif;
}

body{
    margin:0;
    background:#0b141a;
    color:#e9edef;
    height:100vh;
}

.app{height:100vh}

/* SIDEBAR */
.sidebar{
    background:#111b21;
    overflow-y:auto;
}
.side-header{
    height:56px;
    padding:0 16px;
    display:flex;
    align-items:center;
    background:#202c33;
    font-weight:600;
}
.user{
    margin:6px;
    padding:10px;
    border-radius:10px;
    background:#1f2c33;
    display:flex;
    gap:10px;
    align-items:center;
    text-decoration:none;
    color:#fff;
}
.user img,.avatar{
    width:40px;height:40px;border-radius:50%;
    background:#2a3942;
}
.badge{
    margin-left:auto;
    background:#00a884;
    color:#000;
    min-width:20px;
    height:20px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
}

/* CHAT */
.chat{display:none}
.chat.active{
    display:flex;
    flex-direction:column;
    position:fixed;
    inset:0;
    background:#0b141a;
}

/* HEADER */
.chat-header{
    height:56px;
    display:flex;
    align-items:center;
    padding:0 12px;
    gap:10px;
    background:#202c33;
}
.chat-header a{
    color:#00a884;
    font-size:22px;
    text-decoration:none;
}

/* MESSAGES */
.messages{
    flex:1;
    padding:12px;
    overflow-y:auto;
    background:url('uploads/bg/bgl.png') center/cover no-repeat;
}

/* WHATSAPP-LIKE BUBBLES */
.msg{
    display:inline-block;
    max-width:75%;
    padding:6px 10px;          /* SMALLER */
    border-radius:10px;        /* SMALLER */
    margin-bottom:6px;
    font-size:15px;
    line-height:1.4;
    word-wrap:break-word;
    white-space:pre-wrap;
}
.me{
    background:#005c4b;
    margin-left:auto;
    border-bottom-right-radius:4px;
}
.them{
    background:#202c33;
    border-bottom-left-radius:4px;
}
.meta{
    font-size:11px;
    opacity:.6;
    text-align:right;
}

/* INPUT */
.input{
    display:flex;
    gap:8px;
    padding:8px;
    background:#202c33;
}
.input input{
    flex:1;
    height:38px;
    border:none;
    border-radius:19px;
    padding:0 14px;
    background:#2a3942;
    color:#fff;
    font-size:15px;
}
.input button{
    height:38px;
    border:none;
    border-radius:19px;
    padding:0 16px;
    background:#00a884;
    font-weight:600;
}

@media(min-width:900px){
    .app{display:flex}
    .sidebar{width:360px}
    .chat{display:flex;position:relative}
}
</style>
</head>

<body>
<div class="app">

<!-- SIDEBAR -->
<div class="sidebar">
<div class="side-header">Chats</div>
<?php foreach($users as $u): ?>
<a class="user" href="indexx.php?u=<?= $u['id'] ?>">
<?php if($u['photo']): ?>
<img src="../uploads/avatars/<?= htmlspecialchars($u['photo']) ?>">
<?php else: ?><div class="avatar"></div><?php endif; ?>
<?= htmlspecialchars($u['name']) ?>
<?php if($u['unread']>0): ?>
<span class="badge"><?= $u['unread'] ?></span>
<?php endif; ?>
</a>
<?php endforeach; ?>
</div>

<!-- CHAT -->
<?php if($chatUser): ?>
<div class="chat active">

<div class="chat-header">
<a href="indexx.php">✕</a>
<?php if($chatUser['photo']): ?>
<img src="../uploads/avatars/<?= htmlspecialchars($chatUser['photo']) ?>" width="28" height="28" style="border-radius:50%">
<?php endif; ?>
<?= htmlspecialchars($chatUser['name']) ?>
</div>

<div class="messages">
<?php foreach($messages as $m): ?>
<div class="msg <?= $m['sender_id']==$uid?'me':'them' ?>">
<?= htmlspecialchars($m['message']) ?>
<div class="meta">
<?= $m['sender_id']==$uid ? ($m['seen']?'✓✓':'✓') : '' ?>
</div>
</div>
<?php endforeach; ?>
<div id="bottom"></div>
</div>

<form method="post" class="input">
<input name="msg" placeholder="Message…" autocomplete="off">
<button>Send</button>
</form>

</div>
<?php endif; ?>

</div>
</body>
</html>