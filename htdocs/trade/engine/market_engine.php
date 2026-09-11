<?php
/**
 * UNMOOR CLUB - MARKET & ORDER ENGINE
 * Authoritative transparent price engine, tick generator, candlestick aggregator,
 * limit order matcher, spot trader, and conditional trigger manager.
 */

if (!defined('UC_TRADING_CORE')) {
    define('UC_TRADING_CORE', true);
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/fee_engine.php';
require_once __DIR__ . '/wallet_engine.php';
require_once __DIR__ . '/position_engine.php';
require_once __DIR__ . '/liquidation_engine.php';

class MarketEngine {

    /**
     * Heartbeat / Tick generator: runs automatically via worker or upon web API request
     */
    public static function heartbeat(PDO $db, bool $force = false): array {
        $settings = FeeEngine::getSettings($db);
        $tickInterval = (int)($settings['tick_interval_sec'] ?? 2);

        // Fetch current market state
        $stmt = $db->query("SELECT * FROM market_state WHERE symbol = 'UC' LIMIT 1");
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$state) {
            $db->exec("INSERT INTO market_state (symbol, price, bid, ask, open_24h, high_24h, low_24h, change_24h, volume_24h, volume_bdt_24h, status)
                VALUES ('UC', 2.0000, 1.9950, 2.0050, 2.0000, 2.0000, 2.0000, 0.0000, 0.0000, 0.0000, 'active')");
            $stmt = $db->query("SELECT * FROM market_state WHERE symbol = 'UC' LIMIT 1");
            $state = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $lastUpdated = strtotime($state['updated_at'] ?? 'now');
        $now = time();

        if (!$force && ($now - $lastUpdated) < $tickInterval) {
            return $state;
        }

        // Generate next tick price
        $currentPrice = (float)($state['price'] ?? 2.0000);
        $initialPrice = (float)($settings['initial_price'] ?? 2.0000);
        $volatility = (float)($settings['volatility_factor'] ?? 0.0035);

        // Deterministic price drift & bounded random-walk with mean-reversion
        $drift = ($initialPrice - $currentPrice) * 0.0005;
        $randSeed = (mt_rand(-1000, 1000) / 1000.0) * $volatility;
        $priceMultiplier = 1.0 + $drift + $randSeed;
        
        $newPrice = round($currentPrice * $priceMultiplier, 4);
        $newPrice = max(0.1000, min(50.0000, $newPrice)); // Hard boundaries

        $spread = round($newPrice * 0.0025, 4); // 0.25% spread
        $bid = round($newPrice - $spread, 4);
        $ask = round($newPrice + $spread, 4);

        $tickVolume = round((mt_rand(10, 150) / 10.0), 4);
        $tickSide = ($newPrice >= $currentPrice) ? 'buy' : 'sell';

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        $db->beginTransaction();
        try {
            // 1. Record market tick
            $tStmt = $db->prepare("INSERT INTO market_ticks (symbol, price, volume, side, created_at) VALUES ('UC', ?, ?, ?, $nowExpr)");
            $tStmt->execute([$newPrice, $tickVolume, $tickSide]);

            // 2. Aggregate candlesticks for intervals
            self::updateCandles($db, $newPrice, $tickVolume, $now);

            // 3. Compute 24h High, Low, Volume, Change
            $stats24h = self::calculate24hStats($db, $newPrice);

            // 4. Update market state
            $updState = $db->prepare("UPDATE market_state SET 
                price = ?, 
                bid = ?, 
                ask = ?, 
                high_24h = ?, 
                low_24h = ?, 
                change_24h = ?, 
                volume_24h = ?, 
                volume_bdt_24h = ?, 
                updated_at = $nowExpr 
                WHERE symbol = 'UC'");
            $updState->execute([
                $newPrice,
                $bid,
                $ask,
                $stats24h['high'],
                $stats24h['low'],
                $stats24h['change'],
                $stats24h['volume'],
                $stats24h['volume_bdt']
            ]);

            // 5. Check and execute open limit orders
            self::matchLimitOrders($db, $newPrice);

            // 6. Check SL / TP for open positions
            self::checkStopLossTakeProfit($db, $newPrice);

            // 7. Check liquidations
            LiquidationEngine::processLiquidations($db, $newPrice);

            $db->commit();

            return [
                'symbol' => 'UC/BDT',
                'price' => $newPrice,
                'bid' => $bid,
                'ask' => $ask,
                'high_24h' => $stats24h['high'],
                'low_24h' => $stats24h['low'],
                'change_24h' => $stats24h['change'],
                'volume_24h' => $stats24h['volume'],
                'volume_bdt_24h' => $stats24h['volume_bdt'],
                'status' => $state['status'] ?? 'active',
                'updated_at' => date('Y-m-d H:i:s', $now)
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("MarketEngine::heartbeat Error: " . $e->getMessage());
            return $state;
        }
    }

    /**
     * Update and roll candles for timeframes (1m, 5m, 15m, 1h, 4h, 1d)
     */
    private static function updateCandles(PDO $db, float $price, float $volume, int $timestamp): void {
        $resolutions = [
            '1m' => 60,
            '5m' => 300,
            '15m' => 900,
            '1h' => 3600,
            '4h' => 14400,
            '1d' => 86400
        ];

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        foreach ($resolutions as $res => $seconds) {
            $bucketTime = (int)(floor($timestamp / $seconds) * $seconds);

            $stmt = $db->prepare("SELECT id, open, high, low, close, volume FROM market_candles WHERE symbol = 'UC' AND resolution = ? AND timestamp = ? LIMIT 1");
            $stmt->execute([$res, $bucketTime]);
            $candle = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($candle) {
                $newHigh = max((float)$candle['high'], $price);
                $newLow = min((float)$candle['low'], $price);
                $newVol = round((float)$candle['volume'] + $volume, 4);

                $upd = $db->prepare("UPDATE market_candles SET high = ?, low = ?, close = ?, volume = ? WHERE id = ?");
                $upd->execute([$newHigh, $newLow, $price, $newVol, $candle['id']]);
            } else {
                $ins = $db->prepare("INSERT INTO market_candles (symbol, resolution, open, high, low, close, volume, timestamp, created_at)
                    VALUES ('UC', ?, ?, ?, ?, ?, ?, ?, $nowExpr)");
                $ins->execute([$res, $price, $price, $price, $price, $volume, $bucketTime]);
            }
        }
    }

    /**
     * Compute 24h rolling high, low, volume, and percentage change
     */
    private static function calculate24hStats(PDO $db, float $currentPrice): array {
        $since = time() - 86400;

        $stmt = $db->prepare("SELECT 
            MIN(low) as low_24h, 
            MAX(high) as high_24h, 
            SUM(volume) as vol_24h,
            (SELECT open FROM market_candles WHERE symbol = 'UC' AND resolution = '1m' AND timestamp >= ? ORDER BY timestamp ASC LIMIT 1) as open_24h
            FROM market_candles 
            WHERE symbol = 'UC' AND resolution = '1m' AND timestamp >= ?");
        $stmt->execute([$since, $since]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $high = max($currentPrice, (float)($row['high_24h'] ?? $currentPrice));
        $low = min($currentPrice, (float)($row['low_24h'] ?? $currentPrice));
        $vol = (float)($row['vol_24h'] ?? 0);
        $open = (float)($row['open_24h'] ?? $currentPrice);

        $change = 0.0;
        if ($open > 0) {
            $change = round((($currentPrice - $open) / $open) * 100.0, 2);
        }

        $volumeBdt = round($vol * $currentPrice, 2);

        return [
            'high' => $high,
            'low' => $low,
            'volume' => round($vol, 2),
            'volume_bdt' => $volumeBdt,
            'change' => $change
        ];
    }

    /**
     * Place and execute Spot Market Orders (Buy/Sell Trading UC for BDT)
     */
    public static function placeSpotOrder(PDO $db, int $userId, string $side, float $amount, string $orderType = 'market', ?float $limitPrice = null): array {
        if (!in_array($side, ['buy', 'sell'], true)) {
            return ['success' => false, 'error' => 'Invalid spot order side (must be buy or sell).'];
        }

        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Invalid order amount.'];
        }

        $settings = FeeEngine::getSettings($db);
        $minOrder = (float)($settings['min_order_tuc'] ?? 1.0);
        if ($amount < $minOrder) {
            return ['success' => false, 'error' => "Minimum order is {$minOrder} Trading UC."];
        }

        $db->beginTransaction();
        try {
            $mState = $db->query("SELECT price, ask, bid FROM market_state WHERE symbol = 'UC' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $currentPrice = (float)($mState['price'] ?? 2.0000);
            $execPrice = ($side === 'buy') ? (float)($mState['ask'] ?? $currentPrice) : (float)($mState['bid'] ?? $currentPrice);

            $wallet = WalletEngine::getTradingWallet($db, $userId);
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            if ($orderType === 'market') {
                $grossBdt = round($amount * $execPrice, 4);
                $feeBdt = FeeEngine::calculateTakerFee($db, $grossBdt);

                if ($side === 'buy') {
                    $totalRequiredBdt = round($grossBdt + $feeBdt, 4);
                    if ($wallet['available_margin'] < $totalRequiredBdt) {
                        $db->rollBack();
                        return ['success' => false, 'error' => "Insufficient Trading BDT balance. Required: ৳ " . number_format($totalRequiredBdt, 2) . " (Gross: ৳ " . number_format($grossBdt, 2) . " + Fee: ৳ " . number_format($feeBdt, 2) . "), Available: ৳ " . number_format($wallet['available_margin'], 2)];
                    }

                    // Deduct BDT, Credit Trading UC
                    $db->prepare("UPDATE trading_wallets SET 
                        bdt_balance = bdt_balance - ?, 
                        tuc_balance = tuc_balance + ?, 
                        total_trading_fees = total_trading_fees + ?,
                        updated_at = $nowExpr 
                        WHERE user_id = ? AND (bdt_balance - locked_margin) >= CAST(? AS NUMERIC)")
                        ->execute([$totalRequiredBdt, $amount, $feeBdt, $userId, $totalRequiredBdt]);

                    // Record order
                    $oStmt = $db->prepare("INSERT INTO trading_orders (user_id, symbol, side, order_type, price, amount, filled_amount, status, created_at, updated_at)
                        VALUES (?, 'UC/BDT', 'buy', 'market', ?, ?, ?, 'filled', $nowExpr, $nowExpr)");
                    $oStmt->execute([$userId, $execPrice, $amount, $amount]);
                    $orderId = (int)$db->lastInsertId();

                    // Route fee to liquidity pool
                    FeeEngine::routeFeeToLiquidityPool($db, $feeBdt, 'spot_trade', "Spot Buy Order #$orderId: $amount UC @ ৳ $execPrice", $userId, null, $orderId, null);

                    $refMsg = "Spot Bought {$amount} Trading UC @ ৳ " . number_format($execPrice, 4) . " (Total: ৳ " . number_format($totalRequiredBdt, 2) . ")";
                    $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                        VALUES (?, 'spot_buy', ?, 'tUC', ?, ?, ?, $nowExpr)")
                        ->execute([$userId, $amount, $refMsg, $wallet['tuc_balance'], $wallet['tuc_balance'] + $amount]);

                } else { // Spot Sell
                    if ($wallet['tuc_balance'] < $amount) {
                        $db->rollBack();
                        return ['success' => false, 'error' => "Insufficient Trading UC balance. Available: " . number_format($wallet['tuc_balance'], 4) . " UC"];
                    }

                    $netReceivedBdt = round($grossBdt - $feeBdt, 4);

                    // Deduct Trading UC, Credit BDT
                    $db->prepare("UPDATE trading_wallets SET 
                        tuc_balance = tuc_balance - ?, 
                        bdt_balance = bdt_balance + ?, 
                        total_trading_fees = total_trading_fees + ?,
                        updated_at = $nowExpr 
                        WHERE user_id = ? AND tuc_balance >= CAST(? AS NUMERIC)")
                        ->execute([$amount, $netReceivedBdt, $feeBdt, $userId, $amount]);

                    // Record order
                    $oStmt = $db->prepare("INSERT INTO trading_orders (user_id, symbol, side, order_type, price, amount, filled_amount, status, created_at, updated_at)
                        VALUES (?, 'UC/BDT', 'sell', 'market', ?, ?, ?, 'filled', $nowExpr, $nowExpr)");
                    $oStmt->execute([$userId, $execPrice, $amount, $amount]);
                    $orderId = (int)$db->lastInsertId();

                    FeeEngine::routeFeeToLiquidityPool($db, $feeBdt, 'spot_trade', "Spot Sell Order #$orderId: $amount UC @ ৳ $execPrice", $userId, null, $orderId, null);

                    $refMsg = "Spot Sold {$amount} Trading UC @ ৳ " . number_format($execPrice, 4) . " (Received: ৳ " . number_format($netReceivedBdt, 2) . ")";
                    $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                        VALUES (?, 'spot_sell', ?, 'tUC', ?, ?, ?, $nowExpr)")
                        ->execute([$userId, -$amount, $refMsg, $wallet['tuc_balance'], $wallet['tuc_balance'] - $amount]);
                }

                $db->commit();
                return [
                    'success' => true,
                    'message' => "Spot " . strtoupper($side) . " order executed successfully at ৳ {$execPrice}.",
                    'order' => [
                        'id' => $orderId,
                        'side' => $side,
                        'amount' => $amount,
                        'price' => $execPrice,
                        'gross_bdt' => $grossBdt,
                        'fee_bdt' => $feeBdt,
                        'status' => 'filled'
                    ],
                    'balances' => WalletEngine::getComprehensiveBalances($db, $userId)
                ];

            } else { // Limit Order Placement
                if ($limitPrice === null || $limitPrice <= 0) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'Valid Limit Price is required.'];
                }

                $grossBdt = round($amount * $limitPrice, 4);

                if ($side === 'buy') {
                    if ($wallet['available_margin'] < $grossBdt) {
                        $db->rollBack();
                        return ['success' => false, 'error' => "Insufficient available margin to place limit buy order. Required: ৳ " . number_format($grossBdt, 2)];
                    }

                    // Lock margin for limit buy
                    $db->prepare("UPDATE trading_wallets SET locked_margin = locked_margin + ?, updated_at = $nowExpr WHERE user_id = ? AND (bdt_balance - locked_margin) >= CAST(? AS NUMERIC)")
                        ->execute([$grossBdt, $userId, $grossBdt]);

                } else { // Limit Sell
                    if ($wallet['tuc_balance'] < $amount) {
                        $db->rollBack();
                        return ['success' => false, 'error' => "Insufficient Trading UC balance to place limit sell order."];
                    }

                    // Deduct spot UC to escrow for limit sell
                    $db->prepare("UPDATE trading_wallets SET tuc_balance = tuc_balance - ?, updated_at = $nowExpr WHERE user_id = ? AND tuc_balance >= CAST(? AS NUMERIC)")
                        ->execute([$amount, $userId, $amount]);
                }

                $oStmt = $db->prepare("INSERT INTO trading_orders (user_id, symbol, side, order_type, price, amount, margin, status, created_at, updated_at)
                    VALUES (?, 'UC/BDT', ?, 'limit', ?, ?, ?, 'open', $nowExpr, $nowExpr)");
                $oStmt->execute([$userId, $side, $limitPrice, $amount, $grossBdt]);
                $orderId = (int)$db->lastInsertId();

                $db->commit();
                return [
                    'success' => true,
                    'message' => "Limit " . strtoupper($side) . " order placed at ৳ " . number_format($limitPrice, 4) . ".",
                    'order' => [
                        'id' => $orderId,
                        'side' => $side,
                        'order_type' => 'limit',
                        'amount' => $amount,
                        'price' => $limitPrice,
                        'status' => 'open'
                    ],
                    'balances' => WalletEngine::getComprehensiveBalances($db, $userId)
                ];
            }

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("placeSpotOrder Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Order failed: ' . $e->getMessage()];
        }
    }

    /**
     * Cancel an Open Limit Order
     */
    public static function cancelOrder(PDO $db, int $userId, int $orderId): array {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM trading_orders WHERE id = ? AND user_id = ? AND status = 'open' LIMIT 1");
            $stmt->execute([$orderId, $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Order not found or not in open status.'];
            }

            $side = $order['side'];
            $amount = (float)$order['amount'];
            $margin = (float)$order['margin'];

            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            if ($side === 'buy' || $side === 'long') {
                // Unlock locked margin
                $db->prepare("UPDATE trading_wallets SET locked_margin = MAX(0, locked_margin - ?), updated_at = $nowExpr WHERE user_id = ?")
                    ->execute([$margin, $userId]);
            } elseif ($side === 'sell') {
                // Return escrowed spot Trading UC
                $db->prepare("UPDATE trading_wallets SET tuc_balance = tuc_balance + ?, updated_at = $nowExpr WHERE user_id = ?")
                    ->execute([$amount, $userId]);
            }

            $db->prepare("UPDATE trading_orders SET status = 'cancelled', updated_at = $nowExpr WHERE id = ?")->execute([$orderId]);

            $db->commit();
            return [
                'success' => true,
                'message' => "Order #{$orderId} cancelled successfully.",
                'balances' => WalletEngine::getComprehensiveBalances($db, $userId)
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("cancelOrder Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to cancel order: ' . $e->getMessage()];
        }
    }

    /**
     * Match and fill open limit orders when price crosses limit threshold
     */
    private static function matchLimitOrders(PDO $db, float $currentPrice): void {
        $stmt = $db->query("SELECT * FROM trading_orders WHERE status = 'open' AND order_type = 'limit'");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        foreach ($orders as $order) {
            $orderId = (int)$order['id'];
            $userId = (int)$order['user_id'];
            $side = $order['side'];
            $targetPrice = (float)$order['price'];
            $amount = (float)$order['amount'];
            $margin = (float)$order['margin'];

            $filled = false;
            if ($side === 'buy' && $currentPrice <= $targetPrice) {
                $filled = true;
            } elseif ($side === 'sell' && $currentPrice >= $targetPrice) {
                $filled = true;
            }

            if ($filled) {
                $execPrice = $currentPrice;
                $grossBdt = round($amount * $execPrice, 4);
                $feeBdt = FeeEngine::calculateMakerFee($db, $grossBdt);

                if ($side === 'buy') {
                    $refundMargin = max(0.0, round($margin - ($grossBdt + $feeBdt), 4));

                    $db->prepare("UPDATE trading_wallets SET 
                        locked_margin = MAX(0, locked_margin - ?),
                        bdt_balance = bdt_balance + ?,
                        tuc_balance = tuc_balance + ?,
                        total_trading_fees = total_trading_fees + ?,
                        updated_at = $nowExpr 
                        WHERE user_id = ?")
                        ->execute([$margin, $refundMargin, $amount, $feeBdt, $userId]);

                    FeeEngine::routeFeeToLiquidityPool($db, $feeBdt, 'maker_trade', "Limit Buy Fill #$orderId: $amount UC @ ৳ $execPrice", $userId, null, $orderId, null);

                } else { // Limit sell
                    $netBdt = round($grossBdt - $feeBdt, 4);
                    $db->prepare("UPDATE trading_wallets SET 
                        bdt_balance = bdt_balance + ?, 
                        total_trading_fees = total_trading_fees + ?,
                        updated_at = $nowExpr 
                        WHERE user_id = ?")
                        ->execute([$netBdt, $feeBdt, $userId]);

                    FeeEngine::routeFeeToLiquidityPool($db, $feeBdt, 'maker_trade', "Limit Sell Fill #$orderId: $amount UC @ ৳ $execPrice", $userId, null, $orderId, null);
                }

                $db->prepare("UPDATE trading_orders SET filled_amount = ?, status = 'filled', updated_at = $nowExpr WHERE id = ?")
                    ->execute([$amount, $orderId]);
            }
        }
    }

    /**
     * Check and trigger Stop Loss & Take Profit orders automatically
     */
    private static function checkStopLossTakeProfit(PDO $db, float $currentPrice): void {
        $stmt = $db->query("SELECT id, user_id, side, stop_loss, take_profit FROM trading_positions WHERE status = 'open'");
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($positions as $pos) {
            $posId = (int)$pos['id'];
            $uid = (int)$pos['user_id'];
            $side = $pos['side'];
            $sl = ($pos['stop_loss'] !== null && (float)$pos['stop_loss'] > 0) ? (float)$pos['stop_loss'] : null;
            $tp = ($pos['take_profit'] !== null && (float)$pos['take_profit'] > 0) ? (float)$pos['take_profit'] : null;

            $triggeredReason = null;

            if ($side === 'long') {
                if ($sl !== null && $currentPrice <= $sl) {
                    $triggeredReason = 'stop_loss';
                } elseif ($tp !== null && $currentPrice >= $tp) {
                    $triggeredReason = 'take_profit';
                }
            } else { // short
                if ($sl !== null && $currentPrice >= $sl) {
                    $triggeredReason = 'stop_loss';
                } elseif ($tp !== null && $currentPrice <= $tp) {
                    $triggeredReason = 'take_profit';
                }
            }

            if ($triggeredReason !== null) {
                PositionEngine::closePosition($db, $uid, $posId, $triggeredReason);
            }
        }
    }

    /**
     * Get authoritative market state
     */
    public static function getMarketState(PDO $db): array {
        $stmt = $db->query("SELECT * FROM market_state WHERE symbol = 'UC' LIMIT 1");
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$state) {
            return self::heartbeat($db, true);
        }

        return [
            'symbol' => 'UC/BDT',
            'name' => 'Trading UC (tUC)',
            'price' => (float)$state['price'],
            'bid' => (float)$state['bid'],
            'ask' => (float)$state['ask'],
            'open_24h' => (float)$state['open_24h'],
            'high_24h' => (float)$state['high_24h'],
            'low_24h' => (float)$state['low_24h'],
            'change_24h' => (float)$state['change_24h'],
            'volume_24h' => (float)$state['volume_24h'],
            'volume_bdt_24h' => (float)$state['volume_bdt_24h'],
            'status' => $state['status'] ?? 'active',
            'updated_at' => $state['updated_at']
        ];
    }

    /**
     * Get recent market trades / ticks
     */
    public static function getRecentTrades(PDO $db, int $limit = 30): array {
        $stmt = $db->prepare("SELECT id, price, volume, side, created_at FROM market_ticks WHERE symbol = 'UC' ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
