<?php
/**
 * ============================================
 * UC TRADE ENTRY HANDLER
 * ============================================
 */

session_start();
require_once "../../db.php";

/* ================= AUTH ================= */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("LOGIN REQUIRED");
}

$userId = (int)$_SESSION['user_id'];

/* ================= INPUT ================= */
$side     = $_POST['side'] ?? '';      // long | short
$margin   = (float)($_POST['margin'] ?? 0);
$leverage = (int)($_POST['leverage'] ?? 0);

/* ================= VALIDATION ================= */
if (!in_array($side, ['long','short'], true)) {
    die("INVALID SIDE");
}

if ($margin < 100) {
    die("MIN MARGIN 100 BDT");
}

if ($leverage < 5 || $leverage > 100) {
    die("LEVERAGE 5x – 100x ONLY");
}

/* ================= USER ================= */
$user = $db->prepare("
    SELECT id, coins
    FROM users
    WHERE id = ?
    LIMIT 1
");
$user->execute([$userId]);
$user = $user->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['coins'] < $margin) {
    die("INSUFFICIENT BALANCE");
}

/* ================= SYSTEM ================= */
$system = $db->query("
    SELECT id, coins
    FROM users
    WHERE role='system'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$system) {
    die("SYSTEM ERROR");
}

/* ================= MARKET PRICE ================= */
$price = (float)$db->query("
    SELECT price
    FROM uc_market
    ORDER BY id DESC
    LIMIT 1
")->fetchColumn();

if ($price <= 0) {
    die("NO MARKET PRICE");
}

/* ================= ENTRY FEE ================= */
$feeRate = min(0.01, max(0.002, $leverage * 0.0001));
$entryFee = $margin * $feeRate;

/* ================= SYSTEM RISK CAP ================= */
$MAX_SYSTEM_LOSS = 5; // coins (≈ 50 BDT)

$maxProfit = ($margin * $leverage) * 0.05;

if ($maxProfit > $MAX_SYSTEM_LOSS && $system['coins'] < $MAX_SYSTEM_LOSS) {
    die("LIQUIDITY TOO LOW");
}

/* ================= STOP LOSS ================= */
$slDistance = ($margin / ($margin * $leverage)) * $price;

$stopLoss = $side === 'long'
    ? $price - $slDistance
    : $price + $slDistance;

/* ================= OPEN POSITION ================= */
$db->beginTransaction();
try {

    // Deduct margin + fee
    $db->prepare("
        UPDATE users
        SET coins = coins - ?
        WHERE id = ?
    ")->execute([$margin + $entryFee, $userId]);

    // Fee to system
    $db->prepare("
        UPDATE users
        SET coins = coins + ?
        WHERE id = ?
    ")->execute([$entryFee, $system['id']]);

    // Save position
    $db->prepare("
        INSERT INTO uc_positions
        (user_id, side, margin, leverage,
         entry_price, stop_loss, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
    ")->execute([
        $userId,
        $side,
        $margin,
        $leverage,
        $price,
        $stopLoss
    ]);

    $db->commit();

    echo "POSITION OPENED";

} catch (Exception $e) {
    $db->rollBack();
    die("OPEN FAILED");
}