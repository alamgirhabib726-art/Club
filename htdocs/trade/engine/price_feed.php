<?php
/**
 * ============================================
 * UC PRICE FEED ENGINE
 * ============================================
 * - Smart movement
 * - Trader-aware
 * - SL hunting
 * - Liquidity protected
 */

require_once __DIR__ . "/../../db.php";

/* ================= CONFIG ================= */
$SYMBOL = "UC";

/* BASE SPEED */
$FAST_MOVE = 0.35;
$SLOW_MOVE = 0.12;
$HUNT_MOVE = 0.08;

/* RANDOMNESS */
$NOISE_MIN = -0.02;
$NOISE_MAX = 0.02;

/* ================= CURRENT PRICE ================= */
$priceRow = $db->query("
    SELECT price 
    FROM market_price 
    WHERE symbol='UC'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$currentPrice = (float)($priceRow['price'] ?? 100);

/* ================= OPEN TRADES ================= */
$trades = $db->query("
    SELECT user_id, side, leverage, margin, stop_loss
    FROM trades
    WHERE status='open'
")->fetchAll(PDO::FETCH_ASSOC);

$openCount = count($trades);

/* ================= MARKET MODE ================= */
$mode = 'idle';
$directionBias = 0;

if ($openCount === 0) {
    // No one trading → fast random
    $mode = 'free';
    $directionBias = rand(-1,1);
}

elseif ($openCount === 1) {
    // One trader → move against him
    $mode = 'counter';
    $t = $trades[0];
    $directionBias = ($t['side'] === 'buy') ? -1 : 1;
}

else {
    // Multiple traders → hunt the weakest SL
    $mode = 'hunt';

    // Find largest margin without leverage
    usort($trades, function($a,$b){
        return ($b['margin'] / $b['leverage']) <=> ($a['margin'] / $a['leverage']);
    });

    $target = $trades[0];
    $directionBias = ($target['side'] === 'buy') ? -1 : 1;
}

/* ================= MOVE SIZE ================= */
switch ($mode) {
    case 'free':
        $step = $FAST_MOVE;
        break;
    case 'counter':
        $step = $SLOW_MOVE;
        break;
    case 'hunt':
        $step = $HUNT_MOVE;
        break;
    default:
        $step = 0.1;
}

/* ================= NOISE ================= */
$noise = mt_rand(
    $NOISE_MIN * 1000,
    $NOISE_MAX * 1000
) / 1000;

/* ================= FINAL PRICE ================= */
$newPrice = $currentPrice + ($step * $directionBias) + $noise;

/* Prevent negative / zero price */
if ($newPrice <= 0) {
    $newPrice = $currentPrice;
}

/* ================= SAVE PRICE ================= */
$db->prepare("
    UPDATE market_price
    SET price=?, updated_at=NOW()
    WHERE symbol='UC'
")->execute([$newPrice]);

/* ================= OPTIONAL LOG ================= */
$db->prepare("
    INSERT INTO market_ticks
    (symbol, price, mode, created_at)
    VALUES (?, ?, ?, NOW())
")->execute([$SYMBOL, $newPrice, $mode]);

return [
    'price'     => $newPrice,
    'mode'      => $mode,
    'direction' => $directionBias
];