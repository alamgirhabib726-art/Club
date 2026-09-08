<?php
/**
 * ============================================
 * UC TRADE CHART DATA API
 * Returns candlestick JSON array for Lightweight Charts
 * ============================================
 */

require_once __DIR__ . "/../../db.php";

header("Content-Type: application/json");

$tf = $_GET['tf'] ?? '1m';

$map = [
    '1m'  => 60,
    '5m'  => 300,
    '15m' => 900,
    '1h'  => 3600
];

$interval = $map[$tf] ?? 60;

/* FETCH RAW PRICES */
try {
    $rows = $db->query("
        SELECT UNIX_TIMESTAMP(created_at) AS ts, CAST(price AS DOUBLE) AS price
        FROM uc_market
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        // Fallback to trade_market / trade_prices
        $rows = $db->query("
            SELECT UNIX_TIMESTAMP(created_at) AS ts, CAST(price AS DOUBLE) AS price
            FROM trade_market
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    echo json_encode([]);
    exit;
}

if (empty($rows)) {
    echo json_encode([]);
    exit;
}

/* BUILD CANDLES */
$candles = [];
$current = null;

foreach ($rows as $r) {
    $ts = (int)$r['ts'];
    $price = (float)$r['price'];
    $bucket = (int)(floor($ts / $interval) * $interval);

    if (!$current || $current['time'] !== $bucket) {
        if ($current) {
            $candles[] = $current;
        }
        $current = [
            'time'  => $bucket,
            'open'  => $price,
            'high'  => $price,
            'low'   => $price,
            'close' => $price
        ];
    } else {
        $current['high'] = max((float)$current['high'], $price);
        $current['low']  = min((float)$current['low'],  $price);
        $current['close']= $price;
    }
}

if ($current) {
    $candles[] = $current;
}

echo json_encode($candles);
