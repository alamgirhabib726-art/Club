<?php
require_once __DIR__ . "/db.php";
header("Content-Type: application/json");

/*
 BASIC MARKET FEED
 - No user logic
 - No trade logic
 - Pure price illusion
*/

// fetch last price
$row = $db->query("
    SELECT id, price
    FROM trade_market
    ORDER BY id DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$price = $row ? (float)$row['price'] : 1.0000;

// ===== MARKET BEHAVIOR =====

// base volatility (no traders = fast)
$volatility = 0.0025;

// random direction
$direction = rand(0,100) < 50 ? -1 : 1;

// random strength
$move = mt_rand(1, 100) / 100000;

// calculate new price
$newPrice = $price + ($direction * $move * $volatility * $price);

// protection
if ($newPrice <= 0.0001) {
    $newPrice = 0.0001;
}

// save tick
$db->prepare("
    INSERT INTO trade_market (price, created_at)
    VALUES (?, NOW())
")->execute([$newPrice]);

echo json_encode([
    "price" => number_format($newPrice, 6, ".", "")
]);
