<?php
/**
 * ============================================
 * UC OPEN POSITION ENGINE
 * ============================================
 */

session_start();
require_once __DIR__ . "/../../db.php";

/* ================= AUTH ================= */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("LOGIN REQUIRED");
}

$userId = (int)$_SESSION['user_id'];

/* ================= INPUT ================= */
$side     = $_POST['side'] ?? '';
$margin   = (float)($_POST['margin'] ?? 0);
$leverage = (int)($_POST['leverage'] ?? 0);

/* ================= VALIDATION ================= */
if (!in_array($side, ['long','short'], true)) {
    die("INVALID SIDE");
}

if ($margin < 100) {
    die("MINIMUM MARGIN 100");
}

if ($leverage < 5 || $leverage > 100) {
    die("INVALID LEVERAGE");
}

/* ================= USER ================= */
$user = $db->prepare("
    SELECT id, coins, status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$user->execute([$userId]);
$user = $user->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    die("USER BLOCKED");
}

if ($user['coins'] < $margin) {
    die("INSUFFICIENT BALANCE");
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

/* ================= FEE MODEL ================= */
/*
 Fee logic:
 - Base fee 0.2%
 - +0.02% per leverage step
*/
$feeRate = 0.002 + ($leverage * 0.0002);
$fee = round($margin * $feeRate, 2);

$totalLock = $margin + $fee;

if ($user['coins'] < $totalLock) {
    die("INSUFFICIENT BALANCE FOR FEE");
}

/* ================= POSITION SIZE ================= */
$positionSize = ($margin * $leverage) / $price;

/* ================= LIQUIDATION PRICE ================= */
$liqMove = 1 / $leverage;

if ($side === 'long') {
    $liquidationPrice = $price * (1 - $liqMove);
} else {
    $liquidationPrice = $price * (1 + $liqMove);
}

$db->beginTransaction();
try {

    /* LOCK USER FUNDS */
    $db->prepare("
        UPDATE users
        SET coins = coins - ?
        WHERE id = ?
    ")->execute([$totalLock, $userId]);

    /* SYSTEM GETS FEE */
    $db->prepare("
        UPDATE users
        SET coins = coins + ?
        WHERE role = 'system'
        LIMIT 1
    ")->execute([$fee]);

    /* CREATE POSITION */
    $db->prepare("
        INSERT INTO uc_positions
        (user_id, side, margin, leverage, size, entry_price, liquidation_price, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())
    ")->execute([
        $userId,
        $side,
        $margin,
        $leverage,
        $positionSize,
        $price,
        $liquidationPrice
    ]);

    $db->commit();

    echo "POSITION OPENED";

} catch (Exception $e) {
    $db->rollBack();
    die("FAILED TO OPEN POSITION");
}