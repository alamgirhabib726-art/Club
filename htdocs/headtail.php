<?php
/**
 * UNMOOR CLUB - HEAD / TAIL MINI GAME & CASINO ENGINE
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";

/* ================= LOGIN ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$balances = get_user_balances($db, $uid);

if (($balances['status'] !== 'active' && $balances['status'] !== 'premium') || $balances['apply_status'] !== 'approved' || $balances['role'] === 'system') {
    die("ACCESS DENIED");
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

$resultMsg  = $_SESSION['game_msg']  ?? '';
$resultSide = $_SESSION['game_side'] ?? '';
$error      = $_SESSION['game_err']  ?? '';
unset($_SESSION['game_msg'], $_SESSION['game_side'], $_SESSION['game_err']);

/* ================= HANDLE BET PLAY ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $bet    = round((float)($_POST['bet'] ?? 0), 2);
    $choice = strtolower(trim($_POST['choice'] ?? ''));

    if ($bet < 0.1) {
        $err = "Minimum bet is 0.10 coins.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }
        $_SESSION['game_err'] = $err;
        header("Location: headtail.php");
        exit;
    }

    if ($bet > $balances['coins']) {
        $err = "Insufficient Available Balance (🪙 " . number_format($balances['coins'], 2) . "). Locked coins cannot be wagered.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }
        $_SESSION['game_err'] = $err;
        header("Location: headtail.php");
        exit;
    }

    if (!in_array($choice, ['head', 'tail'], true)) {
        $err = "Invalid selection. Please choose Head or Tail.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }
        $_SESSION['game_err'] = $err;
        header("Location: headtail.php");
        exit;
    }

    /* SYSTEM USER (TREASURY) */
    $systemId = (int)$db->query("SELECT id FROM users WHERE role='system' LIMIT 1")->fetchColumn();
    if (!$systemId) {
        $err = "System treasury vault not initialized.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }
        $_SESSION['game_err'] = $err;
        header("Location: headtail.php");
        exit;
    }

    /* USER GAME CONTROL & STREAK LOGIC */
    $ctrlStmt = $db->prepare("SELECT user_id, last_bet, plays, last_play FROM game_control WHERE user_id = ?");
    $ctrlStmt->execute([$uid]);
    $ctrl = $ctrlStmt->fetch(PDO::FETCH_ASSOC);

    $lastBet = (float)($ctrl['last_bet'] ?? 0);
    $plays   = (int)($ctrl['plays'] ?? 0);

    if (!empty($ctrl['last_play']) && (time() - strtotime($ctrl['last_play']) > 43200)) {
        $plays = 0;
        $lastBet = 0;
    }

    /* WIN PROBABILITY ALGORITHM */
    $forceLoss = ($lastBet > 0 && $bet >= ($lastBet * 2));
    if ($forceLoss) {
        $isWin = false;
    } else {
        $winChance = match (true) {
            $plays < 10  => 45,
            $plays < 30  => 35,
            $plays < 80  => 25,
            default      => 18
        };
        $isWin = (random_int(1, 100) <= $winChance);
    }

    $actualOutcome = $isWin ? $choice : ($choice === 'head' ? 'tail' : 'head');
    $payoutRate = 0.80; // 80% profit on win
    $profit = round($bet * $payoutRate, 2);

    /* ATOMIC GAME TRANSACTION */
    $db->beginTransaction();
    try {
        // 1. Update Game Control
        if ($ctrl) {
            $db->prepare("
                UPDATE game_control
                SET last_bet = ?, plays = plays + 1, last_play = datetime('now')
                WHERE user_id = ?
            ")->execute([$bet, $uid]);
        } else {
            $db->prepare("
                INSERT INTO game_control (user_id, last_bet, plays, last_play)
                VALUES (?, ?, 1, datetime('now'))
            ")->execute([$uid, $bet]);
        }

        if ($isWin) {
            // Player Won
            $db->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$profit, $uid]);
            $db->prepare("UPDATE users SET coins = coins - ? WHERE id = ?")->execute([$profit, $systemId]);

            // Insert Bet Record
            $db->prepare("
                INSERT INTO headtail_bets (user_id, choice, result, bet_amount, profit, created_at)
                VALUES (?, ?, ?, ?, ?, datetime('now'))
            ")->execute([$uid, $choice, $actualOutcome, $bet, $profit]);

            // User Coin History
            $db->prepare("
                INSERT INTO coin_history (user_id, amount, type, reference, source_name, created_at)
                VALUES (?, ?, 'game_win', ?, 'Head / Tail Mini Game', datetime('now'))
            ")->execute([$uid, $profit, "Wager: 🪙{$bet}, Choice: {$choice}, Result: {$actualOutcome}"]);

            // System Ledger
            $db->prepare("
                INSERT INTO system_ledger (type, amount, source, reference, created_at)
                VALUES ('game_payout', ?, 'headtail', ?, datetime('now'))
            ")->execute([-$profit, "User #{$uid} won with {$choice}"]);

            $balances['coins'] += $profit;
            $balances['total_coins'] += $profit;
            $msg = "🎉 WIN! +🪙 " . number_format($profit, 2) . " UC";
        } else {
            // Player Lost
            $db->prepare("UPDATE users SET coins = coins - ? WHERE id = ? AND coins >= ?")->execute([$bet, $uid, $bet]);
            $db->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$bet, $systemId]);

            // Insert Bet Record
            $db->prepare("
                INSERT INTO headtail_bets (user_id, choice, result, bet_amount, profit, created_at)
                VALUES (?, ?, ?, ?, ?, datetime('now'))
            ")->execute([$uid, $choice, $actualOutcome, $bet, -$bet]);

            // User Coin History
            $db->prepare("
                INSERT INTO coin_history (user_id, amount, type, reference, source_name, created_at)
                VALUES (?, ?, 'game_loss', ?, 'Head / Tail Mini Game', datetime('now'))
            ")->execute([$uid, -$bet, "Wager: 🪙{$bet}, Choice: {$choice}, Result: {$actualOutcome}"]);

            // System Ledger
            $db->prepare("
                INSERT INTO system_ledger (type, amount, source, reference, created_at)
                VALUES ('game_income', ?, 'headtail', ?, datetime('now'))
            ")->execute([$bet, "User #{$uid} lost with {$choice}"]);

            $balances['coins'] -= $bet;
            $balances['total_coins'] -= $bet;
            $msg = "❌ LOSS -🪙 " . number_format($bet, 2) . " UC";
        }

        $db->commit();

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'is_win' => $isWin,
                'choice' => $choice,
                'outcome' => $actualOutcome,
                'bet' => $bet,
                'profit' => $isWin ? $profit : -$bet,
                'balances' => $balances,
                'message' => $msg
            ]);
            exit;
        }

        $_SESSION['game_msg']  = $msg;
        $_SESSION['game_side'] = $actualOutcome;

    } catch (Throwable $e) {
        $db->rollBack();
        $err = "Game transaction failed. Please retry.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }
        $_SESSION['game_err'] = $err;
    }

    header("Location: headtail.php");
    exit;
}

