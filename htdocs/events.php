<?php
session_start();
require_once "db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, coins, status, apply_status, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$user ||
    $user['status'] !== 'active' ||
    $user['apply_status'] !== 'approved' ||
    $user['role'] === 'system'
) {
    die("ACCESS DENIED");
}

$msg = $error = '';

/* ================= PARTICIPATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $eventId = (int)($_POST['event_id'] ?? 0);

    $stmt = $db->prepare("
        SELECT id, coin_cost
        FROM events
        WHERE id=? AND status='active'
        LIMIT 1
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $error = "Event not available";
    } elseif ($user['coins'] < $event['coin_cost']) {
        $error = "Not enough coins";
    } else {

        $chk = $db->prepare("
            SELECT 1 FROM event_participants
            WHERE user_id=? AND event_id=?
            LIMIT 1
        ");
        $chk->execute([$uid, $eventId]);

        if ($chk->fetchColumn()) {
            $error = "You already joined this event";
        } else {

            try {
                $db->beginTransaction();

                /* JOIN EVENT */
                $db->prepare("
                    INSERT INTO event_participants (user_id, event_id)
                    VALUES (?, ?)
                ")->execute([$uid, $eventId]);

                /* CUT USER */
                $db->prepare("
                    UPDATE users SET coins = coins - ? WHERE id = ?
                ")->execute([$event['coin_cost'], $uid]);

                /* SYSTEM ID */
                $systemId = (int)$db->query("
                    SELECT id FROM users WHERE role='system' LIMIT 1
                ")->fetchColumn();

                /* ADD TO SYSTEM */
                $db->prepare("
                    UPDATE users SET coins = coins + ?
                    WHERE id = ?
                ")->execute([$event['coin_cost'], $systemId]);

                /* USER HISTORY (EVENT PAYMENT) */
                $db->prepare("
                    INSERT INTO coin_history
                        (user_id, amount, type,
                         source_user_id, source_name, source_number)
                    VALUES
                        (?, ?, 'event_out', ?, ?, ?)
                ")->execute([
                    $uid,
                    -$event['coin_cost'],
                    $systemId,
                    'Unmoor Club',
                    'SYSTEM'
                ]);

                /* SYSTEM HISTORY (FROM USER) */
                $db->prepare("
                    INSERT INTO coin_history
                        (user_id, amount, type,
                         source_user_id, source_name, source_number)
                    VALUES
                        (?, ?, 'event_in', ?, ?, ?)
                ")->execute([
                    $systemId,
                    $event['coin_cost'],
                    $user['id'],
                    $user['name'],
                    $user['phone']
                ]);

                $db->commit();

                $user['coins'] -= $event['coin_cost'];
                $msg = "🎉 Joined event successfully";

            } catch (Exception $e) {
                $db->rollBack();
                $error = "Something went wrong";
            }
        }
    }
}

/* ================= FETCH EVENTS ================= */
$events = $db->query("
    SELECT id, title, description, coin_cost
    FROM events
    WHERE status='active'
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Events • Unmoor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#0b0f19;
    --card:#121826;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --gold:#facc15;
    --green:#22c55e;
    --red:#ef4444;
}

*{box-sizing:border-box;font-family:system-ui}

body{
    margin:0;
    background:var(--bg);
    color:var(--text);
}

.wrap{
    max-width:520px;
    margin:auto;
    padding:18px;
}

/* HEADER */
.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:18px;
}
.header h2{
    margin:0;
    font-size:22px;
    font-weight:900;
}
.coins{
    background:rgba(250,204,21,.15);
    color:var(--gold);
    padding:6px 14px;
    border-radius:999px;
    font-weight:900;
    font-size:13px;
}

/* MESSAGE */
.msg{
    background:rgba(34,197,94,.15);
    color:var(--green);
    padding:12px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:14px;
}
.err{
    background:rgba(239,68,68,.15);
    color:var(--red);
    padding:12px;
    border-radius:14px;
    font-weight:800;
    margin-bottom:14px;
}

/* EVENT CARD */
.card{
    background:linear-gradient(135deg,#0f172a,#020617);
    border:1px solid var(--border);
    border-radius:22px;
    padding:18px;
    margin-bottom:16px;
    box-shadow:0 20px 40px rgba(0,0,0,.45);
}

.card h3{
    margin:0 0 6px;
    font-size:16px;
    font-weight:900;
}

.card p{
    margin:0 0 12px;
    font-size:13px;
    color:var(--muted);
    line-height:1.5;
}

.cost{
    font-weight:900;
    margin-bottom:14px;
}

/* BUTTON */
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:16px;
    background:linear-gradient(135deg,#fde68a,#facc15);
    color:#422006;
    font-weight:900;
    font-size:15px;
    cursor:pointer;
}

/* EMPTY */
.empty{
    text-align:center;
    color:var(--muted);
    padding:28px;
}

/* BACK */
.back{
    display:block;
    margin-top:20px;
    text-align:center;
    color:var(--muted);
    text-decoration:none;
    font-weight:700;
}
</style>
</head>

<body>
<div class="wrap">

<div class="header">
    <h2>🎉 Events</h2>
    <div class="coins">🪙 <?= number_format($user['coins'],2) ?></div>
</div>

<?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if($events): foreach($events as $e): ?>
<div class="card">
    <h3><?= htmlspecialchars($e['title']) ?></h3>
    <p><?= htmlspecialchars($e['description']) ?></p>

    <div class="cost">🪙 <?= number_format($e['coin_cost'],2) ?> coins</div>

    <form method="post">
        <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
        <button>Participate</button>
    </form>
</div>
<?php endforeach; else: ?>
<div class="card empty">😔 No events available</div>
<?php endif; ?>

<a class="back" href="dashboard.php">← Back to Dashboard</a>

</div>
</body>
</html>
