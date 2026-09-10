<?php
/**
 * ============================================
 * UC PRICE ENGINE (ISOLATED)
 * ============================================
 */

if (!defined('UC_ENGINE')) {
    define('UC_ENGINE', true);
}

if (!function_exists('get_current_price')) {
    function get_current_price(): float
    {
        global $db;
        try {
            $p = $db->query("SELECT price FROM market_price WHERE symbol='UC' LIMIT 1")->fetchColumn();
            if ($p !== false && (float)$p > 0) {
                return (float)$p;
            }
            $p2 = $db->query("SELECT price FROM uc_market ORDER BY id DESC LIMIT 1")->fetchColumn();
            if ($p2 !== false && (float)$p2 > 0) {
                return (float)$p2;
            }
        } catch (Throwable $e) {
            // fallback safe price
        }
        return 100.00;
    }
}
