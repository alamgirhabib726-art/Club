<?php
/**
 * ============================================
 * UC MARKET PRICE FEED (CORE)
 * ============================================
 * - Generates UC price
 * - No balance change here
 * - No user logic here
 * - Pure market means
 * - Engine-ready
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../../db.php";

/* ================= CONFIG ================= */
$SYMBOL = "UC";
$BASE_PRICE = 100;          // starting price
$MAX_MOVE = 0.8;            // max candle move %
$FAST_MOVE = 1.5;           // when no traders
$SLOW_MOVE = 0.4;           // when traders exist
$HUNT_MOVE = 2.5;           // SL hunt strength

/* ================= FETCH LAST PRICE ================= */
$stmt = $db->prepare("
    SELECT price
    FROM trade_prices
    WHERE symbol = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$SYMBOL]);
$lastPrice = $stmt->fetchColumn();

if (!$lastPrice) {
    $lastPrice = $BASE_PRICE;
}

/* ================= ACTIVE TRADERS ================= */
$activeTraders = (int)$db->query("
    SELECT COUNT(*) 
    FROM trades 
    WHERE status = 'open'
")->fetchColumn();

/* ================= MARKET LOGIC ================= */
$direction = rand(0,1) ? 1 : -1;
$volatility = $MAX_MOVE;

/* === NO ONE TRADING → FAST RANDOM MOVE === */
if ($activeTraders === 0) {
    $volatility = $FAST_MOVE;
}

/* === ONE TRADER → MOVE AGAINST HIM === */
elseif ($activeTraders === 1) {

    $trade = $db->query("
        SELECT side 
        FROM trades 
        WHERE status='open'
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($trade) {
        // Opposite direction
        $direction = ($trade['side'] === 'buy') ? -1 : 1;
        $volatility = $SLOW_MOVE;
    }
}

/* === MULTIPLE TRADERS → SL HUNT === */
elseif ($activeTraders >= 2) {
    $direction = rand(0,1) ? 1 : -1;
    $volatility = $HUNT_MOVE;
}

/* ================= PRICE CALC ================= */
$changePercent = (mt_rand(10,100) / 100) * $volatility;
$newPrice = $lastPrice + ($lastPrice * ($changePercent / 100) * $direction);

/* ================= SAFE FLOOR ================= */
if ($newPrice <= 0) {
    $newPrice = $BASE_PRICE;
}

/* ================= SAVE PRICE ================= */
$db->prepare("
    INSERT INTO trade_prices (symbol, price, created_at)
    VALUES (?, ?, NOW())
")->execute([$SYMBOL, $newPrice]);

/* ================= OUTPUT ================= */
echo json_encode([
    "symbol"        => $SYMBOL,
    "price"         => round($newPrice, 4),
    "last_price"   => round($lastPrice, 4),
    "direction"    => $direction === 1 ? "up" : "down",
    "volatility"   => $volatility,
    "activeTrades" => $activeTraders,
    "timestamp"    => time()
]);