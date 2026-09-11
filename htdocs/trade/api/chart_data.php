<?php
/**
 * UNMOOR CLUB - CHART DATA API
 * Delivers OHLCV Candlestick data for Lightweight Charts / TradingView.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../engine/market_engine.php';

try {
    MarketEngine::heartbeat($db);

    $resolution = $_GET['resolution'] ?? '1m';
    $validResolutions = ['1m', '5m', '15m', '1h', '4h', '1d'];
    if (!in_array($resolution, $validResolutions, true)) {
        $resolution = '1m';
    }

    $limit = min(500, max(50, (int)($_GET['limit'] ?? 200)));

    $stmt = $db->prepare("SELECT timestamp as time, open, high, low, close, volume 
        FROM market_candles 
        WHERE symbol = 'UC' AND resolution = ? 
        ORDER BY timestamp DESC LIMIT ?");
    $stmt->execute([$resolution, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If table is newly seeded or timeframe lacks depth, generate initial historical baseline candles
    if (count($rows) < 100) {
        $now = time();
        $resSec = match($resolution) {
            '5m' => 300,
            '15m' => 900,
            '1h' => 3600,
            '4h' => 14400,
            '1d' => 86400,
            default => 60
        };

        $basePrice = (float)($db->query("SELECT price FROM market_state WHERE symbol = 'UC'")->fetchColumn() ?: 2.0000);
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        $ins = $db->prepare("INSERT OR IGNORE INTO market_candles (symbol, resolution, open, high, low, close, volume, timestamp, created_at)
            VALUES ('UC', ?, ?, ?, ?, ?, ?, ?, $nowExpr)");

        for ($i = 120; $i >= 1; $i--) {
            $t = (int)(floor(($now - ($i * $resSec)) / $resSec) * $resSec);
            $randFluc = (sin($i / 7.0) * 0.03) + ((mt_rand(-40, 40) / 1000.0) * 0.015);
            $cOpen = round($basePrice + $randFluc, 4);
            $cClose = round($cOpen + (mt_rand(-25, 25) / 1000.0), 4);
            $cHigh = round(max($cOpen, $cClose) + (mt_rand(2, 20) / 1000.0), 4);
            $cLow = round(max(0.1, min($cOpen, $cClose) - (mt_rand(2, 20) / 1000.0)), 4);
            $cVol = round(mt_rand(15, 120) + (mt_rand(1, 99) / 100.0), 2);

            try {
                $ins->execute([$resolution, $cOpen, $cHigh, $cLow, $cClose, $cVol, $t]);
            } catch (Throwable $t) {}
            $basePrice = $cClose;
        }

        // Re-fetch
        $stmt->execute([$resolution, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Sort chronologically ascending for charts
    usort($rows, fn($a, $b) => (int)$a['time'] <=> (int)$b['time']);

    $candles = [];
    foreach ($rows as $r) {
        $candles[] = [
            'time' => (int)$r['time'],
            'open' => (float)$r['open'],
            'high' => (float)$r['high'],
            'low' => (float)$r['low'],
            'close' => (float)$r['close'],
            'volume' => (float)$r['volume']
        ];
    }

    echo json_encode([
        'success' => true,
        'symbol' => 'UC/BDT',
        'resolution' => $resolution,
        'count' => count($candles),
        'candles' => $candles
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
