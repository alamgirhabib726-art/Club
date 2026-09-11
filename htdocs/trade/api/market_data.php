<?php
/**
 * UNMOOR CLUB - MARKET DATA API
 * Returns live market stats, price, 24h metrics, orderbook depth, and recent trades.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../engine/fee_engine.php';
require_once __DIR__ . '/../engine/market_engine.php';

try {
    // Advance heartbeat
    $state = MarketEngine::heartbeat($db);

    $currentPrice = (float)($state['price'] ?? 2.0000);
    $bid = (float)($state['bid'] ?? ($currentPrice * 0.9975));
    $ask = (float)($state['ask'] ?? ($currentPrice * 1.0025));

    // Fetch recent market trades
    $stmt = $db->query("SELECT price, volume, side, created_at FROM market_ticks WHERE symbol = 'UC' ORDER BY id DESC LIMIT 25");
    $recentTicks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recentTrades = [];
    foreach ($recentTicks as $t) {
        $recentTrades[] = [
            'price' => (float)$t['price'],
            'amount' => (float)$t['volume'],
            'side' => $t['side'],
            'time' => date('H:i:s', strtotime($t['created_at']))
        ];
    }

    // Generate balanced simulated orderbook depth around market price
    $bids = [];
    $asks = [];
    for ($i = 1; $i <= 6; $i++) {
        $step = $i * 0.005;
        $bPrice = max(0.01, round($bid * (1.0 - $step), 4));
        $aPrice = round($ask * (1.0 + $step), 4);
        $bVol = round(rand(20, 250) + (rand(1, 99) / 100), 2);
        $aVol = round(rand(20, 250) + (rand(1, 99) / 100), 2);

        $bids[] = ['price' => $bPrice, 'amount' => $bVol, 'total' => round($bPrice * $bVol, 2)];
        $asks[] = ['price' => $aPrice, 'amount' => $aVol, 'total' => round($aPrice * $aVol, 2)];
    }

    // Pool health stats
    $pool = $db->query("SELECT bdt_reserve, tuc_reserve, total_fee_revenue_bdt, conversion_fee_revenue_bdt, status FROM liquidity_pool WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $bdtRes = (float)($pool['bdt_reserve'] ?? 500000.0);
    $tucRes = (float)(($pool['tuc_reserve'] ?? 0) > 0 ? $pool['tuc_reserve'] : 250000.0);
    $backingPrice = round($bdtRes / $tucRes, 4);

    echo json_encode([
        'success' => true,
        'market' => [
            'symbol' => 'UC/BDT',
            'name' => 'Trading UC (tUC)',
            'price' => $currentPrice,
            'bid' => $bid,
            'ask' => $ask,
            'high_24h' => (float)($state['high_24h'] ?? $currentPrice),
            'low_24h' => (float)($state['low_24h'] ?? $currentPrice),
            'change_24h' => (float)($state['change_24h'] ?? 0.0),
            'volume_24h' => (float)($state['volume_24h'] ?? 0.0),
            'volume_bdt_24h' => (float)($state['volume_bdt_24h'] ?? 0.0),
            'status' => $state['status'] ?? 'active',
            'server_time' => time()
        ],
        'orderbook' => [
            'bids' => $bids,
            'asks' => $asks
        ],
        'recent_trades' => $recentTrades,
        'liquidity_pool' => [
            'bdt_reserve' => $bdtRes,
            'tuc_reserve' => $tucRes,
            'backing_price' => $backingPrice,
            'total_fee_revenue' => (float)($pool['total_fee_revenue_bdt'] ?? 0.0),
            'conversion_fee_revenue' => (float)($pool['conversion_fee_revenue_bdt'] ?? 0.0),
            'status' => $pool['status'] ?? 'active'
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
