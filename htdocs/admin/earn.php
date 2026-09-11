<?php
/**
 * UNMOOR CLUB - ADMIN EARN BUTTONS MANAGEMENT
 */

require_once __DIR__ . "/guard.php";

$msg = '';
$err = '';

/* ================= ADD BUTTON ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $title = trim($_POST['title'] ?? '');
    $link  = trim($_POST['link'] ?? '');

    if ($title === '' || $link === '') {
        $err = "All fields are required.";
    } elseif (!filter_var($link, FILTER_VALIDATE_URL)) {
        $err = "Please provide a valid URL including http:// or https://.";
    } else {
        $count = (int)$db->query("SELECT COUNT(*) FROM earn_buttons")->fetchColumn();

        if ($count >= 100) {
            $err = "Maximum 100 earn buttons allowed.";
        } else {
            $db->prepare("
                INSERT INTO earn_buttons (title, link, status)
                VALUES (?, ?, 'active')
            ")->execute([$title, $link]);

            log_admin_action($db, $admin['id'], "Added earn button: $title");

            $msg = "Earn button added successfully.";
        }
    }
}

/* ================= TOGGLE STATUS ================= */
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    $db->prepare("
        UPDATE earn_buttons
        SET status = CASE 
            WHEN status='active' THEN 'off'
            ELSE 'active'
        END
        WHERE id=?
    ")->execute([$id]);

    header("Location: earn.php");
    exit;
}

/* ================= DELETE BUTTON ================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM earn_buttons WHERE id=?")->execute([$id]);

    log_admin_action($db, $admin['id'], "Deleted earn button #$id");

    $msg = "Earn button deleted.";
}

/* ================= FETCH BUTTONS ================= */
$buttons = $db->query("SELECT * FROM earn_buttons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Earn Buttons';
$activeNav = 'earn.php';
$pageSubtitle = 'Manage clickable sponsor links and tasks that reward members in the Earn section.';

require_once __DIR__ . "/layout_top.php";
?>

<?php if ($msg): ?>
    <div class="admin-alert admin-alert-success">
        <span>✅</span>
        <div><?= htmlspecialchars($msg) ?></div>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="admin-alert admin-alert-danger">
        <span>❌</span>
        <div><?= htmlspecialchars($err) ?></div>
    </div>
<?php endif; ?>

<!-- ADD BUTTON -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">⚡ Add New Earn Button</h2>
    </div>

    <form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; align-items: flex-end;">
        <input type="hidden" name="add" value="1">
        <div class="admin-form-group" style="margin-bottom: 0;">
            <label class="admin-label">Button Title / Task Name</label>
            <input type="text" name="title" class="admin-input" placeholder="e.g. Visit Partner Website #1" required>
        </div>

        <div class="admin-form-group" style="margin-bottom: 0;">
            <label class="admin-label">Target URL</label>
            <input type="url" name="link" class="admin-input" placeholder="https://..." required>
        </div>

        <div>
            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; height: 46px;">
                ➕ Create Button
            </button>
        </div>
    </form>
</div>

<!-- BUTTONS LIST -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📜 Configured Earn Buttons (<?= count($buttons) ?>)</h2>
    </div>

    <?php if (empty($buttons)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No earn buttons configured yet.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Destination Link</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buttons as $b): ?>
                        <tr>
                            <td>#<?= $b['id'] ?></td>
                            <td><strong style="color: #ffffff;"><?= htmlspecialchars($b['title']) ?></strong></td>
                            <td>
                                <a href="<?= htmlspecialchars($b['link']) ?>" target="_blank" rel="noopener" style="color: var(--admin-info); font-size: 13px; text-decoration: underline;">
                                    <?= htmlspecialchars(mb_strimwidth($b['link'], 0, 45, '...')) ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($b['status'] === 'active'): ?>
                                    <span class="admin-badge admin-badge-success">ACTIVE</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-danger">DISABLED</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <a href="earn.php?toggle=<?= $b['id'] ?>" class="admin-btn admin-btn-sm admin-btn-secondary">
                                        <?= $b['status'] === 'active' ? 'Turn Off' : 'Turn On' ?>
                                    </a>
                                    <a href="earn.php?delete=<?= $b['id'] ?>" class="admin-btn admin-btn-sm admin-btn-danger" data-confirm="Delete this earn button?" data-confirm-title="Delete Button" data-confirm-danger="true" data-confirm-btn="Delete">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
