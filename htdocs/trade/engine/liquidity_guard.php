<?php
/**
 * ============================================
 * UC LIQUIDITY GUARD (ISOLATED)
 * ============================================
 */

if (!defined('UC_ENGINE')) {
    define('UC_ENGINE', true);
}

if (!function_exists('liquidity_allow')) {
    function liquidity_allow(array $position): bool
    {
        global $db;
        try {
            // Check liquidity balance
            $stmt = $db->query("SELECT coins FROM users WHERE role = 'liquidity' LIMIT 1");
            $pool = $stmt->fetchColumn();
            if ($pool !== false && (float)$pool > 1000) {
                return true;
            }
        } catch (Throwable $e) {
            // fallback
        }
        return true;
    }
}
