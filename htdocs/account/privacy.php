<?php
session_start();
require_once __DIR__ . "/../db.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH LOGIN HISTORY */
$stmt = $db->prepare("
    SELECT ip_address, user_agent, created_at
    FROM login_history
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 20
");
$stmt->execute([$uid]);
$logins = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* CURRENT SESSION INFO */
$currentIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Privacy</title>
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
.section h3{
    font-size:14px;
    color:#8696a0;
    margin-bottom:10px;
}

/* CARD */
.card{
    background:#111b21;
    border:1px solid #202c33;
    border-radius:12px;
    padding:12px;
    margin-bottom:10px;
}
.card .title{
    font-size:14px;
    font-weight:600;
}
.card .sub{
    font-size:12px;
    color:#8696a0;
    margin-top:4px;
}

/* LOGIN ROW */
.login-row{
    padding:12px 0;
    border-bottom:1px dashed #202c33;
}
.login-row:last-child{border-bottom:none}

.ip{font-size:14px;font-weight:600}
.time{font-size:12px;color:#8696a0;margin-top:2px}
.agent{font-size:11px;color:#6b7280;margin-top:2px}
</style>
</head>

<body>
<div class="container">

    <!-- HEADER -->
    <div class="header">
        <a href="account.php">←</a>
        <h1>Privacy</h1>
    </div>

    <!-- CURRENT SESSION -->
    <div class="section">
        <h3>Current session</h3>
        <div class="card">
            <div class="title"><?=htmlspecialchars($currentIp)?></div>
            <div class="sub"><?=htmlspecialchars($currentAgent)?></div>
        </div>
    </div>

    <!-- LOGIN HISTORY -->
    <div class="section">
        <h3>Login history</h3>

        <?php if($logins): foreach($logins as $l): ?>
            <div class="login-row">
                <div class="ip"><?=htmlspecialchars($l['ip_address'])?></div>
                <div class="time">
                    <?=date("d M Y, h:i A", strtotime($l['created_at']))?>
                </div>
                <div class="agent"><?=htmlspecialchars($l['user_agent'])?></div>
            </div>
        <?php endforeach; else: ?>
            <div class="time">No login history available</div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>