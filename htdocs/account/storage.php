<?php
session_start();
require_once "../db.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$msg = '';

/*
TABLES USED (example):
- messages (chat history)
- login_history
- coin_history
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['action'] === 'clear_chats') {
        $db->prepare("DELETE FROM messages WHERE user_id = ?")->execute([$uid]);
        $msg = "Chat history cleared";
    }

    if ($_POST['action'] === 'clear_logins') {
        $db->prepare("DELETE FROM login_history WHERE user_id = ?")->execute([$uid]);
        $msg = "Login history cleared";
    }

    if ($_POST['action'] === 'clear_ledger') {
        $db->prepare("DELETE FROM coin_history WHERE user_id = ?")->execute([$uid]);
        $msg = "Transaction history cleared";
    }

    if ($_POST['action'] === 'logout') {
        session_destroy();
        header("Location: ../login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Storage and Data</title>
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

/* MESSAGE */
.msg{
    background:#112c24;
    color:#00a884;
    padding:10px 16px;
    font-size:14px;
}

/* LIST */
.list{margin-top:10px}

/* ITEM */
.item{
    padding:14px 16px;
    border-bottom:1px solid #202c33;
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.left{
    display:flex;
    gap:14px;
    align-items:center;
}
.icon{
    width:38px;
    height:38px;
    border-radius:50%;
    background:#202c33;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
}
.text .title{font-size:15px;font-weight:500}
.text .sub{font-size:13px;color:#8696a0;margin-top:2px}

/* BUTTON */
button{
    background:none;
    border:none;
    color:#00a884;
    font-size:14px;
    cursor:pointer;
}

/* DANGER */
.danger{color:#ef4444}
</style>
</head>

<body>
<div class="container">

    <!-- HEADER -->
    <div class="header">
        <a href="account.php">←</a>
        <h1>Storage and data</h1>
    </div>

    <?php if($msg): ?>
        <div class="msg"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>

    <!-- LIST -->
    <div class="list">

        <!-- CHAT -->
        <form method="post" class="item">
            <div class="left">
                <div class="icon">💬</div>
                <div class="text">
                    <div class="title">Clear chat history</div>
                    <div class="sub">Remove all messages</div>
                </div>
            </div>
            <button name="action" value="clear_chats"
                onclick="return confirm('Clear all chats?')">
                Clear
            </button>
        </form>

        <!-- LOGIN -->
        <form method="post" class="item">
            <div class="left">
                <div class="icon">🖥️</div>
                <div class="text">
                    <div class="title">Clear login history</div>
                    <div class="sub">Devices and IP records</div>
                </div>
            </div>
            <button name="action" value="clear_logins"
                onclick="return confirm('Clear login history?')">
                Clear
            </button>
        </form>

        <!-- LEDGER -->
        <form method="post" class="item">
            <div class="left">
                <div class="icon">📒</div>
                <div class="text">
                    <div class="title">Clear transaction history</div>
                    <div class="sub">Deposits, games, transfers</div>
                </div>
            </div>
            <button name="action" value="clear_ledger"
                onclick="return confirm('Clear transaction history?')">
                Clear
            </button>
        </form>

        <!-- LOGOUT -->
        <form method="post" class="item">
            <div class="left">
                <div class="icon">🚪</div>
                <div class="text">
                    <div class="title danger">Log out</div>
                    <div class="sub">Sign out from this device</div>
                </div>
            </div>
            <button class="danger" name="action" value="logout">
                Log out
            </button>
        </form>

    </div>

</div>
</body>
</html>