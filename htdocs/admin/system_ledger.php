<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ================= ADMIN GUARD ================= */
if (!isset($_SESSION['user_id'])) {
    die("NO SESSION");
}

$stmt = $db->prepare("SELECT id, role, name FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || $admin['role'] !== 'admin') {
    die("ADMIN ONLY");
}

/* ================= SYSTEM USER ================= */
$stmt = $db->prepare("
    SELECT id, coins
    FROM users
    WHERE role = 'system'
    LIMIT 1
");
$stmt->execute();
$system = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$system) {
    die("SYSTEM ACCOUNT NOT FOUND");
}

$msg = $err = "";

/* ================= CASH OUT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cash = round((float)($_POST['cashout'] ?? 0), 2);

    if ($cash <= 0) {
        $err = "❌ Invalid amount";
    }
    elseif ($cash > $system['coins']) {
        $err = "❌ Insufficient system balance";
    }
    else {

        $db->beginTransaction();

        try {

            /* ================= UPDATE SYSTEM BALANCE ================= */
            $stmt = $db->prepare("
                UPDATE users
                SET coins = coins - ?
                WHERE id = ?
            ");
            $stmt->execute([$cash, $system['id']]);

            /* ================= SYSTEM COIN HISTORY ================= */
            $stmt = $db->prepare("
                INSERT INTO coin_history
                    (user_id, amount, type,
                     source_user_id, source_name, source_number, created_at)
                VALUES
                    (?, ?, 'admin_cashout', ?, ?, 'ADMIN', NOW())
            ");
            $stmt->execute([
                $system['id'],
                -$cash,              // ✅ always negative
                $admin['id'],
                $admin['name']
            ]);

            /* ================= SYSTEM LEDGER (AUDIT LOG) ================= */
            $stmt = $db->prepare("
                INSERT INTO system_ledger
                    (type, amount, source, reference, created_at)
                VALUES
                    ('admin_cashout', ?, 'admin_panel', ?, NOW())
            ");
            $stmt->execute([
                -$cash,
                'Admin ID: '.$admin['id'].' | '.$admin['name']
            ]);

            $db->commit();

            // local update (UI only)
            $system['coins'] -= $cash;

            $msg = "✅ Cash-out successful";

        } catch (Exception $e) {

            $db->rollBack();
            error_log("ADMIN CASHOUT ERROR: ".$e->getMessage());
            $err = "❌ Cash-out failed";

        }
    }
}

/* ================= FETCH SYSTEM HISTORY ================= */
$stmt = $db->prepare("
    SELECT amount, type,
           source_name, source_number,
           created_at
    FROM coin_history
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 200
");
$stmt->execute([$system['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Ledger • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#0b0f19;
    --card:#121826;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --green:#22c55e;
    --red:#ef4444;
}
*{box-sizing:border-box;font-family:system-ui}
body{margin:0;background:var(--bg);color:var(--text)}
.wrap{max-width:960px;margin:auto;padding:24px}

.card{
    background:linear-gradient(135deg,#0f172a,#020617);
    border:1px solid var(--border);
    border-radius:24px;
    padding:24px;
    box-shadow:0 30px 60px rgba(0,0,0,.6);
}

h2{margin:0 0 12px;font-weight:900}

.balance{
    font-size:22px;
    font-weight:900;
    margin-bottom:16px;
}
.balance.pos{color:var(--green)}
.balance.neg{color:var(--red)}

input{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:1px solid var(--border);
    background:#020617;
    color:var(--text);
    margin-bottom:10px;
}

button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg,#ef4444,#dc2626);
    color:#fff;
    font-weight:900;
    cursor:pointer;
}

.msg{color:var(--green);font-weight:800;margin-bottom:10px}
.err{color:var(--red);font-weight:800;margin-bottom:10px}

/* ================= LEDGER ROW ================= */
.row{
    display:flex;
    justify-content:space-between;
    padding:16px 0;
    border-bottom:1px dashed var(--border);
}
.row:last-child{border-bottom:none}

.type{
    font-size:12px;
    text-transform:uppercase;
    color:var(--muted);
    font-weight:800;
}
.from{
    font-size:14px;
    margin-top:4px;
}
.time{
    font-size:11px;
    color:#64748b;
}

.plus{color:var(--green);font-weight:900}
.minus{color:var(--red);font-weight:900}

.back{
    display:block;
    margin-top:18px;
    text-align:center;
    color:var(--muted);
    text-decoration:none;
    font-weight:700;
}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<h2>📊 System Ledger</h2>

<div class="balance <?= $system['coins'] < 0 ? 'neg' : 'pos' ?>">
    🪙 <?= number_format($system['coins'],2) ?>
</div>

<?php if($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="err"><?= $err ?></div><?php endif; ?>

<form method="post">
    <input type="number" step="0.01" name="cashout" placeholder="Cash-out coins">
    <button>💸 Cash Out</button>
</form>

<?php if ($rows): foreach ($rows as $r): ?>
<div class="row">
    <div>
        <div class="type"><?= htmlspecialchars($r['type']) ?></div>
        <?php if($r['source_name']): ?>
            <div class="from">
                From: <?= htmlspecialchars($r['source_name']) ?>
                <?= $r['source_number'] ? '(' . htmlspecialchars($r['source_number']) . ')' : '' ?>
            </div>
        <?php endif; ?>
        <div class="time"><?= date("d M Y, h:i A", strtotime($r['created_at'])) ?></div>
    </div>
    <div class="<?= $r['amount'] < 0 ? 'minus' : 'plus' ?>">
        <?= $r['amount'] < 0 ? '−' : '+' ?><?= number_format(abs($r['amount']),2) ?>
    </div>
</div>
<?php endforeach; else: ?>
<p style="color:var(--muted)">No system transactions yet.</p>
<?php endif; ?>

</div>

<a class="back" href="dashboard.php">← Back to Admin Dashboard</a>
</div>
</body>
</html>
