<?php
/**
 * ============================================
 * UC CLOSE POSITION ENGINE
 * ============================================
 */

session_start();
require_once __DIR__ . "/../../db.php";

/* ================= INPUT ================= */
$positionId = (int)($_POST['position_id'] ?? 0);
$reason     = $_POST['reason'] ?? 'manual'; // manual | tp | sl | liquidation

if ($positionId <= 0) {
    die("INVALID POSITION");
}

/* ================= FETCH POSITION ================= */
$pos = $db->prepare("
    SELECT *
    FROM uc_positions
    WHERE id = ?
      AND status = 'open'
    LIMIT 1
");
$pos->execute([$positionId]);
$pos = $pos->fetch(PDO::FETCH_ASSOC);

if (!$pos) {
    die("POSITION NOT FOUND");
}

/* ================= PRICE ================= */
$price = (float)$db->query("
    SELECT price FROM uc_market
    ORDER BY id DESC
    LIMIT 1
")->fetchColumn();

if ($price <= 0) {
    die("MARKET ERROR");
}

/* ================= SYSTEM + LIQUIDITY ================= */
$system = $db->query("
    SELECT id, coins
    FROM users
    WHERE role='system'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$minLiquidity = (float)$db->query("
    SELECT min_balance
    FROM uc_liquidity
    ORDER BY id DESC
    LIMIT 1
")->fetchColumn();

if ($minLiquidity <= 0) {
    $minLiquidity = 50;
}

/* ================= PNL CALC ================= */
if ($pos['side'] === 'long') {
    $pnl = ($price - $pos['entry_price']) * $pos['size'];
} else {
    $pnl = ($pos['entry_price'] - $price) * $pos['size'];
}

$pnl = round($pnl, 2);

/* ================= USER PAYOUT ================= */
$userPayout = $pos['margin'] + $pnl;

/* ================= LOSS CAP ================= */
if ($userPayout < 0) {
    $userPayout = 0;
}

/* ================= PROFIT CAP (SYSTEM SAFE) ================= */
$maxPayable = $system['coins'] - $minLiquidity;

if ($pnl > 0 && $pnl > $maxPayable) {
    $pnl = $maxPayable;
    $userPayout = $pos['margin'] + $pnl;
}

/* ================= FINAL SETTLEMENT ================= */
$db->beginTransaction();
try {

    /* PAY USER */
    if ($userPayout > 0) {
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id = ?
        ")->execute([$userPayout, $pos['user_id']]);
    }

    /* SYSTEM BALANCE UPDATE */
    $systemDelta = -$pnl;

    if ($systemDelta != 0) {
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE role = 'system'
            LIMIT 1
        ")->execute([$systemDelta]);
    }

    /* CLOSE POSITION */
    $db->prepare("
        UPDATE uc_positions
        SET
            close_price = ?,
            pnl = ?,
            status = 'closed',
            close_reason = ?,
            closed_at = NOW()
        WHERE id = ?
    ")->execute([
        $price,
        $pnl,
        $reason,
        $positionId
    ]);

    $db->commit();
    echo "POSITION CLOSED";

} catch (Exception $e) {
    $db->rollBack();
    die("CLOSE FAILED");
}