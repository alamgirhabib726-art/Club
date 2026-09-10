<?php
/**
 * UNMOOR CLUB - ADMIN EVENTS MANAGEMENT
 */

require_once __DIR__ . "/guard.php";

$msg = $error = '';

/* ============ CREATE EVENT ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title = trim($_POST['title']);
    $desc  = trim($_POST['description']);
    $cost  = (float)$_POST['coin_cost'];

    if ($title === '' || $desc === '' || $cost < 0) {
        $error = "All fields are required and coin entry cost must be 0 or higher.";
    } else {
        $now = date('Y-m-d H:i:s');
        $db->prepare("
            INSERT INTO events (title, description, coin_cost, status, created_at)
            VALUES (?, ?, ?, 'active', ?)
        ")->execute([$title, $desc, $cost, $now]);

        log_admin_action($db, $admin['id'], "Created event: " . $title);

        $msg = "Event created successfully.";
    }
}

/* ============ DELETE EVENT ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $eventId = (int)$_POST['event_id'];

    $db->prepare("DELETE FROM events WHERE id=?")->execute([$eventId]);
    $db->prepare("DELETE FROM event_participants WHERE event_id=?")->execute([$eventId]);

    log_admin_action($db, $admin['id'], "Deleted event #$eventId");

    $msg = "Event deleted successfully.";
}

/* ============ FETCH EVENTS ============ */
$events = $db->query("
    SELECT e.id, e.title, e.description, e.coin_cost, e.status, e.created_at,
           (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) as participant_count
    FROM events e
    ORDER BY e.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Club Events';
$activeNav = 'events.php';
$pageSubtitle = 'Organize, launch, and manage club events and track entry participation.';

require_once __DIR__ . "/layout_top.php";
?>

<?php if ($msg): ?>
    <div class="admin-alert admin-alert-success">
        <span>✅</span>
        <div><?= htmlspecialchars($msg) ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-danger">
        <span>❌</span>
        <div><?= htmlspecialchars($error) ?></div>
    </div>
<?php endif; ?>

<!-- CREATE EVENT FORM -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">🎉 Create New Event</h2>
    </div>

    <form method="post">
        <input type="hidden" name="create_event" value="1">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px;">
            <div class="admin-form-group">
                <label class="admin-label">Event Title</label>
                <input type="text" name="title" class="admin-input" placeholder="e.g. Unmoor Winter Championship 2026" required>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Entry Cost (Coins)</label>
                <input type="number" step="0.01" name="coin_cost" class="admin-input" placeholder="0 for free, or coin amount" value="0" required>
            </div>
        </div>

        <div class="admin-form-group">
            <label class="admin-label">Event Description & Rules</label>
            <textarea name="description" class="admin-textarea" placeholder="Detailed event description, prize pool, date, and terms..." rows="3" required></textarea>
        </div>

        <button type="submit" class="admin-btn admin-btn-primary">
            🚀 Launch Event
        </button>
    </form>
</div>

<!-- EXISTING EVENTS -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📜 Existing Events (<?= count($events) ?>)</h2>
        <a href="event_participants.php" class="admin-btn admin-btn-secondary admin-btn-sm">
            👥 View Participants
        </a>
    </div>

    <?php if (empty($events)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 24px 0;">No events created yet.</p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
            <?php foreach ($events as $ev): ?>
                <div style="background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-md); padding: 18px; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <h3 style="font-size: 16px; font-weight: 800; color: #ffffff; margin: 0;"><?= htmlspecialchars($ev['title']) ?></h3>
                            <span class="admin-badge admin-badge-warning">🪙 <?= number_format($ev['coin_cost'], 2) ?> UC</span>
                        </div>
                        <p style="font-size: 13px; color: var(--admin-text-muted); margin-bottom: 12px; line-height: 1.5; white-space: pre-wrap;"><?= htmlspecialchars($ev['description']) ?></p>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--admin-border); padding-top: 12px; margin-top: 8px;">
                        <span style="font-size: 12px; color: var(--admin-text-dim);">
                            👥 <?= $ev['participant_count'] ?> participants
                        </span>
                        <form method="post" onsubmit="return confirm('Delete this event? All participant records for this event will also be removed.')">
                            <input type="hidden" name="delete_event" value="1">
                            <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                🗑️ Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
