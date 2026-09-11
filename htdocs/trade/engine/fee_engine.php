<?php
/**
 * UNMOOR CLUB - CENTRALIZED TRADING FEE ENGINE
 * Single source of truth for trading fees, conversion fees, and liquidity pool fee routing.
 */

if (!defined('UC_TRADING_CORE')) {
    define('UC_TRADING_CORE', true);
}

require_once __DIR__ . '/../../db.php';

class FeeEngine {
    private static ?array $settingsCache = null;
    private static int $lastSettingsFetch = 0;

    /**
     * Get all active trading settings from database with short caching
     */
    public static function getSettings(PDO $db): array {
        $now = time();
        if (self::$settingsCache !== null && ($now - self::$lastSettingsFetch) < 10) {
            return self::$settingsCache;
        }

        $defaults = [
            'initial_price' => 2.0000,
            'maker_fee_rate' => 0.0005, // 0.05%
            'taker_fee_rate' => 0.0010, // 0.10%
            'conversion_fee_rate' => 0.01345, // 1.345%
            'leverage_fee_rate_per_10x' => 0.0001, // 0.01% per 10x
            'liquidation_fee_rate' => 0.0100, // 1.00%
            'maintenance_margin_rate' => 0.0050, // 0.50%
            'min_margin_bdt' => 10.00,
            'max_leverage' => 100,
            'min_order_tuc' => 1.0,
            'trading_enabled' => 1,
            'market_status' => 'active',
            'volatility_factor' => 0.0035,
            'tick_interval_sec' => 2
        ];

        try {
            $stmt = $db->query("SELECT setting_key, setting_value FROM trading_settings");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if ($rows) {
                foreach ($rows as $k => $v) {
                    if (is_numeric($v)) {
                        $defaults[$k] = (float)$v;
                    } else {
                        $defaults[$k] = $v;
                    }
                }
            }
        } catch (Throwable $e) {}

        self::$settingsCache = $defaults;
        self::$lastSettingsFetch = $now;
        return self::$settingsCache;
    }

    /**
     * Calculate Taker Fee (market orders, position opens/closes)
     */
    public static function calculateTakerFee(PDO $db, float $notionalBdt): float {
        $settings = self::getSettings($db);
        $rate = (float)($settings['taker_fee_rate'] ?? 0.0010);
        return round($notionalBdt * $rate, 4);
    }

    /**
     * Calculate Maker Fee (limit order fills)
     */
    public static function calculateMakerFee(PDO $db, float $notionalBdt): float {
        $settings = self::getSettings($db);
        $rate = (float)($settings['maker_fee_rate'] ?? 0.0005);
        return round($notionalBdt * $rate, 4);
    }

    /**
     * Calculate Leverage Risk Surcharge Fee
     */
    public static function calculateLeverageFee(PDO $db, float $marginBdt, int $leverage): float {
        $settings = self::getSettings($db);
        $ratePer10x = (float)($settings['leverage_fee_rate_per_10x'] ?? 0.0001);
        $effectiveRate = ($leverage / 10.0) * $ratePer10x;
        return round($marginBdt * $effectiveRate, 4);
    }

    /**
     * Calculate Conversion Fee (1.345% fixed or configured)
     */
    public static function calculateConversionFee(PDO $db, float $grossAmount): array {
        $settings = self::getSettings($db);
        $rate = (float)($settings['conversion_fee_rate'] ?? 0.01345);
        $fee = round($grossAmount * $rate, 4);
        $net = round($grossAmount - $fee, 4);
        return [
            'rate' => $rate,
            'fee' => $fee,
            'net' => $net
        ];
    }

    /**
     * Calculate Liquidation Fee
     */
    public static function calculateLiquidationFee(PDO $db, float $marginBdt): float {
        $settings = self::getSettings($db);
        $rate = (float)($settings['liquidation_fee_rate'] ?? 0.0100);
        return round($marginBdt * $rate, 4);
    }

    /**
     * Route and credit collected fee directly to Liquidity Pool & Ledger
     */
    public static function routeFeeToLiquidityPool(
        PDO $db,
        float $feeAmountBdt,
        string $feeType,
        string $reference,
        ?int $userId = null,
        ?int $positionId = null,
        ?int $orderId = null,
        ?int $conversionId = null,
        float $rate = 0.0
    ): bool {
        if ($feeAmountBdt <= 0) {
            return true;
        }

        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            // Fetch current pool reserve
            $pool = $db->query("SELECT id, bdt_reserve, total_fee_revenue_bdt, conversion_fee_revenue_bdt FROM liquidity_pool WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$pool) {
                $db->exec("INSERT INTO liquidity_pool (id, bdt_reserve, tuc_reserve, total_fee_revenue_bdt, conversion_fee_revenue_bdt, realized_pnl_bdt, status)
                    VALUES (1, 500000.0000, 250000.0000, 0.0000, 0.0000, 0.0000, 'active')");
                $pool = ['bdt_reserve' => 500000.0, 'total_fee_revenue_bdt' => 0.0, 'conversion_fee_revenue_bdt' => 0.0];
            }

            $currentReserve = (float)($pool['bdt_reserve'] ?? 0);
            $newReserve = $currentReserve + $feeAmountBdt;

            if ($feeType === 'conversion') {
                $upd = $db->prepare("UPDATE liquidity_pool SET 
                    bdt_reserve = bdt_reserve + ?, 
                    total_fee_revenue_bdt = total_fee_revenue_bdt + ?,
                    conversion_fee_revenue_bdt = conversion_fee_revenue_bdt + ?,
                    updated_at = $nowExpr 
                    WHERE id = 1");
                $upd->execute([$feeAmountBdt, $feeAmountBdt, $feeAmountBdt]);
            } else {
                $upd = $db->prepare("UPDATE liquidity_pool SET 
                    bdt_reserve = bdt_reserve + ?, 
                    total_fee_revenue_bdt = total_fee_revenue_bdt + ?,
                    updated_at = $nowExpr 
                    WHERE id = 1");
                $upd->execute([$feeAmountBdt, $feeAmountBdt]);
            }

            // Insert into liquidity_ledger
            $led = $db->prepare("INSERT INTO liquidity_ledger (type, amount, asset, balance_before, balance_after, source, reference, created_at)
                VALUES (?, ?, 'BDT', ?, ?, ?, ?, $nowExpr)");
            $led->execute([$feeType . '_fee', $feeAmountBdt, $currentReserve, $newReserve, 'Trading Fee Engine', $reference]);

            // Insert into trading_fees audit log
            if ($userId !== null) {
                $feeStmt = $db->prepare("INSERT INTO trading_fees (user_id, position_id, order_id, conversion_id, fee_type, amount, asset, rate, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'BDT', ?, $nowExpr)");
                $feeStmt->execute([$userId, $positionId, $orderId, $conversionId, $feeType, $feeAmountBdt, $rate]);
            }

            return true;
        } catch (Throwable $e) {
            error_log("FeeEngine::routeFeeToLiquidityPool Error: " . $e->getMessage());
            return false;
        }
    }
}
