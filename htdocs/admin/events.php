<?php
session_start();
require_once "../db.php";

/* ============ ADMIN GUARD ============ */
if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.php");
    exit;
}

$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

$msg = $error = '';

/* ============ CREATE EVENT ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {

    $title = trim($_POST['title']);
    $desc  = trim($_POST['description']);
    $cost  = (float)$_POST['coin_cost'];

    if ($title === '' || $desc === '' || $cost < 0) {
        $error = "❌ All fields required";
    } else {
        $db->prepare("
            INSERT INTO events (title, description, coin_cost)
            VALUES (?, ?, ?)
        ")->execute([$title, $desc, $cost]);

        $msg = "✅ Event created";
    }
}

/* ============ DELETE EVENT ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {

    $eventId = (int)$_POST['event_id'];

    $db->prepare("DELETE FROM events WHERE id=?")->execute([$eventId]);
    $db->prepare("DELETE FROM event_participants WHERE event_id=?")->execute([$eventId]);

    $msg = "🗑 Event deleted";
}

/* ============ FETCH EVENTS ============ */
$events = $db->query("
    SELECT id, title, coin_cost, status, created_at
    FROM events
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Events • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrap{
    max-width:520px;
    margin:auto;
    padding:16px;
}
.card{
    background:#121826;
    border-radius:22px;
    padding:18px;
    margin-bottom:18px;
}
input,textarea{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:none;
    background:#020617;
    color:#fff;
    margin-bottom:12px;
}
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:16px;
    font-weight:900;
    cursor:pointer;
}
.create{background:#22c55e;color:#022c22}
.delete{background:#ef4444;color:#fff;margin-top:10px}
.badge{
    display:inline-block;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.active{background:#22c55e;color:#022c22}
.msg{color:#22c55e;font-weight:700}
.err{color:#ef4444;font-weight:700}
small{color:#9ca3af}
</style>
</head>
<body>

<div class="wrap">

<div class="card">
<h3>🎉 Create Event</h3>

<?php if($msg): ?><div class="msg"><?=$msg?></div><?php endif; ?>
<?php if($error): ?><div class="err"><?=$error?></div><?php endif; ?>

<form method="post">
    <input name="title" placeholder="Event title" required>
    <textarea name="description" placeholder="Event description" required></textarea>
    <input name="coin_cost" type="number" step="0.01" min="0" placeholder="Coin cost" required>
    <button class="create" name="create_event">Create Event</button>
</form>
</div>

<div class="card">
<h3>📋 Existing Events</h3>

<?php if($events): foreach($events as $e): ?>
    <div style="border-bottom:1px dashed #1f2937;padding:12px 0">
        <strong><?=htmlspecialchars($e['title'])?></strong><br>
        🪙 <?=number_format($e['coin_cost'],2)?> coins<br>
        <span class="badge active"><?=strtoupper($e['status'])?></span><br>
        <small><?=date("d M Y, h:i A", strtotime($e['created_at']))?></small>

        <form method="post" onsubmit="return confirm('Delete this event?')">
            <input type="hidden" name="event_id" value="<?=$e['id']?>">
            <button class="delete" name="delete_event">Delete Event</button>
        </form>
    </div>
<?php endforeach; else: ?>
    <p style="color:#9ca3af">No events yet</p>
<?php endif; ?>
</div>

</div>

</body>
</html>
