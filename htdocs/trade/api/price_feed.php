<?php
/**
 * UNMOOR CLUB - UC MARKET PRICE FEED (FORWARDING COMPATIBILITY)
 * Bridges legacy price feed requests directly to the centralized MarketEngine.
 */

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

require_once __DIR__ . "/../../db.php";
require_once __DIR__ . "/../engine/market_engine.php";

$market = MarketEngine::getMarketState($db);
$price = (float)($market['price'] ?? 2.0);
$open24h = (float)($market['open_24h'] ?? 2.0);
$change24h = (float)($market['change_24h'] ?? 0.0);

echo json_encode([
    "symbol"        => "UC/BDT",
    "price"         => round($price, 4),
    "last_price"    => round($open24h, 4),
    "direction"     => $change24h >= 0 ? "up" : "down",
    "bid"           => round((float)($market['bid'] ?? ($price * 0.998)), 4),
    "ask"           => round((float)($market['ask'] ?? ($price * 1.002)), 4),
    "high_24h"      => round((float)($market['high_24h'] ?? $price), 4),
    "low_24h"       => round((float)($market['low_24h'] ?? $price), 4),
    "volume_24h"    => round((float)($market['volume_24h'] ?? 0), 2),
    "change_24h"    => round($change24h, 2),
    "timestamp"     => time()
]);
