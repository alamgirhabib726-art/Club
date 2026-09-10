<?php
/**
 * UNMOOR CLUB - ROOT ROUTER
 * Redirects visitors to register.php, or dashboard.php if already logged in.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

header("Location: register.php");
exit;
