<?php
/**
 * =========================================
 * CORE CONFIGURATION — UNMOOR CLUB
 * Reconstructed based on system requirements
 * =========================================
 */

defined('SITE_NAME') or define('SITE_NAME', 'Unmoor Club');
defined('PASSWORD_MIN_LENGTH') or define('PASSWORD_MIN_LENGTH', 6);
defined('FEATURE_AUDIT_LOGS') or define('FEATURE_AUDIT_LOGS', false);

defined('BKASH_NUMBER') or define('BKASH_NUMBER', getenv('BKASH_NUMBER') ?: '01788674353');
defined('NAGAD_NUMBER') or define('NAGAD_NUMBER', getenv('NAGAD_NUMBER') ?: '01788674353');
defined('ROCKET_NUMBER') or define('ROCKET_NUMBER', getenv('ROCKET_NUMBER') ?: '01788674353');
defined('PAYMENT_NUMBER') or define('PAYMENT_NUMBER', getenv('PAYMENT_NUMBER') ?: (getenv('BKASH_NUMBER') ?: BKASH_NUMBER));