/* FETCH RECENT BET HISTORY FOR USER */
$myBets = [];
try {
    $histStmt = $db->prepare("
        SELECT choice, result, bet_amount, profit, created_at
        FROM headtail_bets
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 10
    ");
    $histStmt->execute([$uid]);
    $myBets = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Head / Tail Game • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .coin-stage {
            perspective: 800px;
            margin: 20px auto;
            width: 130px;
            height: 130px;
        }
        .coin-disc {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 900;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), inset 0 2px 4px rgba(255, 255, 255, 0.4);
            cursor: pointer;
            user-select: none;
        }
        .coin-disc.head-theme {
            background: linear-gradient(135deg, #fde047, #d97706);
            color: #451a03;
            border: 4px solid #fef08a;
        }
        .coin-disc.tail-theme {
            background: linear-gradient(135deg, #60a5fa, #1d4ed8);
            color: #ffffff;
            border: 4px solid #bfdbfe;
        }
        .coin-disc.spinning {
            animation: coinFlipAnim 1.2s cubic-bezier(0.25, 1, 0.5, 1) infinite;
        }
        @keyframes coinFlipAnim {
            0% { transform: rotateY(0deg) scale(1); }
            50% { transform: rotateY(900deg) scale(1.18); }
            100% { transform: rotateY(1800deg) scale(1); }
        }
        .quick-chip {
            background: var(--bg-dark);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
        }
        .quick-chip:hover, .quick-chip.active {
            border-color: var(--accent-gold);
            color: var(--accent-gold);
            background: rgba(250, 204, 21, 0.1);
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Head / Tail Game", "/dashboard.php") ?>

        <!-- BALANCE OVERVIEW CARD -->
        <div id="balanceSection">
            <?= render_balance_card($balances, false) ?>
        </div>

        <div class="card" style="text-align: center; position: relative;">
            
            <div style="font-size: 11.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px;">
                Instant Mini-Casino
            </div>

            <!-- COIN VISUAL ELEMENT -->
            <div class="coin-stage">
                <div id="coinDisc" class="coin-disc <?= $resultSide === 'tail' ? 'tail-theme' : 'head-theme' ?>">
                    <span id="coinText"><?= $resultSide ? strtoupper(htmlspecialchars($resultSide)) : '🎲 COIN' ?></span>
                </div>
            </div>

            <div id="gameStatusAlert" style="min-height: 48px; margin-bottom: 14px;">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" style="margin: 0; font-size: 13.5px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php elseif (!empty($resultMsg)): ?>
                    <div class="alert <?= str_contains($resultMsg, 'WIN') ? 'alert-success' : 'alert-danger' ?>" style="margin: 0; font-size: 15px; font-weight: 900;">
                        <?= htmlspecialchars($resultMsg) ?>
                    </div>
                <?php else: ?>
                    <div style="font-size: 13px; color: var(--text-muted); padding: 8px 0;">
                        Select your side &amp; enter wager amount to flip.
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($balances['coins'] >= 0.1): ?>
                <form id="gameForm" method="post">
                    <div class="form-group" style="text-align: left; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label" style="margin-bottom: 0;">Wager Amount (Coins)</label>
                            <span style="font-size: 11px; color: var(--accent-gold); font-weight: 700;">Payout: +80% Profit</span>
                        </div>
                        <input id="betInput" name="bet" type="number" min="0.1" max="<?= (float)$balances['coins'] ?>" step="0.1" class="form-control" placeholder="e.g. 5.0" value="1.0" required style="font-size: 16px; font-weight: 800;">
                    </div>

                    <!-- QUICK BET CHIPS -->
                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; margin-bottom: 16px;">
                        <button type="button" class="quick-chip" onclick="setBet(0.5)">0.5</button>
                        <button type="button" class="quick-chip" onclick="setBet(1.0)">1.0</button>
                        <button type="button" class="quick-chip" onclick="setBet(5.0)">5.0</button>
                        <button type="button" class="quick-chip" onclick="setBet(10.0)">10.0</button>
                        <button type="button" class="quick-chip" onclick="setBet(25.0)">25.0</button>
                        <button type="button" class="quick-chip" onclick="setBet(<?= (float)$balances['coins'] ?>)">MAX</button>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <button type="button" id="btnHead" class="btn btn-gold" style="padding: 14px; font-size: 16px; font-weight: 900;" onclick="submitBet('head')">
                            👑 HEAD
                        </button>
                        <button type="button" id="btnTail" class="btn btn-primary" style="padding: 14px; font-size: 16px; font-weight: 900; background: #2563eb; border-color: #3b82f6;" onclick="submitBet('tail')">
                            🪙 TAIL
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger" style="margin-top: 14px;">
                    🔒 Insufficient Available Coins to place bets. Locked balance cannot be used.
                    <div style="margin-top: 8px;">
                        <a href="deposit.php" class="btn btn-gold btn-sm" style="text-decoration: none;">Deposit Now</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RECENT BET HISTORY -->
        <div class="card" style="margin-top: 14px;">
            <h3 style="font-size: 13.5px; font-weight: 800; color: #ffffff; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                📜 My Recent Bets
            </h3>

            <div id="recentBetsList">
                <?php if (!empty($myBets)): ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($myBets as $b): ?>
                            <?php $isWin = ($b['profit'] > 0); ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: var(--bg-dark); border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 12.5px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span><?= $b['result'] === 'head' ? '👑' : '🪙' ?></span>
                                    <div>
                                        <strong style="color: #ffffff; text-transform: capitalize;"><?= htmlspecialchars($b['choice']) ?></strong>
                                        <span style="color: var(--text-dim); font-size: 11px;">(Outcome: <?= htmlspecialchars($b['result']) ?>)</span>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <strong style="color: <?= $isWin ? '#22c55e' : '#ef4444' ?>;">
                                        <?= $isWin ? '+' : '' ?>🪙 <?= number_format($b['profit'], 2) ?>
                                    </strong>
                                    <div style="font-size: 10.5px; color: var(--text-dim);"><?= date("h:i A", strtotime($b['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 18px 0; font-size: 12.5px;">
                        No games played recently.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php 
    $currentPage = 'headtail';
    require_once __DIR__ . "/bottom_nav.php"; 
    ?>

    <script>
    let isFlipping = false;
    let userAvailableBalance = <?= (float)$balances['coins'] ?>;

    function setBet(val) {
        if (isFlipping) return;
        const input = document.getElementById('betInput');
        if (input) {
            input.value = Math.min(val, userAvailableBalance).toFixed(1);
        }
    }

    function submitBet(choice) {
        if (isFlipping) return;

        const input = document.getElementById('betInput');
        const bet = parseFloat(input.value);

        if (isNaN(bet) || bet < 0.1) {
            alert('Please enter a valid bet amount of at least 0.10 coins.');
            return;
        }

        if (bet > userAvailableBalance) {
            alert('Insufficient available balance. You have 🪙 ' + userAvailableBalance.toFixed(2));
            return;
        }

        isFlipping = true;
        document.getElementById('btnHead').disabled = true;
        document.getElementById('btnTail').disabled = true;

        const coin = document.getElementById('coinDisc');
        const coinText = document.getElementById('coinText');
        const statusEl = document.getElementById('gameStatusAlert');

        coin.classList.add('spinning');
        coinText.innerText = '🌀';
        statusEl.innerHTML = '<div style="color: #fbbf24; font-weight: 800; font-size: 14px;">Flipping coin for 🪙 ' + bet.toFixed(2) + '...</div>';

        const formData = new FormData();
        formData.append('bet', bet);
        formData.append('choice', choice);
        formData.append('ajax', '1');

        fetch('headtail.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            setTimeout(() => {
                isFlipping = false;
                coin.classList.remove('spinning');
                document.getElementById('btnHead').disabled = false;
                document.getElementById('btnTail').disabled = false;

                if (data.success) {
                    userAvailableBalance = data.balances.coins;
                    const outcome = data.outcome;
                    
                    coin.className = 'coin-disc ' + (outcome === 'tail' ? 'tail-theme' : 'head-theme');
                    coinText.innerText = outcome.toUpperCase();

                    if (data.is_win) {
                        statusEl.innerHTML = '<div class="alert alert-success" style="margin: 0; font-size: 15px; font-weight: 900;">' + data.message + '</div>';
                    } else {
                        statusEl.innerHTML = '<div class="alert alert-danger" style="margin: 0; font-size: 15px; font-weight: 900;">' + data.message + '</div>';
                    }

                    // Prepend to recent list
                    const list = document.getElementById('recentBetsList');
                    if (list) {
                        const row = document.createElement('div');
                        row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: var(--bg-dark); border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 12.5px; margin-bottom: 8px;';
                        const isWin = data.is_win;
                        row.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span>${outcome === 'head' ? '👑' : '🪙'}</span>
                                <div>
                                    <strong style="color: #ffffff; text-transform: capitalize;">${choice}</strong>
                                    <span style="color: var(--text-dim); font-size: 11px;">(Outcome: ${outcome})</span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <strong style="color: ${isWin ? '#22c55e' : '#ef4444'};">
                                    ${isWin ? '+' : ''}🪙 ${Number(data.profit).toFixed(2)}
                                </strong>
                                <div style="font-size: 10.5px; color: var(--text-dim);">Just now</div>
                            </div>
                        `;
                        list.prepend(row);
                    }
                } else {
                    coin.className = 'coin-disc head-theme';
                    coinText.innerText = '🎲 COIN';
                    statusEl.innerHTML = '<div class="alert alert-danger" style="margin: 0;">' + (data.error || 'Game error occurred.') + '</div>';
                }
            }, 700);
        })
        .catch(err => {
            setTimeout(() => {
                isFlipping = false;
                coin.classList.remove('spinning');
                document.getElementById('btnHead').disabled = false;
                document.getElementById('btnTail').disabled = false;
                statusEl.innerHTML = '<div class="alert alert-danger" style="margin: 0;">Network error. Please try again.</div>';
            }, 700);
        });
    }
    </script>
</body>
</html>
