<?php
/**
 * =========================================
 * HELPERS
 * Utility functions used across the system
 * =========================================
 */

/* Escape output */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/* Format date (PHP 8.2 safe) */
function format_date($date) {
    if (!$date) {
        return '—';
    }
    return date('d M Y', strtotime($date));
}

/* Optional audit log */
function audit_log($action, $userId = null) {
    if (!defined('FEATURE_AUDIT_LOGS') || FEATURE_AUDIT_LOGS !== true) {
        return;
    }

    global $db;

    try {
        $stmt = $db->prepare("
            INSERT INTO logs (user_id, action, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $action]);
    } catch (Exception $e) {
        // silent
    }
}