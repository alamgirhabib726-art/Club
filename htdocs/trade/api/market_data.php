<?php
/**
 * ============================================
 * UC MARKET DATA API (READ ONLY)
 * ============================================
 */

require_once __DIR__ . "/../../db.php";

header("Content-Type: application/json");

/* ================= INPUT ================= */
$tf = $_GET['tf'] ?? '1m';

$map = [
    '1m'  => 60,
    '5m'  => 300,
    '15m' => 900,
    '1h'  => 3600
];

if (!isset($map[$tf])) {
    echo json_encode([]);
    exit;
}

$interval = $map[$tf];

/* ================= FETCH RAW PRICES ================= */
$rows = $db->query("
    SELECT UNIX_TIMESTAMP(created_at) AS ts, price
    FROM uc_market
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo json_encode([]);
    exit;
}

/* ================= BUILD CANDLES ================= */
$candles = [];
$current = null;

foreach ($rows as $r) {

    $bucket = floor($r['ts'] / $interval) * $interval;

    if (!$current || $current['time'] !== $bucket) {

        if ($current) {
            $candles[] = $current;
        }

        $current = [
            'time'  => $bucket,
            'open'  => $r['price'],
            'high'  => $r['price'],
            'low'   => $r['price'],
            'close' => $r['price']
        ];

    } else {

        $current['high'] = max($current['high'], $r['price']);
        $current['low']  = min($current['low'],  $r['price']);
        $current['close']= $r['price'];
    }
}

if ($current) {
    $candles[] = $current;
}

/* ================= OUTPUT ================= */
echo json_encode($candles);