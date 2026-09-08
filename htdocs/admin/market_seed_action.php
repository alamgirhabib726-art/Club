<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ===== ADMIN GUARD ===== */
if (!isset($_SESSION['user_id'])) {
    die("NO SESSION");
}

$stmt = $db->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);

if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

/* ===== INPUT ===== */
$price = (float)($_POST['price'] ?? 0);
if ($price <= 0) {
    die("INVALID PRICE");
}

/* ===== INSERT ===== */
$db->prepare("
    INSERT INTO uc_market (price, created_at)
    VALUES (?, NOW())
")->execute([$price]);

header("Location: market_seed.php?ok=1");
exit;
