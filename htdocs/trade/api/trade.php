<?php
/**
 * ============================================
 * UC TRADE API
 * File: /trade/api/trade.php
 * ============================================
 */

define('UC_ENGINE', true);

session_start();
require_once __DIR__ . "/../../db.php";

/* ENGINES */
require_once __DIR__ . "/../engine/price_engine.php";
require_once __DIR__ . "/../engine/position_engine.php";
require_once __DIR__ . "/../engine/liquidity_guard.php";

/* ================= AUTH ================= */

if (!isset($_SESSION['user_id'])) {
    json_response(false, "Unauthorized");
}

$uid = (int)$_SESSION['user_id'];

/* ================= USER ================= */

$stmt = $db->prepare("
    SELECT coins, status
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    json_response(false, "Account inactive");
}

/* ================= INPUT ================= */

$action    = $_POST['action'] ?? '';
$margin    = (float)($_POST['margin'] ?? 0);
$leverage  = (int)($_POST['leverage'] ?? 0);
$direction = (int)($_POST['direction'] ?? 0); // 1 long, -1 short

if (!in_array($action, ['open','close'], true)) {
    json_response(false, "Invalid action");
}

/* ================= OPEN POSITION ================= */

if ($action === 'open') {

    if ($margin <= 0 || $margin > $user['coins']) {
        json_response(false, "Insufficient balance");
    }

    if (!in_array($direction, [1,-1], true)) {
        json_response(false, "Invalid direction");
    }

    $price = get_current_price();

    $position = open_position(
        $uid,
        $margin,
        $leverage,
        $direction,
        $price
    );

    if (isset($position['error'])) {
        json_response(false, $position['error']);
    }

    /* SYSTEM SAFETY */
    if (!liquidity_allow($position)) {
        json_response(false, "Market unavailable");
    }

    /* SAVE POSITION */
    $db->prepare("
        INSERT INTO trade_positions
        (user_id, margin, leverage, direction, entry_price,
         position_size, fee, liquidation_price, status, created_at)
        VALUES (?,?,?,?,?,?,?,?, 'open', NOW())
    ")->execute([
        $uid,
        $position['margin'],
        $position['leverage'],
        $position['direction'],
        $position['entry_price'],
        $position['position_size'],
        $position['fee'],
        $position['liquidation_px']
    ]);

    /* CUT USER BALANCE */
    $db->prepare("
        UPDATE users
        SET coins = coins - ?
        WHERE id = ?
    ")->execute([$position['margin'], $uid]);

    json_response(true, "Position opened", [
        'entry' => $position['entry_price'],
        'liq'   => $position['liquidation_px'],
        'fee'   => $position['fee']
    ]);
}

/* ================= CLOSE POSITION ================= */

if ($action === 'close') {

    $pid = (int)($_POST['position_id'] ?? 0);
    if (!$pid) json_response(false, "Invalid position");

    $stmt = $db->prepare("
        SELECT *
        FROM trade_positions
        WHERE id = ? AND user_id = ? AND status = 'open'
        LIMIT 1
    ");
    $stmt->execute([$pid, $uid]);
    $pos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pos) json_response(false, "Position not found");

    $price = get_current_price();

    $pnl = close_position($pos, $price);

    /* PAY USER */
    $db->prepare("
        UPDATE users
        SET coins = coins + ?
        WHERE id = ?
    ")->execute([$pos['margin'] + $pnl, $uid]);

    /* UPDATE POSITION */
    $db->prepare("
        UPDATE trade_positions
        SET
            exit_price = ?,
            pnl = ?,
            status = 'closed',
            closed_at = NOW()
        WHERE id = ?
    ")->execute([$price, $pnl, $pid]);

    json_response(true, "Position closed", [
        'pnl' => round($pnl, 4)
    ]);
}

/* ================= UTIL ================= */

function json_response(bool $ok, string $msg, array $data = [])
{
    header("Content-Type: application/json");
    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'data'    => $data
    ]);
    exit;
}