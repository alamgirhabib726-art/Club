<?php
session_start();
require_once "../db.php";

/* ================= ADMIN CHECK ================= */
if (!isset($_SESSION['user_id'])) {
    die("NO ACCESS");
}

$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    die("NO ACCESS");
}

$msg = '';
$err = '';

/* ================= ADD BUTTON ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {

    $title = trim($_POST['title'] ?? '');
    $link  = trim($_POST['link'] ?? '');

    if ($title === '' || $link === '') {
        $err = "All fields are required";
    } elseif (!filter_var($link, FILTER_VALIDATE_URL)) {
        $err = "Invalid URL";
    } else {

        $count = (int)$db->query("SELECT COUNT(*) FROM earn_buttons")->fetchColumn();

        if ($count >= 100) {
            $err = "Maximum 100 buttons allowed";
        } else {
            $db->prepare("
                INSERT INTO earn_buttons (title, link, status)
                VALUES (?, ?, 'active')
            ")->execute([$title, $link]);

            $msg = "✅ Button added successfully";
        }
    }
}

/* ================= TOGGLE ================= */
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

    header("Location: earn_buttons.php");
    exit;
}

/* ================= FETCH ================= */
$rows = $db->query("
    SELECT id, title, link, status
    FROM earn_buttons
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Earn Buttons • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{
  max-width:720px;
  margin:auto;
  background:#fff;
  padding:22px;
  border-radius:18px;
  box-shadow:0 20px 40px rgba(0,0,0,.15)
}
input{
  width:100%;
  padding:12px;
  margin-bottom:10px;
  border:none;
  border-radius:12px;
  background:#f2f2f2
}
button{
  padding:12px 16px;
  border:none;
  border-radius:12px;
  background:#7c3aed;
  color:#fff;
  font-weight:700;
  cursor:pointer
}
table{
  width:100%;
  border-collapse:collapse;
  margin-top:16px
}
th,td{
  padding:10px;
  border-bottom:1px solid #e5e7eb;
  text-align:left
}
.status-active{color:#16a34a;font-weight:700}
.status-off{color:#dc2626;font-weight:700}
.msg{color:#16a34a;font-weight:700}
.err{color:#dc2626;font-weight:700}
a{text-decoration:none;color:#2563eb;font-weight:600}
</style>
</head>

<body>

<div class="card">
<h3>💰 Earn Buttons (Admin)</h3>

<?php if ($msg): ?><p class="msg"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endif; ?>

<form method="post">
  <input name="title" placeholder="Button Title" required>
  <input name="link" placeholder="https://example.com" required>
  <button name="add">Add Button</button>
</form>

<table>
<tr>
  <th>Title</th>
  <th>Link</th>
  <th>Status</th>
  <th>Action</th>
</tr>

<?php foreach ($rows as $r): ?>
<tr>
  <td><?= htmlspecialchars($r['title']) ?></td>
  <td>
    <a href="<?= htmlspecialchars($r['link']) ?>" target="_blank">
      Open
    </a>
  </td>
  <td class="status-<?= $r['status'] ?>">
    <?= strtoupper($r['status']) ?>
  </td>
  <td>
    <a href="?toggle=<?= (int)$r['id'] ?>">Toggle</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<p style="margin-top:14px">
<a href="dashboard.php">← Admin Dashboard</a>
</p>
</div>

</body>
</html>