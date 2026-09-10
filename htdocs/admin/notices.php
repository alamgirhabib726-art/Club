<?php
/**
 * UNMOOR CLUB - ADMIN NOTICE MANAGEMENT
 */

require_once __DIR__ . "/guard.php";

$error = '';
$success = '';

/* ADD NOTICE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        $error = "Notice cannot be empty.";
    } elseif (mb_strlen($message) > 2000) {
        $error = "Notice too long (max 2000 characters).";
    } else {
        $stmt = $db->prepare("INSERT INTO notices (message, created_at) VALUES (?, datetime('now'))");
        $stmt->execute([$message]);
        
        // Log action
        try {
            $db->prepare("INSERT INTO logs (user_id, action, created_at) VALUES (?, ?, datetime('now'))")
               ->execute([$admin['id'], 'Posted new notice: ' . mb_substr($message, 0, 40) . '...']);
        } catch (Throwable $t) {}

        header("Location: notices.php?msg=added");
        exit;
    }
}

/* DELETE NOTICE */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM notices WHERE id = ?")->execute([$id]);

    try {
        $db->prepare("INSERT INTO logs (user_id, action, created_at) VALUES (?, ?, datetime('now'))")
           ->execute([$admin['id'], 'Deleted notice #' . $id]);
    } catch (Throwable $t) {}

    header("Location: notices.php?msg=deleted");
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') $success = "Notice published successfully to member dashboards.";
    if ($_GET['msg'] === 'deleted') $success = "Notice deleted successfully.";
}

/* FETCH ALL NOTICES */
$notices = $db->query("
    SELECT id, message, created_at
    FROM notices
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Notice Board';
$activeNav = 'notices.php';
$pageSubtitle = 'Publish and manage official broadcast notices shown on member dashboards.';

require_once __DIR__ . "/layout_top.php";
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
        <span>✅</span>
        <div><?= htmlspecialchars($success) ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-danger">
        <span>❌</span>
        <div><?= htmlspecialchars($error) ?></div>
    </div>
<?php endif; ?>

<!-- POST NEW NOTICE -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📢 Post New Broadcast Notice</h2>
    </div>
    <form method="post">
        <div class="admin-form-group">
            <label class="admin-label" for="noticeMessage">Notice Content (Supports Multi-line / Bengali & English)</label>
            <textarea id="noticeMessage" name="message" class="admin-textarea" placeholder="Write official announcement or notice here..." rows="4" required></textarea>
        </div>
        <button type="submit" class="admin-btn admin-btn-primary">
            🚀 Publish Notice
        </button>
    </form>
</div>

<!-- EXISTING NOTICES -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📜 Active Notices (<?= count($notices) ?>)</h2>
    </div>

    <?php if (empty($notices)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 24px 0;">
            No notices published yet.
        </p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 14px;">
            <?php foreach ($notices as $n): ?>
                <div style="background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-md); padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <span class="admin-badge admin-badge-info">Notice #<?= $n['id'] ?></span>
                        <span style="font-size: 12px; color: var(--admin-text-muted);">
                            🕒 <?= date("d M Y • h:i A", strtotime($n['created_at'])) ?>
                        </span>
                    </div>
                    <div style="white-space: pre-wrap; line-height: 1.6; font-size: 14px; color: var(--admin-text); margin-bottom: 12px;">
                        <?= htmlspecialchars($n['message']) ?>
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <a href="notices.php?delete=<?= (int)$n['id'] ?>" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Are you sure you want to delete this notice?')">
                            🗑️ Delete Notice
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
