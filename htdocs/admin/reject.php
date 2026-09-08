<?php
session_start();
require_once "../db.php";

/* ADMIN CHECK */
if (!isset($_SESSION['user_id'])) {
    die("NO ACCESS");
}

$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ADMIN ONLY");
}

/* VALIDATE ID */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("INVALID REQUEST");
}

/* CHECK PAYMENT EXISTS */
$stmt = $db->prepare("SELECT id FROM payments WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    die("PAYMENT NOT FOUND");
}

/* REJECT PAYMENT */
$db->prepare("
    UPDATE payments 
    SET status = 'rejected' 
    WHERE id = ?
")->execute([$id]);

header("Location: payments.php");
exit;
