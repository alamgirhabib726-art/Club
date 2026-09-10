<?php
session_start();
require_once __DIR__ . "/db.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, status, apply_status, coins,
           coin_cycle_start, last_coin_cut, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= SECURITY ================= */
if (!$user || $user['status'] === 'banned') {
    session_destroy();
    die("ACCESS DENIED");
}

/* ONLY APPROVED USERS */
if ($user['status'] !== 'active' || $user['apply_status'] !== 'approved') {
    if ($user['apply_status'] === 'pending') {
        header("Location: application_pending.php");
    } else {
        header("Location: apply_payment.php");
    }
    exit;
}

/* ================= COIN AUTO CUT ENGINE ================= */
$now = time();

if ($user['role'] === 'user' && !empty($user['coin_cycle_start'])) {

    $cycleStart = strtotime($user['coin_cycle_start']);

    $lastCutTs = $user['last_coin_cut']
        ? strtotime($user['last_coin_cut'])
        : $cycleStart;

    $daysPassed  = floor(($now - $cycleStart) / 86400);
    $actualCuts  = floor(($lastCutTs - $cycleStart) / 86400);
    $pendingCuts = $daysPassed - $actualCuts;

    if ($pendingCuts > 0) {

        $currentCoins = (float)$user['coins'];

        // system only receives REAL coins
        $realCut = max(0, min($currentCoins, $pendingCuts));

        $newCutDays = $actualCuts + $pendingCuts;

        $db->beginTransaction();
        try {

            /* ========= CUT USER (CAN GO NEGATIVE) ========= */
            $cutDate = date('Y-m-d H:i:s', strtotime($user['coin_cycle_start'] . " + " . (int)$newCutDays . " days"));
            $db->prepare("
                UPDATE users
                SET coins = coins - ?,
                    last_coin_cut = ?
                WHERE id = ?
            ")->execute([
                $pendingCuts,
                $cutDate,
                $user['id']
            ]);

            /* ========= SYSTEM CREDIT (ONLY REAL COINS) ========= */
            if ($realCut > 0) {

                $systemId = (int)$db->query("
                    SELECT id FROM users WHERE role='system' LIMIT 1
                ")->fetchColumn();

                // add coins to system
                $db->prepare("
                    UPDATE users
                    SET coins = coins + ?
                    WHERE id = ?
                ")->execute([$realCut, $systemId]);

                // system coin history (FROM USER)
                $db->prepare("
                    INSERT INTO coin_history
                        (user_id, amount, type,
                         source_user_id, source_name, source_number)
                    VALUES
                        (?, ?, 'credit', ?, ?, ?)
                ")->execute([
                    $systemId,
                    $realCut,
                    $user['id'],
                    $user['name'],
                    $user['phone']
                ]);

                // system ledger (audit)
                $db->prepare("
                    INSERT INTO system_ledger
                        (type, amount, source, reference)
                    VALUES
                        ('coin_cut', ?, 'auto_cycle', ?)
                ")->execute([
                    $realCut,
                    'User ID: '.$user['id']
                ]);
            }

            /* ========= USER HISTORY (FULL CUT) ========= */
            $db->prepare("
                INSERT INTO coin_history
                    (user_id, amount, type,
                     source_user_id, source_name, source_number)
                VALUES
                    (?, ?, 'debit', ?, ?, ?)
            ")->execute([
                $user['id'],
                -$pendingCuts,
                $user['id'],
                $user['name'],
                $user['phone']
            ]);

            $db->commit();

            // update local balance
            $user['coins'] -= $pendingCuts;

        } catch (Exception $e) {
            $db->rollBack();
            error_log("AUTO CUT FAILED: ".$e->getMessage());
        }
    }
}

/* ================= NEXT CUT TIMER ================= */
$hours = $minutes = 0;

if ($user['role'] === 'user' && !empty($user['coin_cycle_start'])) {
    $start = strtotime($user['coin_cycle_start']);
    $nextCutTs = $start + (floor(($now - $start) / 86400) + 1) * 86400;
    $remain  = max(0, $nextCutTs - $now);
    $hours   = floor($remain / 3600);
    $minutes = floor(($remain % 3600) / 60);
}

/* ================= ACCESS RULES ================= */
$isDebt = ($user['coins'] < 0);

/* user can use features ONLY if not in debt */
$canUseFeatures = !$isDebt;

