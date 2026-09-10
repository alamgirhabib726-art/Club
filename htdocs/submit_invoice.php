<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) exit;

$method = $_POST['method'];
$invoice = $_POST['invoice_id'];

$proof = $_FILES['proof'] ?? null;
$ext = strtolower(pathinfo($proof['name'] ?? '', PATHINFO_EXTENSION));
$allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
if (!in_array($ext, $allowedExts, true)) {
    $ext = 'jpg';
}
$name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$invoice) . "_" . time() . "." . $ext;

if (!is_dir(__DIR__ . "/uploads")) {
    mkdir(__DIR__ . "/uploads", 0755, true);
}
if (!empty($proof['tmp_name'])) {
    move_uploaded_file($proof['tmp_name'], __DIR__ . "/uploads/" . $name);
}

$now = date('Y-m-d H:i:s');
$db->prepare("
INSERT INTO payments
(user_id,type,product_id,amount,method,proof,status,created_at)
VALUES (?,?,?,?,?,?, 'pending', ?)
")->execute([
    $_SESSION['user_id'],
    'purchase',
    $_POST['product_id'] ?? null,
    $_POST['amount'] ?? 0,
    $method,
    $name,
    $now
]);

header("Location: dashboard.php");
exit;
