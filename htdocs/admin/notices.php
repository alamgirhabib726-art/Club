<?php
/**
 * =========================================
 * ADMIN NOTICE MANAGEMENT
 * Unmoor Club
 * =========================================
 */

session_start();
require_once "../db.php";

/* ADMIN GUARD */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$role = $stmt->fetchColumn();

if ($role !== 'admin') {
    http_response_code(403);
    die("ACCESS DENIED");
}

/* ADD NOTICE */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        $error = "Notice cannot be empty.";
    } elseif (mb_strlen($message) > 1500) {
        $error = "Notice too long (max 1500 characters).";
    } else {
        $stmt = $db->prepare("INSERT INTO notices (message) VALUES (?)");
        $stmt->execute([$message]);
        header("Location: notices.php");
        exit;
    }
}

/* DELETE NOTICE */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM notices WHERE id = ?")->execute([$id]);
    header("Location: notices.php");
    exit;
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
<link rel="stylesheet" href="../assets/admin.css">
<style>
/* =========================
   ADMIN NOTICE – DARK MODE
========================= */

:root{
    --bg:#0b0f19;
    --panel:#0f172a;
    --panel-2:#020617;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --accent:#7c3aed;
    --danger:#ef4444;
}

/* BASE */
body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:system-ui;
}

/* LAYOUT */
.admin-wrapper{
    display:flex;
    min-height:100vh;
}

/* =========================
   SIDEBAR
========================= */
.sidebar{
    width:240px;
    background:linear-gradient(180deg,#020617,#020617);
    border-right:1px solid var(--border);
    padding:22px 16px;
}

.sidebar h1{
    margin:0 0 22px;
    font-size:18px;
    font-weight:900;
    color:#a78bfa;
}

.sidebar a{
    display:block;
    padding:12px 14px;
    margin-bottom:6px;
    border-radius:12px;
    text-decoration:none;
    font-weight:700;
    font-size:14px;
    color:#c7d2fe;
}

.sidebar a:hover{
    background:#111827;
}

.sidebar a.active{
    background:rgba(124,58,237,.18);
    color:#c4b5fd;
}

/* =========================
   MAIN
========================= */
.admin-main{
    flex:1;
    padding:26px;
}

/* TOP BAR */
.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:18px;
}

.topbar h2{
    margin:0;
    font-size:22px;
    font-weight:900;
}

.admin-badge{
    background:rgba(124,58,237,.2);
    color:#c4b5fd;
    padding:6px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}

/* =========================
   NOTICE CARD
========================= */
.notice-box{
    background:linear-gradient(135deg,var(--panel),var(--panel-2));
    border:1px solid var(--border);
    border-radius:18px;
    padding:18px;
    margin-bottom:16px;
    box-shadow:0 16px 36px rgba(0,0,0,.55);
}

/* TITLE */
.notice-box h3{
    margin:0 0 10px;
    font-size:15px;
    font-weight:900;
}

/* DATE */
.notice-date{
    font-size:12px;
    color:var(--muted);
    margin-bottom:10px;
}

/* NOTICE TEXT (IMPORTANT FIX) */
.notice-text{
    white-space:pre-wrap;
    line-height:1.6;
    font-size:14px;
    color:var(--text);
}

/* =========================
   FORM
========================= */
textarea{
    width:100%;
    min-height:140px;
    padding:14px;
    border-radius:14px;
    background:#020617;
    border:1px solid var(--border);
    color:var(--text);
    resize:vertical;
    outline:none;
    font-size:14px;
}

textarea::placeholder{
    color:#64748b;
}

button{
    margin-top:12px;
    padding:12px 18px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg,#7c3aed,#6d28d9);
    color:#fff;
    font-weight:900;
    cursor:pointer;
}

/* ERROR */
.error{
    background:rgba(239,68,68,.15);
    color:#fecaca;
    padding:10px 14px;
    border-radius:12px;
    font-weight:800;
    margin-bottom:12px;
}

/* DELETE */
.notice-actions{
    margin-top:12px;
}

.notice-actions a{
    color:#f87171;
    font-size:13px;
    font-weight:800;
    text-decoration:none;
}

.notice-actions a:hover{
    text-decoration:underline;
}
</style>
</head>

<body>

<div class="admin-wrapper">

  <div class="sidebar">
    <h1>ADMIN</h1>
    <a href="dashboard.php">Dashboard</a>
    <a class="active" href="notices.php">Notices</a>
    <a href="users.php">Users</a>
    <a href="payments.php">Payments</a>
    <a href="activity_logs.php">Logs</a>
  </div>

  <div class="admin-main">

    <div class="topbar">
      <h2>Notice Board</h2>
      <span class="admin-badge">ADMIN ONLY</span>
    </div>

    <!-- ADD NOTICE -->
    <div class="notice-box">
      <h3>Add New Notice</h3>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <textarea name="message" placeholder="বাংলায় নোটিশ লিখুন (max 1500 characters)"></textarea>
        <button type="submit">Post Notice</button>
      </form>
    </div>

    <!-- EXISTING NOTICES -->
    <?php if (!$notices): ?>
      <p style="color:#6b7280;">No notices yet.</p>
    <?php else: ?>
      <?php foreach ($notices as $n): ?>
        <div class="notice-box">
          <div class="notice-date">
            <?= date("d M Y, h:i A", strtotime($n['created_at'])) ?>
          </div>
         <div class="notice-text">
            <?= htmlspecialchars($n['message']) ?>
          </div>
          <div class="notice-actions" style="margin-top:8px;">
            <a href="?delete=<?= (int)$n['id'] ?>"
               onclick="return confirm('Delete this notice?')">
               ❌ Delete
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

</div>

</body>
</html>