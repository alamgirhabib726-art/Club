<?php
/**
 * ============================================
 * UC CLOSE POSITION API
 * ============================================
 */

session_start();
require_once __DIR__ . "/../../db.php";

/* ================= AUTH ================= */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$pid = (int)($_POST['position_id'] ?? 0);

if ($pid <= 0) {
    die("INVALID POSITION");
}

/* ================= FETCH POSITION ================= */
$stmt = $db->prepare("
    SELECT *
    FROM uc_positions
    WHERE id = ?
      AND user_id = ?
      AND status = 'open'
    LIMIT 1
");
$stmt->execute([$pid, $uid]);
$pos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pos) {
    die("POSITION NOT FOUND");
}

/* ================= CURRENT PRICE ================= */
$price = (float)$db->query("
    SELECT price
    FROM uc_market
    ORDER BY id DESC
    LIMIT 1
")->fetchColumn();

if ($price <= 0) {
    die("MARKET ERROR");
}

/* ================= PNL CALC ================= */
if ($pos['side'] === 'long') {
    $pnl = ($price - $pos['entry_price']) * $pos['size'];
} else {
    $pnl = ($pos['entry_price'] - $price) * $pos['size'];
}

/* MAX LOSS = margin */
$pnl = max(-$pos['margin'], $pnl);

/* ================= LIQUIDITY ACCOUNT ================= */
$liqId = $db->query("
    SELECT id FROM users WHERE role='liquidity' LIMIT 1
")->fetchColumn();

if (!$liqId) {
    die("LIQUIDITY MISSING");
}

/* ================= TRANSACTION ================= */
$db->beginTransaction();

try {

    /* CLOSE POSITION */
    $db->prepare("
        UPDATE uc_positions
        SET
            status = 'closed',
            exit_price = ?,
            pnl = ?,
            closed_at = NOW()
        WHERE id = ?
    ")->execute([$price, $pnl, $pid]);

    /* USER SETTLEMENT */
    if ($pnl > 0) {
        // user profit from liquidity
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id = ?
        ")->execute([$pos['margin'] + $pnl, $uid]);

        $db->prepare("
            UPDATE users
            SET coins = coins - ?
            WHERE id = ?
        ")->execute([$pnl, $liqId]);

    } else {
        // user loss goes to liquidity
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id = ?
        ")->execute([$pos['margin'] + $pnl, $liqId]);
    }

    /* USER HISTORY */
    $db->prepare("
        INSERT INTO coin_history
        (user_id, amount, type, reference, created_at)
        VALUES (?, ?, 'trade_close', 'UC Trade Closed', NOW())
    ")->execute([$uid, $pnl]);

    /* LIQUIDITY HISTORY */
    $db->prepare("
        INSERT INTO coin_history
        (user_id, amount, type, reference, created_at)
        VALUES (?, ?, 'liquidity_pnl', 'UC Trade PnL', NOW())
    ")->execute([$liqId, -$pnl]);

    $db->commit();

    echo "POSITION CLOSED";

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo "FAILED";
}
