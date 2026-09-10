<?php
/**
 * UNMOOR CLUB - ADMIN NOTICE REDIRECT / CANONICAL ROUTER
 * Redirects legacy notice.php references to canonical notices.php
 */
require_once __DIR__ . "/guard.php";

header("Location: notices.php", true, 302);
exit;
