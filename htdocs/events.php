<?php
/**
 * UNMOOR CLUB - EVENTS
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

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

if (!$user || ($user['status'] !== 'active' && $user['status'] !== 'premium') || $user['apply_status'] !== 'approved' || $user['role'] === 'system') {
    die("ACCESS DENIED");
}

$msg = $error = '';

/* ================= PARTICIPATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $eventId = (int)($_POST['event_id'] ?? 0);

    $stmt = $db->prepare("
        SELECT id, title, coin_cost
        FROM events
        WHERE id=? AND status='active'
        LIMIT 1
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $error = "Event not available";
    } elseif ((float)$user['coins'] < (float)$event['coin_cost']) {
        $error = "Not enough coins to join this event";
    } else {

        $chk = $db->prepare("
            SELECT 1 FROM event_participants
            WHERE user_id=? AND event_id=?
            LIMIT 1
        ");
        $chk->execute([$uid, $eventId]);

        if ($chk->fetchColumn()) {
            $error = "You have already joined this event";
        } else {

            try {
                $db->beginTransaction();

                $db->prepare("
                    INSERT INTO event_participants (user_id, event_id, created_at)
                    VALUES (?, ?, NOW())
                ")->execute([$uid, $eventId]);

                $db->prepare("
                    UPDATE users SET coins = coins - ? WHERE id = ?
                ")->execute([$event['coin_cost'], $uid]);

                $systemId = (int)$db->query("
                    SELECT id FROM users WHERE role='system' LIMIT 1
                ")->fetchColumn();

                if ($systemId) {
                    $db->prepare("
                        UPDATE users SET coins = coins + ?
                        WHERE id = ?
                    ")->execute([$event['coin_cost'], $systemId]);

                    $db->prepare("
                        INSERT INTO coin_history
                            (user_id, amount, type,
                             source_user_id, source_name, source_number, created_at)
                        VALUES
                            (?, ?, 'event_in', ?, ?, ?, NOW())
                    ")->execute([
                        $systemId,
                        $event['coin_cost'],
                        $user['id'],
                        $user['name'],
                        $user['phone']
                    ]);
                }

                $db->prepare("
                    INSERT INTO coin_history
                        (user_id, amount, type,
                         source_user_id, source_name, source_number, created_at)
                    VALUES
                        (?, ?, 'event_out', ?, ?, ?, NOW())
                ")->execute([
                    $uid,
                    -$event['coin_cost'],
                    $systemId ?: $uid,
                    'Unmoor Club Event',
                    'SYSTEM'
                ]);

                $db->commit();

                $user['coins'] -= $event['coin_cost'];
                $msg = "🎉 Successfully registered for \"" . htmlspecialchars($event['title']) . "\"!";

            } catch (Exception $e) {
                $db->rollBack();
                $error = "Something went wrong. Please try again.";
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

/* CHECK PARTICIPATION */
$myEvents = [];
try {
    $stmt = $db->prepare("SELECT event_id FROM event_participants WHERE user_id = ?");
    $stmt->execute([$uid]);
    $myEvents = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $t) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Club Events • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Club Events", "/dashboard.php") ?>

        <!-- CURRENT BALANCE -->
        <div class="card" style="text-align: center; padding: 18px 14px; margin-bottom: 14px;">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 800;">Available Balance</div>
            <div style="font-size: 26px; font-weight: 900; color: <?= $user['coins'] < 0 ? 'var(--accent-red)' : 'var(--accent-green)' ?>; margin-top: 4px;">
                🪙 <?= number_format($user['coins'], 2) ?>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success" style="margin-bottom: 14px;">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 14px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($events): ?>
            <?php foreach ($events as $e): ?>
                <?php $hasJoined = in_array($e['id'], $myEvents); ?>
                <div class="card" style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <h3 style="font-size: 16px; font-weight: 900; color: #ffffff; margin: 0;">
                            🎉 <?= htmlspecialchars($e['title']) ?>
                        </h3>
                        <div style="background: rgba(234, 179, 8, 0.15); color: var(--accent-gold); padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 900; white-space: nowrap;">
                            🪙 <?= number_format($e['coin_cost'], 2) ?>
                        </div>
                    </div>

                    <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5; margin-bottom: 14px;">
                        <?= nl2br(htmlspecialchars($e['description'])) ?>
                    </p>

                    <?php if ($hasJoined): ?>
                        <button class="btn btn-secondary btn-block" disabled style="padding: 12px; opacity: 0.7;">
                            ✓ Already Registered
                        </button>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
                            <button type="submit" class="btn btn-gold btn-block" style="padding: 12px;">
                                Join Event (🪙 <?= number_format($e['coin_cost'], 2) ?>)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card" style="text-align: center; color: var(--text-muted); padding: 36px 0;">
                <div style="font-size: 36px; margin-bottom: 8px;">🎊</div>
                No active events at this time. Stay tuned!
            </div>
        <?php endif; ?>

        <?= render_support_widget() ?>

    </div>

    <?php require_once __DIR__ . "/bottom_nav.php"; ?>
</body>
</html>
