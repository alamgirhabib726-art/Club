<?php
session_start();
require_once __DIR__ . "/db.php";


/* LOGIN */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH USER */
$stmt = $db->prepare("
    SELECT id, status, apply_status, coins
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$user ||
    $user['status'] !== 'active' ||
    $user['apply_status'] !== 'approved'
) {
    die("ACCESS DENIED");
}

/* PRODUCTS */
$products = [
    'power' => ['title'=>'⚡ Power Click', 'coins'=>30],
    'turbo' => ['title'=>'🚀 Turbo Power Click', 'coins'=>10],
    'raw'   => ['title'=>'🔥 Raw Click', 'coins'=>10],
    'joint' => ['title'=>'🤝 Joint Click', 'coins'=>5],
    'paper' => ['title'=>'📄 Paper Click', 'coins'=>0.5],
    'manta_large' => ['title'=>'Manta Large Click', 'coins'=>70],
    'manta'       => ['title'=>'Manta Click', 'coins'=>35],
    
];

$msg = '';
$error = '';

/* PURCHASE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $key = $_POST['product'] ?? '';

    if (!isset($products[$key])) {
        $error = "❌ Invalid product";
    } else {

        $cost = (float)$products[$key]['coins'];

        if ($user['coins'] < $cost) {
            $error = "❌ Insufficient coins";
        } else {

            $db->beginTransaction();

            try {
                /* CUT COINS */
                $stmt = $db->prepare("
                    UPDATE users
                    SET coins = coins - ?
                    WHERE id = ? AND coins >= ?
                ");
                $stmt->execute([$cost, $uid, $cost]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception("Coin update failed");
                }

                /* SAVE PURCHASE */
                $db->prepare("
                    INSERT INTO payments
                    (user_id, type, amount, source, status, created_at)
                    VALUES (?, 'purchase', ?, ?, 'pending', NOW())
                ")->execute([
                    $uid,
                    $cost,
                    $key
                ]);

                $db->commit();

                $user['coins'] -= $cost;
                $msg = "✅ Purchase submitted. Waiting for approval.";

            } catch (Exception $e) {
                $db->rollBack();
                $error = "❌ Purchase failed";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Purchase • Unmoor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{
    margin:0;
    background:#0b0f19;
    color:#e5e7eb;
    font-family:system-ui;
}
.card{
    max-width:420px;
    margin:30px auto;
    background:#121826;
    padding:22px;
    border-radius:22px;
    margin-bottom:120px; /* ✅ bottom nav space */
}

.product{
    border:1px solid #1f2937;
    border-radius:18px;
    padding:16px;
    margin-top:14px;
}
button{
    width:100%;
    margin-top:12px;
    padding:14px;
    border:none;
    border-radius:14px;
    background:#22c55e;
    color:#022c22;
    font-weight:900;
}
.history{
    display:block;
    margin-top:16px;
    padding:14px;
    text-align:center;
    border-radius:14px;
    text-decoration:none;
    font-weight:800;
    background:#1f2937;
    color:#e5e7eb;
}
.back{
    display:block;
    margin-top:12px;
    padding:14px;
    text-align:center;
    border-radius:14px;
    text-decoration:none;
    font-weight:900;
    background:linear-gradient(135deg,#ef4444,#dc2626);
    color:#ffffff;
}
.msg{text-align:center;color:#22c55e;font-weight:800}
.err{text-align:center;color:#ef4444;font-weight:800}
/* =========================
   MANUAL BOTTOM NAV (HEAVY)
========================= */

.manual-nav{
    position:fixed;
    bottom:0;
    left:0;
    right:0;

    height:96px;
    background:linear-gradient(
        180deg,
        rgba(15,23,42,.88),
        rgba(2,6,23,.95)
    );

    backdrop-filter:blur(14px);
    border-top:1px solid rgba(255,255,255,.08);

    display:flex;
    justify-content:space-around;
    align-items:flex-end;

    padding-bottom:12px;
    z-index:999;
}

/* BUTTON */
.manual-nav .nav-btn{
    flex:1;
    text-decoration:none;
    color:#e5e7eb;
    font-size:13px;
    font-weight:900;

    display:flex;
    flex-direction:column;
    align-items:center;
    gap:8px;
}

/* ICON (HEAVY) */
.manual-nav .icon{
    width:52px;
    height:52px;
    border-radius:16px;

    background:#020617;
    color:#e5e7eb;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:22px;

    border:1px solid rgba(255,255,255,.14);

    box-shadow:
        0 10px 22px rgba(0,0,0,.6),
        inset 0 1px 0 rgba(255,255,255,.08);
}

/* ACTIVE SIDE BUTTON */
.manual-nav .nav-btn.active{
    color:#22c55e;
}
.manual-nav .nav-btn.active .icon{
    background:rgba(34,197,94,.22);
    color:#22c55e;
    box-shadow:
        0 0 18px rgba(34,197,94,.6),
        inset 0 1px 0 rgba(255,255,255,.25);
}

/* CENTER BUY */
.manual-nav .nav-btn.center{
    transform:translateY(-6px);
}

.manual-nav .nav-btn.center .icon{
    width:78px;
    height:78px;
    font-size:34px;
    border-radius:22px;

    background:linear-gradient(180deg,#fde68a,#f59e0b);
    color:#422006;

    box-shadow:
        0 16px 36px rgba(245,158,11,.75),
        inset 0 2px 0 rgba(255,255,255,.5);
}

/* BUY TEXT */
.manual-nav .nav-btn.center span{
    color:#facc15;
    font-weight:900;
}

/* SPACE FOR NAV */
.wrap{
    padding-bottom:160px;
}
/* PREVENT RANDOM GREEN GLOW */
.manual-nav .nav-btn:not(.active) .icon{
    box-shadow:
        0 6px 14px rgba(0,0,0,.45);
    background:#020617;
    color:#e5e7eb;
}

/* PREVENT RANDOM GREEN — BUT EXCLUDE BUY */
.manual-nav .nav-btn:not(.active):not(.center) .icon{
    background:#020617;
    color:#e5e7eb;
    box-shadow:
        0 6px 14px rgba(0,0,0,.45);
}
</style>
</head>

<body>
<div class="card">

<h2>🛒 Purchase</h2>
<p style="text-align:center">
    Coins: 🪙 <?= number_format($user['coins'],2) ?>
</p>

<?php if($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
<?php if($error): ?><div class="err"><?= $error ?></div><?php endif; ?>

<?php foreach($products as $k=>$p): ?>
<form method="post" class="product">
    <strong><?= htmlspecialchars($p['title']) ?></strong>
    <div>Cost: 🪙 <?= $p['coins'] ?></div>
    <input type="hidden" name="product" value="<?= $k ?>">
    <button>Purchase</button>
</form>
<?php endforeach; ?>

<a class="history" href="purchase_history.php">📜 Purchase History</a>


</div>

<!-- ===== MANUAL BOTTOM NAV ===== -->
<div class="manual-nav">

    <a class="nav-btn" href="dashboard.php">
        <div class="icon">🏠</div>
        <span>Home</span>
    </a>

    <a class="nav-btn" href="earn.php">
        <div class="icon">💰</div>
        <span>Earn</span>
    </a>

    <!-- ✅ BUY ACTIVE -->
    <a class="nav-btn center active" href="purchase.php">
        <div class="icon">🛒</div>
        <span>Buy</span>
    </a>

    <a class="nav-btn" href="headtail.php">
        <div class="icon">🎲</div>
        <span>Head/Tail</span>
    </a>

    <a class="nav-btn" href="account.php">
        <div class="icon">👤</div>
        <span>Account</span>
    </a>

</div>
</body>
</html>
