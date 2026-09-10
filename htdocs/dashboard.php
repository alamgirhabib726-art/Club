<?php
/**
 * UNMOOR CLUB - MAIN USER DASHBOARD
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* ================= LOGIN & AUTH ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, status, apply_status, coins, balance,
           coin_cycle_start, last_coin_cut, role, photo
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= SECURITY ================= */
if (!$user || $user['status'] === 'banned') {
    session_destroy();
    header("Location: login.php");
    exit;
}

/* ONLY APPROVED USERS */
if ($user['status'] !== 'active' && $user['status'] !== 'premium') {
    if (($user['apply_status'] ?? '') === 'pending') {
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
        $realCut = max(0, min($currentCoins, $pendingCuts));
        $newCutDays = $actualCuts + $pendingCuts;

        $db->beginTransaction();
        try {
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

            if ($realCut > 0) {
                $systemId = (int)$db->query("
                    SELECT id FROM users WHERE role='system' LIMIT 1
                ")->fetchColumn();

                if ($systemId) {
                    $db->prepare("
                        UPDATE users
                        SET coins = coins + ?
                        WHERE id = ?
                    ")->execute([$realCut, $systemId]);

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

                    $db->prepare("
                        INSERT INTO system_ledger
                            (type, amount, source, reference)
                        VALUES
                            ('coin_cut', ?, 'auto_cycle', ?)
                    ")->execute([
                        $realCut,
                        'User ID: ' . $user['id']
                    ]);
                }
            }

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
            $user['coins'] -= $pendingCuts;

        } catch (Exception $e) {
            $db->rollBack();
            error_log("AUTO CUT FAILED: " . $e->getMessage());
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
$isDebt = ((float)$user['coins'] < 0);
$canUseFeatures = !$isDebt;

/* ================= CLUB FUND ================= */
$clubFund = (float)$db->query("
    SELECT COALESCE(coins,0)
    FROM users
    WHERE role = 'system'
    LIMIT 1
")->fetchColumn();

/* ================= NOTICE BOARD ================= */
$notices = [];
try {
    $stmt = $db->prepare("
        SELECT message, created_at
        FROM notices
        ORDER BY id DESC
        LIMIT 5
    ");
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {}

$isVip = ($user['status'] === 'premium' || $user['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <!-- USER TOPBAR CARD -->
        <div class="card" style="background: linear-gradient(135deg, #111827, #020617); padding: 16px; margin-bottom: 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--accent-gold); overflow: hidden; background: var(--bg-dark); flex-shrink: 0;">
                        <?php if (!empty($user['photo'])): ?>
                            <img src="uploads/avatars/<?= htmlspecialchars($user['photo']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 20px;">👤</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight: 800; font-size: 15px; color: #ffffff;">
                            <?= htmlspecialchars($user['name']) ?>
                        </div>
                        <div style="font-size: 11.5px; color: var(--text-muted);">
                            <?php if ($isVip): ?>
                                <span style="color: var(--accent-gold); font-weight: 700;">💎 VIP Premium</span>
                            <?php else: ?>
                                <span style="color: var(--accent-green); font-weight: 700;">Verified Member</span>
                            <?php endif; ?>
                            • ID #<?= $user['id'] ?>
                        </div>
                    </div>
                </div>

                <div style="text-align: right;">
                    <div style="display: inline-block; padding: 6px 12px; border-radius: 999px; font-weight: 900; font-size: 14.5px; background: <?= $isDebt ? 'rgba(239,68,68,0.15)' : 'rgba(34,197,94,0.15)' ?>; color: <?= $isDebt ? 'var(--accent-red)' : 'var(--accent-green)' ?>; border: 1px solid <?= $isDebt ? 'rgba(239,68,68,0.3)' : 'rgba(34,197,94,0.3)' ?>;">
                        🪙 <?= number_format($user['coins'], 2) ?>
                    </div>
                    <div style="font-size: 10.5px; color: var(--text-dim); margin-top: 4px;">
                        Cycle: <?= $hours ?>h <?= $minutes ?>m left
                    </div>
                </div>
            </div>
        </div>

        <!-- DEBT WARNING BANNER IF NEGATIVE COINS -->
        <?php if ($isDebt): ?>
            <div class="alert alert-danger" style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <strong>⚠️ Negative Balance Warning</strong>
                    <div style="font-size: 12px; margin-top: 2px;">Your features are temporarily locked. Please deposit to restore access.</div>
                </div>
                <a href="deposit.php" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px; white-space: nowrap;">
                    Deposit
                </a>
            </div>
        <?php endif; ?>

        <!-- TOTAL CLUB FUND HIGHLIGHT CARD -->
        <div class="card" style="background: linear-gradient(135deg, #f472b6, #ec4899); color: #3b0a24; text-align: center; padding: 20px 14px; box-shadow: 0 14px 34px rgba(236, 72, 153, 0.35); border: none;">
            <div style="font-size: 12.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9;">🏦 Total Club Reserve Fund</div>
            <div style="font-size: 28px; font-weight: 900; margin-top: 4px; letter-spacing: 0.5px;">
                🪙 <?= number_format($clubFund, 2) ?>
            </div>
        </div>

        <!-- PRIMARY ACTION HUBS -->
        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                🏦 Account & Financial Hub
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                <a href="deposit.php" style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 6px; text-align: center; text-decoration: none; color: #ffffff; display: flex; flex-direction: column; align-items: center; gap: 6px; transition: transform 0.15s;">
                    <span style="font-size: 22px;">💳</span>
                    <span style="font-size: 13px; font-weight: 800;">Deposit</span>
                </a>

                <a href="transfer.php" style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 6px; text-align: center; text-decoration: none; color: #ffffff; display: flex; flex-direction: column; align-items: center; gap: 6px; <?= $canUseFeatures ? '' : 'opacity: 0.4; pointer-events: none;' ?>">
                    <span style="font-size: 22px;">🔄</span>
                    <span style="font-size: 13px; font-weight: 800;">Transfer</span>
                </a>

                <a href="history.php" style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 6px; text-align: center; text-decoration: none; color: #ffffff; display: flex; flex-direction: column; align-items: center; gap: 6px;">
                    <span style="font-size: 22px;">📜</span>
                    <span style="font-size: 13px; font-weight: 800;">History</span>
                </a>
            </div>
        </div>

        <!-- CLUB ACTIVITIES & GAMING -->
        <div class="card">
            <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                🎮 Club Activities & Events
            </h3>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 12px;">
                <a href="headtail.php" style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 6px; text-align: center; text-decoration: none; color: #ffffff; display: flex; flex-direction: column; align-items: center; gap: 6px; <?= $canUseFeatures ? '' : 'opacity: 0.4; pointer-events: none;' ?>">
                    <span style="font-size: 22px;">🎲</span>
                    <span style="font-size: 13px; font-weight: 800;">Head/Tail</span>
                </a>

                <a href="events.php" style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 6px; text-align: center; text-decoration: none; color: #ffffff; display: flex; flex-direction: column; align-items: center; gap: 6px; <?= $canUseFeatures ? '' : 'opacity: 0.4; pointer-events: none;' ?>">
                    <span style="font-size: 22px;">🎉</span>
                    <span style="font-size: 13px; font-weight: 800;">Events</span>
                </a>

                <a href="trade/chart.php" style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 6px; text-align: center; text-decoration: none; color: #ffffff; display: flex; flex-direction: column; align-items: center; gap: 6px; <?= $canUseFeatures ? '' : 'opacity: 0.4; pointer-events: none;' ?>">
                    <span style="font-size: 22px;">📈</span>
                    <span style="font-size: 13px; font-weight: 800;">Trade</span>
                </a>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <a href="purchase.php" class="btn btn-gold btn-block" style="text-align: center;">
                    🛒 Club Store
                </a>
                
                <a href="donation.php" class="btn btn-primary btn-block" style="text-align: center; <?= $canUseFeatures ? '' : 'opacity: 0.4; pointer-events: none;' ?>">
                    💚 Donate & Support
                </a>
            </div>
        </div>

        <!-- NOTICE BOARD -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="font-size: 14px; font-weight: 800; color: #ffffff; margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">
                    📢 Notice Board
                </h3>
                <a href="notices.php" style="font-size: 12px; color: var(--accent-gold); text-decoration: none; font-weight: 700;">View All ›</a>
            </div>

            <?php if ($isDebt): ?>
                <div style="padding: 24px; text-align: center; color: var(--text-muted); font-weight: 700;">
                    🔒 Notice Board Locked<br>
                    <small style="color: var(--text-dim);">Clear your negative coin balance to unlock.</small>
                </div>
            <?php else: ?>
                <?php if ($notices): ?>
                    <?php foreach ($notices as $n): ?>
                        <div style="padding: 10px 0; border-bottom: 1px dashed var(--border-color);">
                            <div style="font-size: 13.5px; line-height: 1.5; color: var(--text-main); white-space: pre-wrap;">
                                <?= htmlspecialchars($n['message']) ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-dim); margin-top: 4px;">
                                ⏱️ <?= date("d M Y, h:i A", strtotime($n['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: var(--text-dim); text-align: center; padding: 18px 0; font-size: 13px;">
                        No notices posted yet.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- SUPPORT WIDGET -->
        <?= render_support_widget() ?>

    </div>

    <!-- GLOBAL BOTTOM NAVIGATION -->
    <?php require_once __DIR__ . "/bottom_nav.php"; ?>

    <script src="assets/app.js"></script>
</body>
</html>
