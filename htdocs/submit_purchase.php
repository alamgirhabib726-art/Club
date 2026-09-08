<?php
session_start();
require_once "db.php";

/* ===============================
   LOGIN REQUIRED
================================ */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ===============================
   PREMIUM CHECK
================================ */
$stmt = $db->prepare("SELECT status FROM users WHERE id=?");
$stmt->execute([$uid]);
if ($stmt->fetchColumn() !== 'premium') {
    header("Location: payment.php");
    exit;
}

/* ===============================
   PRODUCT VALIDATION
================================ */
$productId = (int)($_POST['product_id'] ?? 0);

$stmt = $db->prepare("
    SELECT id, price, discount
    FROM products
    WHERE id = ? AND active = 1
    LIMIT 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("❌ Invalid product");
}

$finalAmount = max(0, $product['price'] - $product['discount']);

/* ===============================
   PROOF CHECK
================================ */
if (
    !isset($_FILES['proof']) ||
    $_FILES['proof']['error'] !== UPLOAD_ERR_OK
) {
    die("❌ Payment proof required");
}

$allowed = ['jpg','jpeg','png'];
$ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    die("❌ Only JPG / PNG allowed");
}

/* ===============================
   UPLOAD FILE
================================ */
$dir = __DIR__ . "/uploads";
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = "purchase_{$uid}_" . time() . "." . $ext;
move_uploaded_file($_FILES['proof']['tmp_name'], "$dir/$filename");

/* ===============================
   INSERT PAYMENT
================================ */
$stmt = $db->prepare("
    INSERT INTO payments
    (user_id, type, amount, product_id, proof, status)
    VALUES (?, 'purchase', ?, ?, ?, 'pending')
");
$stmt->execute([
    $uid,
    $finalAmount,
    $product['id'],
    $filename
]);

header("Location: dashboard.php");
exit;
