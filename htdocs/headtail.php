<?php
/**
 * UNMOOR CLUB - HEAD / TAIL GAME
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

/* ================= FETCH USER ================= */
$stmt = $db->prepare("
    SELECT id, name, phone, coins, status, apply_status, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || ($user['status'] !== 'active' && $user['status'] !== 'premium') || $user['apply_status'] !== 'approved' || $user['role'] === 'system') {
    die("ACCESS DENIED");
}

$coins = (float)$user['coins'];

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
        $_SESSION['game_err'] = "❌ Minimum bet is 0.1 coin";
        goto REDIRECT;
    }

    if ($bet > $coins) {
        $_SESSION['game_err'] = "❌ Insufficient coins balance";
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
                INSERT INTO coin_history (user_id, amount, type, created_at)
                VALUES (?, ?, 'game_win', NOW())
            ")->execute([$uid,$profit]);

            $_SESSION['game_msg']  = "🎉 WIN +🪙" . number_format($profit, 2);
            $_SESSION['game_side'] = $choice;

        } else {

            $db->prepare("UPDATE users SET coins = coins - ? WHERE id=?")
               ->execute([$bet,$uid]);

            $db->prepare("UPDATE users SET coins = coins + ? WHERE id=?")
               ->execute([$bet,$systemId]);

            $db->prepare("
                INSERT INTO coin_history (user_id, amount, type, created_at)
                VALUES (?, ?, 'game_loss', NOW())
            ")->execute([$uid,-$bet]);

            $_SESSION['game_msg']  = "❌ LOSS -🪙" . number_format($bet, 2);
            $_SESSION['game_side'] = ($choice === 'head') ? 'tail' : 'head';
        }

        $db->commit();

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['game_err'] = "❌ Game transaction failed";
    }

REDIRECT:
    unset($_SESSION['game_lock']);
    header("Location: headtail.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Head / Tail • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header("Head / Tail Game", "/dashboard.php") ?>

        <div class="card" style="text-align: center;">
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 800;">Available Coins</div>
            <div style="font-size: 26px; font-weight: 900; color: <?= $coins < 0 ? 'var(--accent-red)' : 'var(--accent-green)' ?>; margin-top: 4px;">
                🪙 <?= number_format($coins, 2) ?>
            </div>

            <!-- COIN VISUAL -->
            <div style="width: 120px; height: 120px; border-radius: 50%; margin: 20px auto; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 900; background: <?= $resultSide === 'tail' ? 'linear-gradient(135deg, #60a5fa, #2563eb)' : 'linear-gradient(135deg, #fde047, #eab308)' ?>; color: <?= $resultSide === 'tail' ? '#ffffff' : '#422006' ?>; box-shadow: 0 16px 36px rgba(0,0,0,0.5), inset 0 2px 4px rgba(255,255,255,0.4);">
                <?= $resultSide ? strtoupper(htmlspecialchars($resultSide)) : '🎲 COIN' ?>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 12px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($resultMsg)): ?>
                <div class="alert <?= str_contains($resultMsg, 'WIN') ? 'alert-success' : 'alert-danger' ?>" style="margin-bottom: 12px; font-size: 16px; font-weight: 900;">
                    <?= htmlspecialchars($resultMsg) ?>
                </div>
            <?php endif; ?>

            <?php if ($coins >= 0.1): ?>
                <form method="post">
                    <div class="form-group" style="text-align: left;">
                        <label class="form-label">Enter Bet Amount (Coins)</label>
                        <input name="bet" type="number" min="0.1" step="0.1" class="form-control" placeholder="e.g. 1.0" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px;">
                        <button type="submit" name="choice" value="head" class="btn btn-primary" style="padding: 14px; font-size: 15px;">
                            👑 HEAD
                        </button>
                        <button type="submit" name="choice" value="tail" class="btn btn-gold" style="padding: 14px; font-size: 15px;">
                            🪙 TAIL
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger" style="margin-top: 14px;">
                    🔒 Insufficient coins to place bets. Please deposit first.
                </div>
            <?php endif; ?>
        </div>

        <?= render_support_widget() ?>

    </div>

    <?php 
    $currentPage = 'headtail';
    require_once __DIR__ . "/bottom_nav.php"; 
    ?>
</body>
</html>
