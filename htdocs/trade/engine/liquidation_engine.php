<?php
/**
 * UNMOOR CLUB - LIQUIDATION ENGINE
 * Scans active positions against mark price, executes transparent liquidations,
 * records liquidation audits, and routes liquidation fees to the Liquidity Pool.
 */

if (!defined('UC_TRADING_CORE')) {
    define('UC_TRADING_CORE', true);
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/fee_engine.php';

class LiquidationEngine {

    /**
     * Scan and process all underwater positions crossing liquidation threshold
     */
    public static function processLiquidations(PDO $db, float $currentPrice): int {
        $stmt = $db->query("SELECT * FROM trading_positions WHERE status = 'open'");
        $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $liquidatedCount = 0;
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        foreach ($positions as $pos) {
            $posId = (int)$pos['id'];
            $userId = (int)$pos['user_id'];
            $side = $pos['side'];
            $entryPrice = (float)$pos['entry_price'];
            $liqPrice = (float)$pos['liquidation_price'];
            $margin = (float)$pos['margin'];
            $leverage = (int)$pos['leverage'];
            $size = (float)$pos['position_size'];

            $isLiquidated = false;
            if ($side === 'long' && $currentPrice <= $liqPrice) {
                $isLiquidated = true;
            } elseif ($side === 'short' && $currentPrice >= $liqPrice) {
                $isLiquidated = true;
            }

            if ($isLiquidated) {
                try {
                    $liqFee = FeeEngine::calculateLiquidationFee($db, $margin);
                    $lossAmount = $margin;

                    // 1. Release locked margin & record realized loss in wallet
                    $updWallet = $db->prepare("UPDATE trading_wallets SET 
                        locked_margin = MAX(0, locked_margin - ?),
                        realized_pnl = realized_pnl - ?,
                        total_trading_fees = total_trading_fees + ?,
                        updated_at = $nowExpr 
                        WHERE user_id = ?");
                    $updWallet->execute([$margin, $lossAmount, $liqFee, $userId]);

                    // 2. Mark position as liquidated
                    $updPos = $db->prepare("UPDATE trading_positions SET 
                        exit_price = ?,
                        exit_fee = ?,
                        pnl = ?,
                        roi = -100.00,
                        status = 'liquidated',
                        close_reason = 'liquidation',
                        closed_at = $nowExpr,
                        updated_at = $nowExpr 
                        WHERE id = ?");
                    $updPos->execute([$currentPrice, $liqFee, -$lossAmount, $posId]);

                    // 3. Route liquidation fee to Liquidity Pool
                    FeeEngine::routeFeeToLiquidityPool(
                        $db,
                        $liqFee,
                        'liquidation',
                        "Liquidation Position #$posId @ ৳ $currentPrice: Margin ৳ $margin lost",
                        $userId,
                        $posId,
                        null,
                        null
                    );

                    // Credit remainder of margin to liquidity pool reserves (pool is counterparty)
                    $poolGain = round($margin - $liqFee, 4);
                    if ($poolGain > 0) {
                        $db->prepare("UPDATE liquidity_pool SET 
                            bdt_reserve = bdt_reserve + ?, 
                            realized_pnl_bdt = realized_pnl_bdt + ?,
                            updated_at = $nowExpr 
                            WHERE id = 1")->execute([$poolGain, $poolGain]);
                    }

                    // 4. Insert liquidation audit event
                    $liqAudit = $db->prepare("INSERT INTO liquidation_events (
                        position_id, user_id, symbol, side, entry_price, mark_price, liquidation_price, margin, loss_amount, fee_amount, settled_at
                    ) VALUES (?, ?, 'UC/BDT', ?, ?, ?, ?, ?, ?, ?, $nowExpr)");
                    $liqAudit->execute([
                        $posId,
                        $userId,
                        $side,
                        $entryPrice,
                        $currentPrice,
                        $liqPrice,
                        $margin,
                        $lossAmount,
                        $liqFee
                    ]);

                    // 5. Send in-app notification to user
                    $notif = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                        VALUES (?, '⚠️ Position Liquidated', ?, 'warning', 0, $nowExpr)");
                    $notifMsg = "Your {$leverage}x " . strtoupper($side) . " position (#$posId) was liquidated at ৳ " . number_format($currentPrice, 4) . " (Liquidation Price: ৳ " . number_format($liqPrice, 4) . "). Margin of ৳ " . number_format($margin, 2) . " was settled.";
                    $notif->execute([$userId, $notifMsg]);

                    // 6. Log transaction
                    $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                        VALUES (?, 'liquidation', ?, 'BDT', ?, 0, 0, $nowExpr)")
                        ->execute([$userId, -$lossAmount, $notifMsg]);

                    $liquidatedCount++;

                } catch (Throwable $e) {
                    error_log("LiquidationEngine error for pos #$posId: " . $e->getMessage());
                }
            }
        }

        return $liquidatedCount;
    }
}
