<?php
/**
 * ============================================
 * UC OPEN POSITION API
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

/* ================= INPUT ================= */
$side     = $_POST['side'] ?? '';
$margin   = (float)($_POST['margin'] ?? 0);
$leverage = (int)($_POST['leverage'] ?? 0);

/* ================= RULES ================= */
if (!in_array($side, ['long','short'], true)) {
    die("INVALID SIDE");
}

if ($margin < 10) {
    die("MIN MARGIN 10");
}

if ($leverage < 5 || $leverage > 100) {
    die("INVALID LEVERAGE");
}

/* ================= USER ================= */
$stmt = $db->prepare("
    SELECT coins
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$userCoins = (float)$stmt->fetchColumn();

if ($userCoins < $margin) {
    die("INSUFFICIENT BALANCE");
}

/* ================= PRICE ================= */
$price = (float)$db->query("
    SELECT price
    FROM uc_market
    ORDER BY id DESC
    LIMIT 1
")->fetchColumn();

if ($price <= 0) {
    die("MARKET ERROR");
}

/* ================= FEE LOGIC ================= */
/*
 Fee model:
 - Base fee: 0.2%
 - Extra leverage fee: 0.02% per 1x above 5x
*/
$feePct = 0.002 + max(0, ($leverage - 5) * 0.0002);
$fee    = $margin * $feePct;

/* ================= SIZE ================= */
$positionValue = $margin * $leverage;
$size = $positionValue / $price;

/* ================= STOP LOSS ================= */
$slDistance = ($margin * 0.95) / $positionValue; // 95% loss cap

if ($side === 'long') {
    $stopLoss = $price * (1 - $slDistance);
} else {
    $stopLoss = $price * (1 + $slDistance);
}

/* ================= TRANSACTION ================= */
$db->beginTransaction();

try {

    /* LOCK USER COINS (MARGIN + FEE) */
    $db->prepare("
        UPDATE users
        SET coins = coins - ?
        WHERE id = ?
    ")->execute([$margin + $fee, $uid]);

    /* SYSTEM GETS FEE */
    $db->prepare("
        UPDATE users
        SET coins = coins + ?
        WHERE role = 'system'
        LIMIT 1
    ")->execute([$fee]);

    /* COIN HISTORY (USER) */
    $db->prepare("
        INSERT INTO coin_history
        (user_id, amount, type, reference, created_at)
        VALUES (?, ?, 'trade_open', 'UC Trade Open', NOW())
    ")->execute([$uid, -($margin + $fee)]);

    /* COIN HISTORY (SYSTEM) */
    $systemId = $db->query("
        SELECT id FROM users WHERE role='system' LIMIT 1
    ")->fetchColumn();

    if ($systemId) {
        $db->prepare("
            INSERT INTO coin_history
            (user_id, amount, type, reference, created_at)
            VALUES (?, ?, 'trade_fee', 'UC Trade Fee', NOW())
        ")->execute([$systemId, $fee]);
    }

    /* CREATE POSITION */
    $db->prepare("
        INSERT INTO uc_positions
        (user_id, side, margin, leverage, size, entry_price, stop_loss, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())
    ")->execute([
        $uid,
        $side,
        $margin,
        $leverage,
        $size,
        $price,
        $stopLoss
    ]);

    $db->commit();

    echo "POSITION OPENED";

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo "FAILED";
}
