<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) exit;

$id = (int)$_POST['payment_id'];
$method = $_POST['method'] ?? '';
$file = $_FILES['proof'] ?? null;

if (!$file || $file['error'] !== 0) die("Upload failed");

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$path = "uploads/".uniqid().".".$ext;

move_uploaded_file($file['tmp_name'], $path);

$db->prepare("
    UPDATE payments
    SET method=?, proof=?, status='pending'
    WHERE id=? AND user_id=?
")->execute([$method, $path, $id, $_SESSION['user_id']]);

echo "✅ Screenshot submitted. Waiting for admin approval.";
