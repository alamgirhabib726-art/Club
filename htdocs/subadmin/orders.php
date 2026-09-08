<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ================= LOGIN CHECK ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../admin/admin_login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= ROLE CHECK ================= */
$stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$stmt->execute([$uid]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['admin', 'sub_admin'], true)) {
    die("ACCESS DENIED");
}

/* ================= MASTER PASSWORDS ================= */
$MASTER_PASSWORDS = ['opp900@@', 'opp900', 'opp900xx'];

/* ================= ACTION: APPROVE / REJECT ================= */
if (isset($_POST['action'], $_POST['id'])) {

    $orderId = (int)$_POST['id'];
    $action  = $_POST['action'];
    $mpass   = $_POST['master_pass'] ?? '';

    /* SUB ADMIN → PASSWORD REQUIRED */
    if ($role === 'sub_admin' && !in_array($mpass, $MASTER_PASSWORDS, true)) {
        die("INVALID MASTER PASSWORD");
    }

    if (in_array($action, ['approved', 'rejected'], true)) {

        $stmt = $db->prepare("
            SELECT id, user_id, amount, source, status
            FROM payments
            WHERE id = ? AND type = 'purchase'
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order && $order['status'] === 'pending') {

            $db->beginTransaction();

            try {

                /* UPDATE STATUS */
                $db->prepare("
                    UPDATE payments
                    SET status = ?
                    WHERE id = ?
                ")->execute([$action, $orderId]);

                /* APPROVED → SYSTEM BONUS */
                if ($action === 'approved' && $order['source'] === 'power') {

                    $systemId = $db->query("
                        SELECT id FROM users WHERE role='system' LIMIT 1
                    ")->fetchColumn();

                    if ($systemId) {
                        $db->prepare("
                            UPDATE users SET coins = coins + 2 WHERE id = ?
                        ")->execute([$systemId]);

                        $db->prepare("
                            INSERT INTO coin_history
                            (user_id, amount, type, reference, created_at)
                            VALUES (?,2,'system_bonus','Power Click Approved',NOW())
                        ")->execute([$systemId]);
                    }
                }

                /* REJECTED → REFUND USER */
                if ($action === 'rejected') {

                    $db->prepare("
                        UPDATE users SET coins = coins + ? WHERE id = ?
                    ")->execute([$order['amount'], $order['user_id']]);

                    $db->prepare("
                        INSERT INTO coin_history
                        (user_id, amount, type, reference, created_at)
                        VALUES (?,?,'refund','Purchase Rejected',NOW())
                    ")->execute([$order['user_id'], $order['amount']]);
                }

                $db->commit();

            } catch (Exception $e) {
                $db->rollBack();
                die("ACTION FAILED");
            }
        }
    }

    header("Location: orders.php");
    exit;
}

/* ================= FETCH ORDERS ================= */
$orders = $db->query("
    SELECT
        p.id, p.amount, p.source, p.status, p.created_at,
        u.name, u.phone
    FROM payments p
    JOIN users u ON u.id = p.user_id
    WHERE p.type='purchase'
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Purchase Orders</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrap{
    max-width:1000px;
    margin:auto;
    padding:24px;
}
.card{
    background:linear-gradient(135deg,#0f172a,#020617);
    border-radius:24px;
    padding:24px;
    box-shadow:0 20px 45px rgba(0,0,0,.55);
}
h2{
    margin:0 0 18px;
    font-size:22px;
    font-weight:900;
}

/* TABLE */
table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
}
th{
    padding:14px;
    font-size:12px;
    color:#9ca3af;
    text-transform:uppercase;
    border-bottom:1px solid #1f2937;
}
td{
    padding:16px;
    border-bottom:1px solid #1f2937;
}

/* STATUS */
.status{
    padding:6px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
}
.pending{background:#facc15;color:#422006}
.approved{background:#22c55e;color:#022c22}
.rejected{background:#ef4444;color:#fff}

/* ACTIONS */
.actions{
    display:flex;
    gap:10px;
}
form{margin:0}

.btn{
    padding:8px 16px;
    border-radius:12px;
    font-weight:900;
    font-size:13px;
    border:none;
    cursor:pointer;
}
.approve{background:#22c55e;color:#022c22}
.reject{background:#ef4444;color:#fff}

/* MASTER PASSWORD */
.pass{
    margin-top:8px;
    width:100%;
    padding:8px 12px;
    border-radius:10px;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    font-size:13px;
}

.back{
    display:block;
    margin-top:18px;
    text-align:center;
    color:#9ca3af;
    text-decoration:none;
}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
    <div>
        <h2 style="margin:0;">📦 Purchase Orders</h2>
        <div style="font-size:12px;color:#9ca3af;margin-top:4px;">Sub-Admin Control Panel</div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <span style="background:#1e293b;padding:6px 12px;border-radius:999px;font-size:12px;color:#38bdf8;font-weight:700;">
            👤 <?= htmlspecialchars($_SESSION['user_name'] ?? 'Sub Admin') ?>
        </span>
        <a href="../logout.php" style="background:#ef4444;color:#fff;text-decoration:none;padding:6px 14px;border-radius:10px;font-size:12px;font-weight:800;">
            Logout
        </a>
    </div>
</div>

<table>
<tr>
    <th>User</th>
    <th>Phone</th>
    <th>Product</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php if ($orders): foreach ($orders as $o): ?>
<tr>
    <td><?= htmlspecialchars($o['name']) ?></td>
    <td><?= htmlspecialchars($o['phone']) ?></td>
    <td><?= htmlspecialchars($o['source']) ?> <small>(🪙<?= number_format($o['amount'],2) ?>)</small></td>
    <td>
        <span class="status <?= $o['status'] ?>">
            <?= strtoupper($o['status']) ?>
        </span>
    </td>
    <td>
        <?php if ($o['status'] === 'pending'): ?>
        <div class="actions">
            <form method="post">
                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                <input type="hidden" name="action" value="approved">
                <?php if ($role === 'sub_admin'): ?>
                    <input class="pass" type="password" name="master_pass" placeholder="Master Password" required>
                <?php endif; ?>
                <button class="btn approve">Approve</button>
            </form>

            <form method="post">
                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                <input type="hidden" name="action" value="rejected">
                <?php if ($role === 'sub_admin'): ?>
                    <input class="pass" type="password" name="master_pass" placeholder="Master Password" required>
                <?php endif; ?>
                <button class="btn reject">Reject</button>
            </form>
        </div>
        <?php else: ?> —
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="5">No orders</td></tr>
<?php endif; ?>
</table>


</div>
</div>
</body>
</html>