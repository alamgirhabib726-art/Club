<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$product = $_GET['product'] ?? '';

if ($product === '') {
    die("Invalid request");
}

/* FETCH ACTIVE PRODUCT */
$stmt = $db->prepare("
    SELECT *
    FROM products
    WHERE type = ? AND active = 1
    LIMIT 1
");
$stmt->execute([$product]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("Product not available");
}

/* PRICE CALCULATION */
$price    = (float)$p['price'];
$discount = (float)$p['discount'];
$final    = round($price - ($price * $discount / 100), 2);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars(strtoupper($p['type'])) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{
    max-width:420px;
    margin:auto;
    background:#fff;
    padding:20px;
    border-radius:18px
}
button{
    width:100%;
    padding:14px;
    border-radius:14px;
    background:#7c3aed;
    color:#fff;
    border:none;
    font-weight:600;
    cursor:pointer
}
.old{opacity:.6;text-decoration:line-through}
.price{font-size:18px;font-weight:700}
</style>
</head>

<body>

<div class="card">
    <h3><?= htmlspecialchars(strtoupper($p['type'])) ?></h3>

    <p>মূল্য: <span class="old">৳<?= number_format($price,2) ?></span></p>
    <p>ডিসকাউন্ট: <?= (int)$discount ?>%</p>
    <p class="price">চূড়ান্ত মূল্য: ৳<?= number_format($final,2) ?></p>

    <p>ডেলিভারি: <?= htmlspecialchars($p['delivery_time'] ?? '—') ?></p>

    <br>
    <a href="purchase_pay.php?product=<?= urlencode($p['type']) ?>">
        <button>Buy Now</button>
    </a>
</div>

</body>
</html>