/* ================= CLUB FUND ================= */
$clubFund = (float)$db->query("
    SELECT COALESCE(coins,0)
    FROM users
    WHERE role = 'system'
    LIMIT 1
")->fetchColumn();

/* ================= NOTICE BOARD ================= */
$stmt = $db->prepare("
    SELECT message, created_at
    FROM notices
    ORDER BY id DESC
    LIMIT 5
");
$stmt->execute();
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#0b0f19;
    --card:#121826;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --gold1:#fde68a;
    --gold2:#fbbf24;
    --green1:#22c55e;
    --green2:#16a34a;
    --danger:#ef4444;
    --pink1:#f472b6;
    --pink2:#ec4899;
}
*{box-sizing:border-box;font-family:system-ui}
body{margin:0;background:var(--bg);color:var(--text)}
.wrapper{
    max-width:480px;
    margin:auto;
    padding:16px;
    padding-bottom:90px; /* space for bottom nav */
}

.topbar{
    background:linear-gradient(135deg,#111827,#020617);
    border:1px solid var(--border);
    border-radius:22px;
    padding:18px;
    display:flex;
    justify-content:space-between;
}
.coin{
    background:rgba(34,197,94,.15);
    color:#22c55e;
    padding:6px 14px;
    border-radius:999px;
    font-weight:800;
}
.coin.negative{
    background:rgba(239,68,68,.15);
    color:#ef4444;
}
.timer{font-size:12px;color:var(--muted)}

.card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:22px;
    padding:18px;
    margin-top:16px;
}

/* ===== CLUB FUND CARD ===== */
.club-fund{
    background:linear-gradient(135deg,var(--pink1),var(--pink2));
    color:#3b0a24;
    text-align:center;
    padding:20px;
    border-radius:22px;
    font-weight:900;
    box-shadow:0 20px 50px rgba(236,72,153,.45);
}
.club-fund span{
    display:block;
    font-size:13px;
    font-weight:700;
    opacity:.9;
}
.club-fund strong{
    font-size:26px;
    letter-spacing:.5px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px
}
.grid a{
    padding:14px 6px;
    border-radius:16px;
    text-align:center;
    font-weight:800;
    text-decoration:none;
    border:1px solid var(--border);
    color:var(--text)
}

.action{
    height:62px;
    border-radius:18px;
    display:flex;
    justify-content:center;
    align-items:center;
    font-weight:900;
    font-size:16px;
    text-decoration:none
}
.notice-text{
    white-space: pre-wrap;   /* 🔥 THIS IS THE KEY */
    line-height: 1.6;
    font-size: 14px;
    color: var(--text);
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


/* ===== PAGE SAFE SPACE ===== */
.wrapper{
    padding-bottom:160px;
}
/* PREVENT RANDOM GREEN — BUT EXCLUDE BUY */
.manual-nav .nav-btn:not(.active):not(.center) .icon{
    background:#020617;
    color:#e5e7eb;
    box-shadow:
        0 6px 14px rgba(0,0,0,.45);
}/* PREVENT RANDOM GREEN — BUT EXCLUDE BUY */
.manual-nav .nav-btn:not(.active):not(.center) .icon{
    background:#020617;
    color:#e5e7eb;
    box-shadow:
        0 6px 14px rgba(0,0,0,.45);
}

/* ONLY ACTIVE GETS GREEN */


.gold{background:linear-gradient(135deg,var(--gold1),var(--gold2));color:#422006}
.donation{background:linear-gradient(135deg,var(--green1),var(--green2));color:#022c22}
.logout{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff}

.notice{padding:12px 0;border-bottom:1px dashed var(--border)}
.notice:last-child{border-bottom:none}
.notice time{font-size:11px;color:var(--muted)}

.locked{opacity:.4;pointer-events:none}
</style>
</head>

<body>
<div class="wrapper">

<div class="topbar">
    <div>
        <strong><?=htmlspecialchars($user['name'])?></strong><br>
        <small style="color:var(--muted)">Approved Member</small>
    </div>
    <div>
        <div class="coin <?= $user['coins'] < 0 ? 'negative' : '' ?>">
            🪙 <?=number_format($user['coins'],2)?>
        </div>
        <div class="timer"><?= $hours ?>h <?= $minutes ?>m left</div>
    </div>
</div>

<!-- 🌸 CLUB FUND (DASHBOARD ONLY) -->
<div class="card club-fund">
    <span>🏦 Total Club Fund</span>
    <strong>🪙 <?= number_format($clubFund,2) ?></strong>
</div>

<div class="card">
    <h3>🏦 Account Center</h3>
    <div class="grid">
        <a href="deposit.php">Deposit</a>
        <a class="<?= $canUseFeatures?'':'locked' ?>" href="transfer.php">Transfer</a>
        <a href="history.php">History</a>
    </div>
</div>

<div class="card">
    <a class="action donation <?= $canUseFeatures?'':'locked' ?>" href="donation.php">
        💚 Donate & Support
    </a>
</div>

<div class="card">
    <h3>📢 Notice Board</h3>

    <?php if($isDebt): ?>
        <div style="
            padding:20px;
            text-align:center;
            color:var(--muted);
            font-weight:700;
        ">
            🔒 Notice Board Locked<br>
            <small>Clear your balance to unlock</small>
        </div>
    <?php else: ?>

        <?php if ($notices): foreach ($notices as $n): ?>
            <div class="notice">
                <div class="notice-text">
                    <?= htmlspecialchars($n['message']) ?>
                </div>
                <time><?= date("d M Y, h:i A", strtotime($n['created_at'])) ?></time>
            </div>
        <?php endforeach; else: ?>
            <p style="color:var(--muted)">No notices yet.</p>
        <?php endif; ?>

    <?php endif; ?>
</div>
<div class="card">
    <a class="action gold <?= $isDebt ? 'locked' : '' ?>" href="events.php">
        🎉 Events
    </a>
</div>

<div class="card">
    <a class="action gold <?= $isDebt ? 'locked' : '' ?>" href="/trade/chart.php">
        📈 Trade
    </a>
</div>
<!-- ===== MANUAL BOTTOM NAV ===== -->
<div class="manual-nav">
    <a class="nav-btn active" href="dashboard.php">
        <div class="icon">🏠</div>
        <span>Home</span>
    </a>
    
    <a class="nav-btn" href="purchase.php">
        <div class="icon">🛒</div>
        <span>Buy</span>
    </a>

<a class="nav-btn center" href="chat/indexx.php">
        <div class="icon">🗨️</div>
        <span>Chat</span>
    </a>
    
    <a class="nav-btn" href="headtail.php">
        <div class="icon">🎲</div>
        <span>Head/Tail</span>
    </a>

    <a class="nav-btn" href="account/account.php">
        <div class="icon">👤</div>
        <span>Account</span>
    </a>
</div>
</body>
</html>
