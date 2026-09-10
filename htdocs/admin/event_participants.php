<?php
/**
 * UNMOOR CLUB - ADMIN EVENT PARTICIPATION DIRECTORY
 */

require_once __DIR__ . "/guard.php";

/* FETCH PARTICIPANTS */
$stmt = $db->prepare("
    SELECT 
        e.title as event_title,
        e.coin_cost,
        u.name as user_name,
        u.phone as user_phone,
        ep.joined_at
    FROM event_participants ep
    JOIN events e ON e.id = ep.event_id
    JOIN users u ON u.id = ep.user_id
    ORDER BY ep.joined_at DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Event Participants';
$activeNav = 'event_participants.php';
$pageSubtitle = 'Complete directory of members registered for active and past events.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">🎉 Registered Participants (<?= count($rows) ?>)</h2>
        <a href="events.php" class="admin-btn admin-btn-secondary admin-btn-sm">
            ← Manage Events
        </a>
    </div>

    <?php if (empty($rows)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No participants have joined any events yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Entry Fee</th>
                        <th>Member Name</th>
                        <th>Phone Number</th>
                        <th>Joined At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['event_title']) ?></strong></td>
                            <td><strong style="color: var(--admin-gold);">🪙 <?= number_format($r['coin_cost'], 2) ?></strong></td>
                            <td><strong><?= htmlspecialchars($r['user_name']) ?></strong></td>
                            <td><span style="color: var(--admin-text-dim);"><?= htmlspecialchars($r['user_phone']) ?></span></td>
                            <td><span style="font-size: 12px; color: var(--admin-text-muted);"><?= date("d M Y • h:i A", strtotime($r['joined_at'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
