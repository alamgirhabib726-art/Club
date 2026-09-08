<?php
require_once __DIR__ . "/db.php";

/*
 TRADE ENGINE v1
 ----------------
 - No UI
 - No direct price change
 - Returns market bias
*/

function trade_engine(PDO $db): array
{
    // ===== SYSTEM LIQUIDITY =====
    $liquidity = (float)$db->query("
        SELECT coins FROM users
        WHERE role = 'system'
        LIMIT 1
    ")->fetchColumn();

    // ===== OPEN TRADES =====
    $trades = $db->query("
        SELECT direction, leverage, amount
        FROM trade_positions
        WHERE status = 'open'
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ===== DEFAULT STATE =====
    $bias = 'flat';
    $power = 0.1;

    if (!$trades) {
        // no traders → fast random market
        return [
            'bias' => 'random',
            'power' => 1.0
        ];
    }

    // ===== AGGREGATE EXPOSURE =====
    $long = 0;
    $short = 0;

    foreach ($trades as $t) {
        $risk = $t['amount'] * $t['leverage'];

        if ($t['direction'] === 'long') {
            $long += $risk;
        } else {
            $short += $risk;
        }
    }

    // ===== MARKET DECISION =====
    if ($long > $short) {
        $bias = 'down'; // hunt longs
    } elseif ($short > $long) {
        $bias = 'up';   // hunt shorts
    }

    // ===== SYSTEM LOSS PROTECTION =====
    if ($liquidity <= 5) {
        // system almost drained → force profit
        $bias = $bias === 'up' ? 'down' : 'up';
        $power = 0.9;
    } else {
        $power = min(1, abs($long - $short) / max($long, $short));
    }

    return [
        'bias' => $bias,
        'power' => round($power, 2)
    ];
}
