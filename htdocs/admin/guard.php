<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.php");
    exit;
}

$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$role = $stmt->fetchColumn();
if (!in_array($role, ['admin', 'sub_admin'], true)) {
    header("Location: admin_login.php");
    exit;
}
if ($role === 'sub_admin') {
    header("Location: ../subadmin/orders.php");
    exit;
}