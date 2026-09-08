<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ================= ADMIN GUARD ================= */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

/* ================= COUPON CODE GENERATOR (UNIQUE) ================= */
function generateUniqueCoupon(PDO $db, string $type): string {

    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '123456789';

    do {
        $rand =
            $letters[rand(0,25)] .
            $numbers[rand(0,8)] .
            $letters[rand(0,25)] .
            $numbers[rand(0,8)];

        $code = 'UNM-' . ($type === 'apply' ? 'REG' : 'DEP') . '-' . $rand;

        $chk = $db->prepare("SELECT id FROM coupons WHERE code=? LIMIT 1");
        $chk->execute([$code]);

    } while ($chk->fetch());

    return $code;
}

/* ================= CREATE COUPON ================= */
$msg = $err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = (float)($_POST['amount'] ?? 0);
    $type   = $_POST['type'] ?? '';

    if ($amount <= 0) {
        $err = "❌ Invalid amount";
    } elseif (!in_array($type, ['apply','deposit'], true)) {
        $err = "❌ Invalid coupon type";
    } else {

        try {

            $code = generateUniqueCoupon($db, $type);

            $db->prepare("
                INSERT INTO coupons
                (code, amount, type, status, created_at)
                VALUES (?, ?, ?, 'active', NOW())
            ")->execute([$code, $amount, $type]);

            /* POST → REDIRECT → GET (ANTI REFRESH DUPLICATE) */
            $_SESSION['coupon_success'] = "✅ Coupon created: $code";
            header("Location: coupons.php");
            exit;

        } catch (Exception $e) {
            $err = "❌ Coupon creation failed";
        }
    }
}

/* FLASH MESSAGE */
if (isset($_SESSION['coupon_success'])) {
    $msg = $_SESSION['coupon_success'];
    unset($_SESSION['coupon_success']);
}

/* ================= FETCH COUPONS ================= */
$coupons = $db->query("
    SELECT code, amount, type, status, created_at
    FROM coupons
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coupons • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrap{
    max-width:1100px;
    margin:auto;
    padding:24px;
}
.card{
    background:#121826;
    border-radius:22px;
    padding:22px;
    margin-bottom:24px;
    box-shadow:0 20px 40px rgba(0,0,0,.45);
}
h2{margin-top:0}
input,select{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    margin-bottom:14px;
}
button{
    padding:14px;
    width:100%;
    border:none;
    border-radius:16px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    font-weight:900;
}
.msg{color:#22c55e;font-weight:800;margin-bottom:10px}
.err{color:#ef4444;font-weight:800;margin-bottom:10px}

table{
    width:100%;
    border-collapse:collapse;
}
th,td{
    padding:14px;
    border-bottom:1px solid #1f2937;
    font-size:14px;
}
th{color:#9ca3af}
.badge{
    padding:5px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.active{background:#22c55e;color:#022c22}
.used{background:#64748b;color:#fff}
.apply{background:#0ea5e9;color:#022c22}
.deposit{background:#fbbf24;color:#422006}

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
    <h2>🎟 Create Coupon</h2>

    <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="post">
        <input type="number" step="0.01" name="amount" placeholder="Amount (BDT)" required>

        <select name="type" required>
            <option value="">Select Coupon Type</option>
            <option value="apply">Apply</option>
            <option value="deposit">Deposit</option>
        </select>

        <button>Create Coupon</button>
    </form>
</div>

<div class="card">
    <h2>📜 Coupon List</h2>

    <table>
        <tr>
            <th>Code</th>
            <th>Amount</th>
            <th>Type</th>
            <th>Status</th>
            <th>Created</th>
        </tr>

        <?php if ($coupons): foreach ($coupons as $c): ?>
        <tr>
            <td><strong><?= htmlspecialchars($c['code']) ?></strong></td>
            <td><?= number_format($c['amount'],2) ?> BDT</td>
            <td><span class="badge <?= $c['type'] ?>"><?= strtoupper($c['type']) ?></span></td>
            <td><span class="badge <?= $c['status'] ?>"><?= strtoupper($c['status']) ?></span></td>
            <td><?= date("d M Y, h:i A", strtotime($c['created_at'])) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5">No coupons yet</td></tr>
        <?php endif; ?>
    </table>

    <a class="back" href="dashboard.php">← Back to Admin</a>
</div>

</div>
</body>
</html>
