<?php
/**
 * ============================================
 * UC MARKET BRAIN
 * Price control + hunting logic
 * ============================================
 */

require_once __DIR__ . "/../../db.php";

/* ================= CONFIG ================= */
$SYMBOL = 'UC';

/* Admin-defined system protection */
$SYSTEM_FLOOR = (float)$db->query("
    SELECT value FROM system_settings
    WHERE name='liquidity_floor'
    LIMIT 1
")->fetchColumn();

if ($SYSTEM_FLOOR <= 0) {
    $SYSTEM_FLOOR = 5000; // fallback safety
}

/* ================= CURRENT PRICE ================= */
$market = $db->query("
    SELECT price FROM market_state
    WHERE symbol='$SYMBOL'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$price = (float)$market['price'];

/* ================= SYSTEM BALANCE ================= */
$systemCoins = (float)$db->query("
    SELECT coins FROM users WHERE role='system' LIMIT 1
")->fetchColumn();

/* ================= OPEN POSITIONS ================= */
$positions = $db->query("
    SELECT id, user_id, side, margin, leverage,
           entry_price, sl, liq_price
    FROM positions
    WHERE status='open'
      AND symbol='$SYMBOL'
")->fetchAll(PDO::FETCH_ASSOC);

$count = count($positions);

/* ================= NO TRADERS ================= */
if ($count === 0) {

    // Fast random movement
    $delta = rand(-30, 30) / 100;
    $price += $delta;

}

/* ================= ONE TRADER ================= */
elseif ($count === 1) {

    $p = $positions[0];
    $dir = ($p['side'] === 'long') ? -1 : 1;

    // slow opposite move
    $price += $dir * (rand(1, 5) / 100);

}

/* ================= MULTIPLE TRADERS ================= */
else {

    // Find biggest vulnerable trader
    usort($positions, function($a, $b) {
        return ($b['margin'] / $b['leverage']) <=> ($a['margin'] / $a['leverage']);
    });

    $target = $positions[0];

    // Decide hunt direction
    if ($target['side'] === 'long') {
        $price -= rand(5, 12) / 100;
    } else {
        $price += rand(5, 12) / 100;
    }

    // If system low → force liquidation faster
    if ($systemCoins < $SYSTEM_FLOOR) {
        if ($target['side'] === 'long') {
            $price -= rand(10, 25) / 100;
        } else {
            $price += rand(10, 25) / 100;
        }
    }
}

/* ================= PRICE FLOOR ================= */
if ($price < 0.01) {
    $price = 0.01;
}

/* ================= SAVE PRICE ================= */
$db->prepare("
    UPDATE market_state
    SET price=?, updated_at=NOW()
    WHERE symbol=?
")->execute([$price, $SYMBOL]);

echo "PRICE UPDATED: $price";