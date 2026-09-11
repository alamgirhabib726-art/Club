<?php
/**
 * UNMOOR CLUB - POSITION ENGINE
 * Handles Leveraged Futures Positions (1x - 100x), Margin, Liquidation Price,
 * PnL, SL/TP management, and atomic settlement.
 */

if (!defined('UC_TRADING_CORE')) {
    define('UC_TRADING_CORE', true);
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/fee_engine.php';
require_once __DIR__ . '/wallet_engine.php';

class PositionEngine {

    /**
     * Calculate position size in Trading UC
     */
    public static function calculatePositionSize(float $marginBdt, int $leverage, float $entryPrice): float {
        if ($entryPrice <= 0) return 0.0;
        $notional = $marginBdt * $leverage;
        return round($notional / $entryPrice, 4);
    }

    /**
     * Calculate liquidation price based on maintenance margin threshold
     */
    public static function calculateLiquidationPrice(string $side, float $entryPrice, int $leverage, float $mmRate = 0.0050): float {
        if ($leverage <= 0 || $entryPrice <= 0) return 0.0;

        if ($side === 'long') {
            $liq = $entryPrice * (1.0 - ((1.0 - $mmRate) / $leverage));
            return max(0.0100, round($liq, 4));
        } else {
            $liq = $entryPrice * (1.0 + ((1.0 - $mmRate) / $leverage));
            return round($liq, 4);
        }
    }

    /**
     * Calculate gross unrealized/realized PnL in BDT
     */
    public static function calculatePnL(string $side, float $entryPrice, float $currentPrice, float $positionSize): float {
        if ($side === 'long') {
            return round(($currentPrice - $entryPrice) * $positionSize, 4);
        } else {
            return round(($entryPrice - $currentPrice) * $positionSize, 4);
        }
    }

    /**
     * Calculate Return on Investment percentage
     */
    public static function calculateRoi(float $pnl, float $margin): float {
        if ($margin <= 0) return 0.0;
        return round(($pnl / $margin) * 100.0, 2);
    }

    /**
     * Open a Leveraged Position
     */
    public static function openPosition(
        PDO $db,
        int $userId,
        string $side,
        float $margin,
        int $leverage,
        ?float $stopLoss = null,
        ?float $takeProfit = null
    ): array {
        $settings = FeeEngine::getSettings($db);

        if ((int)($settings['trading_enabled'] ?? 1) !== 1) {
            return ['success' => false, 'error' => 'Trading is temporarily paused for maintenance.'];
        }

        if (!in_array($side, ['long', 'short'], true)) {
            return ['success' => false, 'error' => 'Invalid position side (must be long or short).'];
        }

        $minMargin = (float)($settings['min_margin_bdt'] ?? 10.0);
        if ($margin < $minMargin) {
            return ['success' => false, 'error' => "Minimum margin required is ৳ " . number_format($minMargin, 2) . " BDT."];
        }

        $maxLeverage = (int)($settings['max_leverage'] ?? 100);
        if ($leverage < 1 || $leverage > $maxLeverage) {
            return ['success' => false, 'error' => "Leverage must be between 1x and {$maxLeverage}x."];
        }

        $db->beginTransaction();
        try {
            // 1. Fetch current market price
            $mState = $db->query("SELECT price, status FROM market_state WHERE symbol = 'UC' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$mState || ($mState['status'] ?? 'active') !== 'active') {
                $db->rollBack();
                return ['success' => false, 'error' => 'Market is currently halted by circuit breaker.'];
            }
            $entryPrice = (float)$mState['price'];

            // 2. Calculate fees
            $notional = $margin * $leverage;
            $takerFee = FeeEngine::calculateTakerFee($db, $notional);
            $leverageFee = FeeEngine::calculateLeverageFee($db, $margin, $leverage);
            $totalEntryFee = round($takerFee + $leverageFee, 4);

            // 3. Check and lock wallet balance
            $wallet = WalletEngine::getTradingWallet($db, $userId);
            $requiredTotal = round($margin + $totalEntryFee, 4);

            if ($wallet['available_margin'] < $requiredTotal) {
                $db->rollBack();
                return [
                    'success' => false,
                    'error' => "Insufficient available margin. Required: ৳ " . number_format($requiredTotal, 2) . " (Margin: ৳ " . number_format($margin, 2) . " + Fee: ৳ " . number_format($totalEntryFee, 2) . "), Available: ৳ " . number_format($wallet['available_margin'], 2)
                ];
            }

            // 4. Calculate position metrics
            $positionSize = self::calculatePositionSize($margin, $leverage, $entryPrice);
            $mmRate = (float)($settings['maintenance_margin_rate'] ?? 0.0050);
            $liqPrice = self::calculateLiquidationPrice($side, $entryPrice, $leverage, $mmRate);

            // Validate SL / TP if provided
            if ($stopLoss !== null && $stopLoss > 0) {
                if ($side === 'long' && $stopLoss >= $entryPrice) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'For Long positions, Stop Loss must be below entry price.'];
                }
                if ($side === 'short' && $stopLoss <= $entryPrice) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'For Short positions, Stop Loss must be above entry price.'];
                }
            } else {
                $stopLoss = null;
            }

            if ($takeProfit !== null && $takeProfit > 0) {
                if ($side === 'long' && $takeProfit <= $entryPrice) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'For Long positions, Take Profit must be above entry price.'];
                }
                if ($side === 'short' && $takeProfit >= $entryPrice) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'For Short positions, Take Profit must be below entry price.'];
                }
            } else {
                $takeProfit = null;
            }

            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            // 5. Update user trading wallet
            $updWallet = $db->prepare("UPDATE trading_wallets SET 
                bdt_balance = bdt_balance - ?, 
                locked_margin = locked_margin + ?,
                total_trading_fees = total_trading_fees + ?,
                updated_at = $nowExpr 
                WHERE user_id = ? AND (bdt_balance - locked_margin) >= CAST(? AS NUMERIC)");
            $updWallet->execute([$totalEntryFee, $margin, $totalEntryFee, $userId, $requiredTotal]);

            if ($updWallet->rowCount() === 0) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Margin allocation conflict. Please retry.'];
            }

            // 6. Insert open position
            $posStmt = $db->prepare("INSERT INTO trading_positions (
                user_id, symbol, side, entry_price, current_price, position_size, margin, leverage,
                liquidation_price, stop_loss, take_profit, entry_fee, exit_fee, pnl, roi, status, created_at, updated_at
            ) VALUES (?, 'UC/BDT', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.0000, 0.0000, 0.0000, 'open', $nowExpr, $nowExpr)");
            $posStmt->execute([
                $userId,
                $side,
                $entryPrice,
                $entryPrice,
                $positionSize,
                $margin,
                $leverage,
                $liqPrice,
                $stopLoss,
                $takeProfit,
                $totalEntryFee
            ]);
            $positionId = (int)$db->lastInsertId();

            // 7. Route entry fee to Liquidity Pool
            FeeEngine::routeFeeToLiquidityPool(
                $db,
                $totalEntryFee,
                'trade_entry',
                "Position #$positionId open: {$leverage}x " . strtoupper($side) . " {$positionSize} UC @ ৳ {$entryPrice}",
                $userId,
                $positionId,
                null,
                null,
                (float)($settings['taker_fee_rate'] ?? 0.0010)
            );

            // 8. Log transactions
            $refMsg = "Opened {$leverage}x " . strtoupper($side) . " Position #$positionId: Margin ৳ " . number_format($margin, 2) . " (Fee ৳ " . number_format($totalEntryFee, 2) . ")";
            $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                VALUES (?, 'position_open', ?, 'BDT', ?, ?, ?, $nowExpr)")
                ->execute([$userId, -$totalEntryFee, $refMsg, $wallet['bdt_balance'], $wallet['bdt_balance'] - $totalEntryFee]);

            $db->commit();

            return [
                'success' => true,
                'message' => "Successfully opened {$leverage}x " . strtoupper($side) . " position at ৳ {$entryPrice}.",
                'position' => [
                    'id' => $positionId,
                    'symbol' => 'UC/BDT',
                    'side' => $side,
                    'entry_price' => $entryPrice,
                    'position_size' => $positionSize,
                    'margin' => $margin,
                    'leverage' => $leverage,
                    'liquidation_price' => $liqPrice,
                    'stop_loss' => $stopLoss,
                    'take_profit' => $takeProfit,
                    'entry_fee' => $totalEntryFee,
                    'status' => 'open'
                ],
                'balances' => WalletEngine::getComprehensiveBalances($db, $userId)
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("openPosition Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to open position: ' . $e->getMessage()];
        }
    }

    /**
     * Close an Active Position (Market Close)
     */
    public static function closePosition(PDO $db, int $userId, int $positionId, string $closeReason = 'user_close'): array {
        $db->beginTransaction();
        try {
            // 1. Fetch position with lock
            $posStmt = $db->prepare("SELECT * FROM trading_positions WHERE id = ? AND user_id = ? AND status = 'open' LIMIT 1");
            $posStmt->execute([$positionId, $userId]);
            $pos = $posStmt->fetch(PDO::FETCH_ASSOC);

            if (!$pos) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Active position not found or already closed.'];
            }

            // 2. Fetch market price
            $mState = $db->query("SELECT price FROM market_state WHERE symbol = 'UC' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $exitPrice = (float)($mState['price'] ?? $pos['entry_price']);

            $side = $pos['side'];
            $entryPrice = (float)$pos['entry_price'];
            $size = (float)$pos['position_size'];
            $margin = (float)$pos['margin'];
            $leverage = (int)$pos['leverage'];

            // 3. Calculate PnL & exit fee
            $grossPnl = self::calculatePnL($side, $entryPrice, $exitPrice, $size);
            $roi = self::calculateRoi($grossPnl, $margin);

            $notional = $margin * $leverage;
            $exitFee = FeeEngine::calculateTakerFee($db, $notional);

            // Net balance delta = PnL - exitFee (unlocked margin is returned)
            $netBalanceDelta = round($grossPnl - $exitFee, 4);

            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            // 4. Update wallet balances
            $updWallet = $db->prepare("UPDATE trading_wallets SET 
                bdt_balance = bdt_balance + ?, 
                locked_margin = locked_margin - ?,
                realized_pnl = realized_pnl + ?,
                total_trading_fees = total_trading_fees + ?,
                updated_at = $nowExpr 
                WHERE user_id = ? AND locked_margin >= CAST(? AS NUMERIC)");
            $updWallet->execute([$netBalanceDelta, $margin, $grossPnl, $exitFee, $userId, $margin]);

            if ($updWallet->rowCount() === 0) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Failed to settle position margin.'];
            }

            // 5. Update position record
            $updPos = $db->prepare("UPDATE trading_positions SET 
                exit_price = ?,
                exit_fee = ?,
                pnl = ?,
                roi = ?,
                status = 'closed',
                close_reason = ?,
                closed_at = $nowExpr,
                updated_at = $nowExpr 
                WHERE id = ?");
            $updPos->execute([$exitPrice, $exitFee, $grossPnl, $roi, $closeReason, $positionId]);

            // 6. Route exit fee to Liquidity Pool & record counterparty PnL
            FeeEngine::routeFeeToLiquidityPool(
                $db,
                $exitFee,
                'trade_exit',
                "Position #$positionId closed @ ৳ {$exitPrice}: PnL ৳ " . number_format($grossPnl, 2),
                $userId,
                $positionId,
                null,
                null
            );

            // Counterparty PnL: if user profits (+PnL), pool pays (-PnL); if user loses (-PnL), pool earns (+PnL)
            $poolCounterpartyDelta = -$grossPnl;
            $db->prepare("UPDATE liquidity_pool SET 
                bdt_reserve = bdt_reserve + ?, 
                realized_pnl_bdt = realized_pnl_bdt + ?,
                updated_at = $nowExpr 
                WHERE id = 1")->execute([$poolCounterpartyDelta, $poolCounterpartyDelta]);

            // 7. Log transactions
            $refMsg = "Closed Position #$positionId @ ৳ " . number_format($exitPrice, 4) . ": PnL ৳ " . number_format($grossPnl, 2) . " (ROI {$roi}%, Exit Fee ৳ " . number_format($exitFee, 2) . ")";
            $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                VALUES (?, 'position_close', ?, 'BDT', ?, 0, 0, $nowExpr)")
                ->execute([$userId, $netBalanceDelta, $refMsg]);

            $db->commit();

            return [
                'success' => true,
                'message' => "Position #{$positionId} successfully closed at ৳ {$exitPrice}. Realized PnL: ৳ " . number_format($grossPnl, 2) . " ({$roi}%).",
                'settlement' => [
                    'position_id' => $positionId,
                    'exit_price' => $exitPrice,
                    'gross_pnl' => $grossPnl,
                    'exit_fee' => $exitFee,
                    'net_settlement' => round($margin + $netBalanceDelta, 4),
                    'roi_pct' => $roi . '%'
                ],
                'balances' => WalletEngine::getComprehensiveBalances($db, $userId)
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("closePosition Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to close position: ' . $e->getMessage()];
        }
    }

    /**
     * Update Stop Loss / Take Profit on an Open Position
     */
    public static function updateSlTp(PDO $db, int $userId, int $positionId, ?float $sl, ?float $tp): array {
        $posStmt = $db->prepare("SELECT id, side, entry_price FROM trading_positions WHERE id = ? AND user_id = ? AND status = 'open' LIMIT 1");
        $posStmt->execute([$positionId, $userId]);
        $pos = $posStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pos) {
            return ['success' => false, 'error' => 'Position not found or not open.'];
        }

        $side = $pos['side'];
        $entry = (float)$pos['entry_price'];

        if ($sl !== null && $sl > 0) {
            if ($side === 'long' && $sl >= $entry) {
                return ['success' => false, 'error' => 'For Long positions, Stop Loss must be below entry price.'];
            }
            if ($side === 'short' && $sl <= $entry) {
                return ['success' => false, 'error' => 'For Short positions, Stop Loss must be above entry price.'];
            }
        } else {
            $sl = null;
        }

        if ($tp !== null && $tp > 0) {
            if ($side === 'long' && $tp <= $entry) {
                return ['success' => false, 'error' => 'For Long positions, Take Profit must be above entry price.'];
            }
            if ($side === 'short' && $tp >= $entry) {
                return ['success' => false, 'error' => 'For Short positions, Take Profit must be below entry price.'];
            }
        } else {
            $tp = null;
        }

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        $upd = $db->prepare("UPDATE trading_positions SET stop_loss = ?, take_profit = ?, updated_at = $nowExpr WHERE id = ?");
        $upd->execute([$sl, $tp, $positionId]);

        return [
            'success' => true,
            'message' => 'Stop Loss & Take Profit successfully updated.',
            'position_id' => $positionId,
            'stop_loss' => $sl,
            'take_profit' => $tp
        ];
    }
}
