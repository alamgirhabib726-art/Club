<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) exit;

$method = $_POST['method'];
$invoice = $_POST['invoice_id'];

$proof = $_FILES['proof'];
$ext = pathinfo($proof['name'], PATHINFO_EXTENSION);
$name = $invoice . "." . $ext;

if (!is_dir("uploads")) mkdir("uploads",0777,true);
move_uploaded_file($proof['tmp_name'], "uploads/".$name);

$db->prepare("
INSERT INTO payments
(user_id,type,product_id,amount,method,proof,status,created_at)
VALUES (?,?,?,?,?,?, 'pending', datetime('now'))
")->execute([
    $_SESSION['user_id'],
    'purchase',
    $_POST['product_id'],
    $_POST['amount'],
    $method,
    $name
]);

header("Location: dashboard.php");
exit;
