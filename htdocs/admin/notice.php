<?php
session_start();
require_once "../db.php";

/* ADMIN LOGIN REQUIRED */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

/* CHECK ADMIN ROLE */
$stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || $admin['role'] !== 'admin') {
    die("ACCESS DENIED");
}

/* ADD NOTICE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);

    if ($message !== '') {
        $stmt = $db->prepare("INSERT INTO notices (message) VALUES (?)");
        $stmt->execute([$message]);
    }
}

/* DELETE NOTICE */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM notices WHERE id = ?")->execute([$id]);
}

/* FETCH NOTICES */
$notices = $db->query("
    SELECT id, message, created_at
    FROM notices
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Notices • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box;font-family:system-ui}
body{background:#f4f4f5;padding:20px}
.card{
    background:#fff;
    padding:20px;
    border-radius:16px;
    margin-bottom:20px;
}
textarea{
    width:100%;
    min-height:120px;
    padding:12px;
    border-radius:12px;
    border:1px solid #ddd;
}
button{
    padding:12px 18px;
    border:none;
    border-radius:12px;
    background:#7c3aed;
    color:#fff;
    font-weight:600;
    cursor:pointer;
}
.notice{
    background:#f9fafb;
    padding:12px;
    border-radius:12px;
    margin-bottom:10px;
}
small{opacity:.6}
a{color:#dc2626;text-decoration:none;font-weight:600}
</style>
</head>

<body>

<h2>📢 Notice Management</h2>

<!-- ADD NOTICE -->
<div class="card">
    <form method="post">
        <textarea name="message" placeholder="Write notice here (max ~150 lines)..." required></textarea>
        <br><br>
        <button type="submit">Post Notice</button>
    </form>
</div>

<!-- EXISTING NOTICES -->
<div class="card">
    <h3>All Notices</h3>

<?php if (!$notices): ?>
    <p>No notices yet.</p>
<?php else: ?>
    <?php foreach ($notices as $n): ?>
        <div class="notice">
            <small><?= date("d M Y, h:i A", strtotime($n['created_at'])) ?></small>
            <p><?= nl2br(htmlspecialchars($n['message'])) ?></p>
            <a href="?delete=<?= $n['id'] ?>" onclick="return confirm('Delete this notice?')">
                ❌ Delete
            </a>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

</body>
</html>
