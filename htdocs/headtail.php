<?php
session_start();
require_once __DIR__ . "/db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, coins, status, apply_status, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$user ||
    $user['status'] !== 'active' ||
    $user['apply_status'] !== 'approved' ||
    $user['role'] === 'system'
) {
    die("ACCESS DENIED");
}

$coins = (float)$user['coins'];

/* ================= FLASH ================= */
$resultMsg  = $_SESSION['game_msg']  ?? '';
$resultSide = $_SESSION['game_side'] ?? '';
$error      = $_SESSION['game_err']  ?? '';

unset($_SESSION['game_msg'], $_SESSION['game_side'], $_SESSION['game_err']);

/* ================= POST LOCK ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_SESSION['game_lock'])) {
        header("Location: headtail.php");
        exit;
    }
    $_SESSION['game_lock'] = true;

    $bet    = round((float)($_POST['bet'] ?? 0), 2);
    $choice = $_POST['choice'] ?? '';

    /* VALIDATION */
    if ($bet < 0.1) {
        $_SESSION['game_err'] = "❌ Minimum bet is 0.1";
        goto REDIRECT;
    }

    if ($bet > $coins) {
        $_SESSION['game_err'] = "❌ Insufficient coins";
        goto REDIRECT;
    }

    if (!in_array($choice, ['head','tail'], true)) {
        $_SESSION['game_err'] = "❌ Invalid choice";
        goto REDIRECT;
    }

    /* SYSTEM USER */
    $systemId = (int)$db->query("
        SELECT id FROM users WHERE role='system' LIMIT 1
    ")->fetchColumn();

    if (!$systemId) {
        $_SESSION['game_err'] = "❌ System account missing";
        goto REDIRECT;
    }

    /* USER CONTROL */
    $stmt = $db->prepare("
        SELECT last_bet, plays, last_play
        FROM game_control
        WHERE user_id = ?
    ");
    $stmt->execute([$uid]);
    $ctrl = $stmt->fetch(PDO::FETCH_ASSOC);

    $lastBet = (float)($ctrl['last_bet'] ?? 0);
    $plays   = (int)($ctrl['plays'] ?? 0);

    if (!empty($ctrl['last_play']) &&
        time() - strtotime($ctrl['last_play']) > 43200) {
        $plays = 0;
        $lastBet = 0;
    }

    /* FORCE LOSS */
    $forceLoss = false;
    if ($lastBet > 0 && $bet >= $lastBet * 2) $forceLoss = true;

    /* WIN ENGINE */
    if ($forceLoss) {
        $isWin = false;
    } else {
        $winChance = match (true) {
            $plays < 15  => 40,
            $plays < 50  => 30,
            $plays < 120 => 20,
            default      => 10
        };
        $isWin = random_int(1,100) <= $winChance;
    }

    /* ================= TRANSACTION ================= */
    $db->beginTransaction();

    try {

        $db->prepare("
            INSERT INTO game_control (user_id,last_bet,plays,last_play)
            VALUES (?,?,1,NOW())
            ON DUPLICATE KEY UPDATE
                last_bet=VALUES(last_bet),
                plays=plays+1,
                last_play=NOW()
        ")->execute([$uid,$bet]);

        if ($isWin) {

            $profit = min(round($bet * 0.8,2), $bet);

            $db->prepare("UPDATE users SET coins = coins + ? WHERE id=?")
               ->execute([$profit,$uid]);

            $db->prepare("UPDATE users SET coins = coins - ? WHERE id=?")
               ->execute([$profit,$systemId]);

            $db->prepare("
                INSERT INTO coin_history (user_id, amount, type)
                VALUES (?, ?, 'game_win')
            ")->execute([$uid,$profit]);

            $_SESSION['game_msg']  = "🎉 WIN +🪙".number_format($profit,2);
            $_SESSION['game_side'] = $choice;

        } else {

            $db->prepare("UPDATE users SET coins = coins - ? WHERE id=?")
               ->execute([$bet,$uid]);

            $db->prepare("UPDATE users SET coins = coins + ? WHERE id=?")
               ->execute([$bet,$systemId]);

            $db->prepare("
                INSERT INTO coin_history (user_id, amount, type)
                VALUES (?, ?, 'game_loss')
            ")->execute([$uid,-$bet]);

            $_SESSION['game_msg']  = "❌ LOSS -🪙".number_format($bet,2);
            $_SESSION['game_side'] = ($choice === 'head') ? 'tail' : 'head';
        }

        $db->commit();

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['game_err'] = "❌ Game failed";
    }

REDIRECT:
    unset($_SESSION['game_lock']);
    header("Location: headtail.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Head / Tail • Unmoor</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{
    margin:0;
    background:radial-gradient(circle at top,#020617,#000);
    font-family:system-ui;
    color:#e5e7eb;
}

/* MAIN CARD */
.card{
    max-width:420px;
    margin:40px auto;
    background:linear-gradient(135deg,#0f172a,#020617);
    border:1px solid #1f2937;
    border-radius:26px;
    padding:26px;
    box-shadow:0 30px 60px rgba(0,0,0,.75);
}

/* TITLE */
h3{
    text-align:center;
    margin:0;
    font-weight:900;
    letter-spacing:.5px;
}

/* BALANCE */
.balance{
    text-align:center;
    font-weight:900;
    margin:14px 0 6px;
    font-size:15px;
    color: <?= $coins < 0 ? '#ef4444' : '#22c55e' ?>;
}

/* COIN DISPLAY */
.coin{
    width:120px;
    height:120px;
    border-radius:50%;
    background:linear-gradient(135deg,#fde047,#facc15);
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:900;
    color:#422006;
    margin:20px auto;
    font-size:22px;
    box-shadow:
        0 18px 40px rgba(250,204,21,.55),
        inset 0 3px 6px rgba(255,255,255,.5);
}
.coin.head{
    background:linear-gradient(135deg,#fde047,#facc15);
}
.coin.tail{
    background:linear-gradient(135deg,#93c5fd,#3b82f6);
    color:#0f172a;
    box-shadow:
        0 18px 40px rgba(59,130,246,.55),
        inset 0 3px 6px rgba(255,255,255,.4);
}

/* BET INPUT (PREMIUM FIX) */
input{
    width:100%;
    padding:15px 16px;
    border-radius:16px;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    font-size:15px;
    font-weight:800;
    margin:14px 0;
    outline:none;
}
input::placeholder{
    color:#9ca3af;
}
input:focus{
    border-color:#22c55e;
    box-shadow:0 0 0 3px rgba(34,197,94,.25);
}

/* BUTTONS */
button{
    width:100%;
    padding:15px;
    margin-top:12px;
    border:none;
    border-radius:18px;
    font-weight:900;
    font-size:15px;
    cursor:pointer;
    transition:transform .15s ease, box-shadow .15s ease;
}

/* HEAD */
.headBtn{
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    box-shadow:0 14px 30px rgba(34,197,94,.45);
}
.headBtn:active{
    transform:scale(.97);
}

/* TAIL */
.tailBtn{
    background:linear-gradient(135deg,#3b82f6,#2563eb);
    color:#fff;
    box-shadow:0 14px 30px rgba(59,130,246,.45);
}
.tailBtn:active{
    transform:scale(.97);
}

/* MESSAGES */
.msg{
    text-align:center;
    margin-top:14px;
    font-weight:900;
    font-size:14px;
}
.win{color:#22c55e}
.err{color:#ef4444}

/* BACK LINK */
a{
    display:block;
    text-align:center;
    margin-top:18px;
    color:#9ca3af;
    text-decoration:none;
    font-weight:700;
}

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

<h3>🎲 Head / Tail</h3>
<div class="balance">🪙 Coins: <?=number_format($coins,2)?></div>

<div class="coin <?= htmlspecialchars($resultSide) ?>">
    <?= $resultSide ? strtoupper(htmlspecialchars($resultSide)) : 'COIN' ?>
</div>

<?php if (!empty($error)): ?>
    <div class="msg err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($resultMsg)): ?>
    <div class="msg <?= str_contains($resultMsg, 'WIN') ? 'win' : 'err' ?>">
        <?= htmlspecialchars($resultMsg) ?>
    </div>
<?php endif; ?>

<?php if($coins >= 0.1): ?>
<form method="post">
    <input name="bet" type="number" min="0.1" step="0.1" placeholder="Bet Coins" required>
    <button class="headBtn" name="choice" value="head">HEAD</button>
    <button class="tailBtn" name="choice" value="tail">TAIL</button>
</form>
<?php else: ?>
<div class="msg err">🔒 Not enough coins</div>
<?php endif; ?>

</div>

<div class="manual-nav">

    <a class="nav-btn" href="dashboard.php">
        <div class="icon">🏠</div>
        <span>Home</span>
    </a>

    <a class="nav-btn" href="earn.php">
        <div class="icon">💰</div>
        <span>Earn</span>
    </a>

    <a class="nav-btn center" href="purchase.php">
        <div class="icon">🛒</div>
        <span>Buy</span>
    </a>

    <!-- ✅ ACTIVE PAGE -->
    <a class="nav-btn active" href="headtail.php">
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
