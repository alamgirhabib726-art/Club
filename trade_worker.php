<?php
/**
 * UNMOOR CLUB - 24/7 AUTONOMOUS TRADING WORKER
 * 
 * Railway Worker / Daemon CLI Process.
 * 
 * Usage:
 *   php trade_worker.php
 * 
 * Functions:
 *   - Autonomous deterministic price engine heartbeat
 *   - Continuous candlestick generation (1m, 5m, 15m, 1h, 4h, 1d)
 *   - Continuous limit order matching
 *   - Continuous Stop Loss & Take Profit monitoring
 *   - Continuous Liquidation Engine scanning
 */

// Only allow execution from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: Worker is a background CLI process only.\n");
}

set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/htdocs/db.php';
require_once __DIR__ . '/htdocs/trade/engine/fee_engine.php';
require_once __DIR__ . '/htdocs/trade/engine/wallet_engine.php';
require_once __DIR__ . '/htdocs/trade/engine/position_engine.php';
require_once __DIR__ . '/htdocs/trade/engine/liquidation_engine.php';
require_once __DIR__ . '/htdocs/trade/engine/market_engine.php';

echo "====================================================\n";
echo "🚀 UNMOOR CLUB 24/7 TRADING WORKER INITIALIZED\n";
echo "====================================================\n";
echo "Market Symbol: UC/BDT\n";
echo "Starting continuous price feed & matching loop...\n\n";

$running = true;

// Signal handlers for clean shutdown on Railway/Linux
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function() use (&$running) {
        echo "\n[SHUTDOWN] Received SIGTERM. Stopping worker gracefully...\n";
        $running = false;
    });
    pcntl_signal(SIGINT, function() use (&$running) {
        echo "\n[SHUTDOWN] Received SIGINT. Stopping worker gracefully...\n";
        $running = false;
    });
}

$iteration = 0;

while ($running) {
    $iteration++;
    $startTime = microtime(true);

    try {
        // Execute market tick and matching
        $state = MarketEngine::heartbeat($db, true);

        $price = number_format((float)($state['price'] ?? 2.0), 4);
        $change = number_format((float)($state['change_24h'] ?? 0.0), 2);
        $vol = number_format((float)($state['volume_24h'] ?? 0.0), 2);
        $ts = date('Y-m-d H:i:s');

        if ($iteration % 5 === 0 || $iteration === 1) {
            echo "[$ts] #$iteration | UC/BDT: ৳ $price ($change%) | 24h Vol: $vol UC\n";
        }

    } catch (Throwable $e) {
        echo "[ERROR] Worker iteration #$iteration failed: " . $e->getMessage() . "\n";
        sleep(2);
    }

    $elapsed = microtime(true) - $startTime;
    $sleepMicrosec = max(200000, (int)((2.0 - $elapsed) * 1000000));
    usleep($sleepMicrosec);
}

echo "Worker stopped successfully.\n";
