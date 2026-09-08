<?php
/**
 * ============================================
 * TRADE ORDER API (UC)
 * ============================================
 * - Open / Close trades
 * - Margin + leverage rules
 * - Fee collection
 */

session_start();
require_once __DIR__ . "/../../db.php";

/* ================= AUTH ================= */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("LOGIN_REQUIRED");
}

$uid = (int)$_SESSION['user_id'];

/* ================= INPUT ================= */
$action   = $_POST['action'] ?? '';
$side     = $_POST['side'] ?? '';
$margin   = (float)($_POST['margin'] ?? 0);
$leverage = (int)($_POST['leverage'] ?? 0);
$posId    = (int)($_POST['position_id'] ?? 0);

/* ================= FETCH USER ================= */
$user = $db->prepare("
    SELECT id, coins
    FROM users
    WHERE id = ?
    LIMIT 1
");
$user->execute([$uid]);
$user = $user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("INVALID_USER");
}

/* ================= CLOSE POSITION ================= */
if ($action === 'close') {

    $pos = $db->prepare("
        SELECT *
        FROM positions
        WHERE id = ?
          AND user_id = ?
          AND status = 'open'
        LIMIT 1
    ");
    $pos->execute([$posId, $uid]);
    $pos = $pos->fetch(PDO::FETCH_ASSOC);

    if (!$pos) {
        die("POSITION_NOT_FOUND");
    }

    /* Market price */
    $price = $db->query("
        SELECT price
        FROM market_state
        WHERE symbol='UC'
        LIMIT 1
    ")->fetchColumn();

    $pnl = ($pos['side'] === 'long')
        ? ($price - $pos['entry_price']) * $pos['size']
        : ($pos['entry_price'] - $price) * $pos['size'];

    $db->beginTransaction();
    try {

        /* Return margin + PnL */
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id = ?
        ")->execute([$pos['margin'] + $pnl, $uid]);

        /* Close position */
        $db->prepare("
            UPDATE positions
            SET status='closed',
                exit_price=?,
                pnl=?,
                closed_at=NOW()
            WHERE id=?
        ")->execute([$price, $pnl, $posId]);

        $db->commit();
        echo "CLOSED";
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        die("CLOSE_FAILED");
    }
}

/* ================= OPEN POSITION ================= */

if ($action !== 'open') {
    die("INVALID_ACTION");
}

if (!in_array($side, ['long','short'], true)) {
    die("INVALID_SIDE");
}

if ($margin < 100) {
    die("MIN_MARGIN_100");
}

if ($leverage < 5 || $leverage > 100) {
    die("INVALID_LEVERAGE");
}

if ($user['coins'] < $margin) {
    die("INSUFFICIENT_BALANCE");
}

/* ================= FEE LOGIC ================= */
/*
 Base fee: 0.1%
 + leverage risk fee
*/
$feeRate = 0.001 + ($leverage * 0.00015);
$fee     = $margin * $feeRate;

/* ================= MARKET PRICE ================= */
$price = $db->query("
    SELECT price
    FROM market_state
    WHERE symbol='UC'
    LIMIT 1
")->fetchColumn();

/* ================= POSITION SIZE ================= */
$size = ($margin * $leverage) / $price;

/* ================= DB ================= */
$db->beginTransaction();
try {

    /* Deduct margin + fee */
    $db->prepare("
        UPDATE users
        SET coins = coins - ?
        WHERE id = ?
    ")->execute([$margin + $fee, $uid]);

    /* Fee → system */
    $db->prepare("
        UPDATE users
        SET coins = coins + ?
        WHERE role = 'system'
    ")->execute([$fee]);

    /* Create position */
    $db->prepare("
        INSERT INTO positions
        (user_id, side, margin, leverage, size, entry_price, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
    ")->execute([
        $uid,
        $side,
        $margin,
        $leverage,
        $size,
        $price
    ]);

    $db->commit();
    echo "OPENED";
    exit;

} catch (Exception $e) {
    $db->rollBack();
    die("OPEN_FAILED");
}