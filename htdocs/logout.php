<?php
session_start();

/* Destroy session */
$_SESSION = [];
session_destroy();

/* Redirect to login */
header("Location: login.php");
exit;
