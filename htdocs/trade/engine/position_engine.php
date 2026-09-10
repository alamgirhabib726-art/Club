<?php
/**
 * ============================================
 * UC POSITION ENGINE (ISOLATED)
 * ============================================
 */

if (!defined('UC_ENGINE')) {
    define('UC_ENGINE', true);
}

if (!function_exists('open_position')) {
    function open_position(int $uid, float $margin, int $leverage, int $direction, float $price): array
    {
        if ($margin <= 0 || $leverage <= 0 || $price <= 0) {
            return ['error' => 'Invalid trade parameters'];
        }

        $feePercent = 0.001; // 0.1% fee
        $positionSize = ($margin * $leverage) / $price;
        $fee = ($margin * $leverage) * $feePercent;

        // Liquidation distance: approx 80% loss of margin
        $liqDistance = ($price * (0.8 / $leverage));
        $liquidationPx = ($direction === 1) ? ($price - $liqDistance) : ($price + $liqDistance);
        if ($liquidationPx < 0) {
            $liquidationPx = 0.01;
        }

        return [
            'margin'         => $margin,
            'leverage'       => $leverage,
            'direction'      => $direction,
            'entry_price'    => $price,
            'position_size'  => $positionSize,
            'fee'            => $fee,
            'liquidation_px' => round($liquidationPx, 4)
        ];
    }
}

if (!function_exists('close_position')) {
    function close_position(array $pos, float $price): float
    {
        $direction = (int)($pos['direction'] ?? 1);
        $entryPrice = (float)($pos['entry_price'] ?? $price);
        $size = (float)($pos['position_size'] ?? 0);

        if ($direction === 1) {
            $pnl = ($price - $entryPrice) * $size;
        } else {
            $pnl = ($entryPrice - $price) * $size;
        }

        return round($pnl, 4);
    }
}
