<?php
require_once "guard.php";
require_once "../db.php";

/* ======================
   BAN / UNBAN ACTION
====================== */
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        if ($_GET['action'] === 'ban') {
            $db->prepare("
                UPDATE users
                SET status = 'banned'
                WHERE id = ?
                  AND role NOT IN ('admin','sub_admin','system')
            ")->execute([$id]);
        }

        if ($_GET['action'] === 'unban') {
            $db->prepare("
                UPDATE users
                SET status = 'active'
                WHERE id = ?
                  AND role NOT IN ('admin','sub_admin','system')
            ")->execute([$id]);
        }
    }

    header("Location: users.php");
    exit;
}

/* ======================
   FETCH USERS
====================== */
$users = $db->query("
    SELECT
        id, name, phone, role, status,
        apply_status, coins, coin_cycle_start
    FROM users
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$now = time();

/* ======================
   NEGATIVE USERS COUNT
====================== */
$negativeCount = 0;
foreach ($users as $u) {
    if ($u['role'] === 'user' && $u['coins'] < 0) {
        $negativeCount++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Users • Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
/* ================= BASE ================= */
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrap{
    max-width:1280px;
    margin:auto;
    padding:26px;
}

/* ================= CARD ================= */
.card{
    background:linear-gradient(135deg,#0f172a,#020617);
    border-radius:24px;
    padding:26px;
    box-shadow:0 20px 45px rgba(0,0,0,.55);
}

/* ================= HEADER ================= */
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
.header .alert{
    color:#fecaca;
    font-weight:900;
    background:rgba(239,68,68,.12);
    padding:8px 14px;
    border-radius:999px;
    font-size:13px;
}

/* ================= TABLE ================= */
table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    overflow:hidden;
}

th{
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.4px;
    color:#9ca3af;
    padding:14px 16px;
    border-bottom:1px solid #1f2937;
    text-align:left;
    background:#020617;
}

td{
    padding:16px;
    border-bottom:1px solid #1f2937;
    font-size:14px;
}

tr:last-child td{
    border-bottom:none;
}

/* ================= BADGES ================= */
.badge{
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
    display:inline-block;
}
.active{background:#22c55e;color:#022c22}
.banned{background:#ef4444;color:#fff}
.pending{background:#64748b;color:#fff}
.system{background:#0ea5e9;color:#022c22}
.negative-badge{background:#7f1d1d;color:#fecaca}

/* ================= COINS ================= */
.coin{
    font-weight:900;
}
.coin.negative{color:#ef4444}
.coin.positive{color:#facc15}

/* ================= TIME ================= */
.time{
    font-size:12px;
    color:#9ca3af;
}

/* ================= ACTIONS ================= */
.actions{
    display:flex;
    gap:10px;
}

a.btn{
    padding:8px 16px;
    border-radius:12px;
    font-size:13px;
    font-weight:900;
    text-decoration:none;
    min-width:90px;
    text-align:center;
}

.ban{
    background:linear-gradient(135deg,#ef4444,#dc2626);
    color:#fff;
}
.unban{
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
}

.disabled{
    opacity:.4;
    pointer-events:none;
}

/* ================= NEGATIVE ROW ================= */
tr.negative-row{
    background:rgba(127,29,29,.35);
}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<div class="header">
    <h2>👥 Users</h2>
    <div class="alert">🔻 Negative Balance: <?= $negativeCount ?></div>
</div>

<table>
<tr>
    <th>Name</th>
    <th>Phone</th>
    <th>Role</th>
    <th>Status</th>
    <th>Coins</th>
    <th>Next Cut</th>
    <th>Action</th>
</tr>

<?php foreach ($users as $u): ?>

<?php
$nextCut = "—";

if (
    $u['role'] === 'user' &&
    $u['status'] === 'active' &&
    $u['apply_status'] === 'approved' &&
    !empty($u['coin_cycle_start'])
) {
    $start = strtotime($u['coin_cycle_start']);
    $nextTs = $start + (floor(($now - $start) / 86400) + 1) * 86400;
    $remain = max(0, $nextTs - $now);
    $nextCut = floor($remain / 3600)."h ".floor(($remain % 3600) / 60)."m";
}

$coinClass = ($u['coins'] < 0) ? 'negative' : 'positive';
$rowClass  = ($u['coins'] < 0 && $u['role'] === 'user') ? 'negative-row' : '';
?>

<tr class="<?= $rowClass ?>">
    <td><?= htmlspecialchars($u['name']) ?></td>
    <td><?= htmlspecialchars($u['phone']) ?></td>

    <td>
        <?php if ($u['role'] === 'system'): ?>
            <span class="badge system">SYSTEM</span>
        <?php else: ?>
            <?= strtoupper(htmlspecialchars($u['role'])) ?>
        <?php endif; ?>
    </td>

    <td>
        <?php if ($u['status'] === 'active' && $u['apply_status'] === 'approved'): ?>
            <span class="badge active">ACTIVE</span>
        <?php elseif ($u['status'] === 'banned'): ?>
            <span class="badge banned">BANNED</span>
        <?php else: ?>
            <span class="badge pending">PENDING</span>
        <?php endif; ?>
    </td>

    <td class="coin <?= $coinClass ?>">
        🪙 <?= number_format($u['coins'],2) ?>
        <?php if ($u['coins'] < 0 && $u['role'] === 'user'): ?>
            <span class="badge negative-badge">NEGATIVE</span>
        <?php endif; ?>
    </td>

    <td class="time"><?= $nextCut ?></td>

    <td>
        <?php if (in_array($u['role'], ['admin','sub_admin','system'], true)): ?>
            <span class="disabled">—</span>
        <?php elseif ($u['status'] === 'banned'): ?>
            <div class="actions">
                <a class="btn unban" href="?action=unban&id=<?= $u['id'] ?>">Unban</a>
            </div>
        <?php else: ?>
            <div class="actions">
                <a class="btn ban" href="?action=ban&id=<?= $u['id'] ?>">Ban</a>
            </div>
        <?php endif; ?>
    </td>
</tr>

<?php endforeach; ?>
</table>

</div>
</div>
</body>
</html>
