<?php
session_start();
require_once __DIR__ . "/db.php";

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
   FETCH PRODUCT
================================ */
$slug = $_GET['product'] ?? '';

$stmt = $db->prepare("
    SELECT id, title, price, discount
    FROM products
    WHERE slug = ? AND active = 1
    LIMIT 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("❌ Invalid product");
}

$finalAmount = max(0, $product['price'] - $product['discount']);
$msg = '';
$error = '';

/* ===============================
   HANDLE SUBMIT
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_FILES['proof']) ||
        $_FILES['proof']['error'] !== UPLOAD_ERR_OK
    ) {
        $error = "পেমেন্ট স্ক্রিনশট দিন";
    } else {

        /* FILE VALIDATION */
        $allowed = ['jpg','jpeg','png'];
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "শুধু JPG / PNG ফাইল অনুমোদিত";
        } else {

            /* UPLOAD */
            $dir = __DIR__ . "/uploads";
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $filename = "pay_" . $uid . "_" . time() . "." . $ext;
            $path = $dir . "/" . $filename;

            move_uploaded_file($_FILES['proof']['tmp_name'], $path);

            /* SAVE PAYMENT */
            $db->prepare("
                INSERT INTO payments
                (user_id, type, amount, product_id, proof, status)
                VALUES (?, 'purchase', ?, ?, ?, 'pending')
            ")->execute([
                $uid,
                $finalAmount,
                $product['id'],
                $filename
            ]);

            $msg = "✅ পেমেন্ট জমা হয়েছে। Admin অনুমোদনের অপেক্ষায়";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($product['title']) ?> • Payment</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f5f6fa}
.card{
    max-width:420px;
    margin:30px auto;
    background:#fff;
    padding:20px;
    border-radius:18px
}
input,button{
    width:100%;
    padding:14px;
    margin-top:12px;
    border-radius:14px
}
button{
    border:none;
    background:#7c3aed;
    color:#fff;
    font-weight:600
}
.msg{color:#16a34a}
.err{color:#dc2626}
</style>
</head>
<body>

<div class="card">
<h3><?= htmlspecialchars($product['title']) ?></h3>

<p>চূড়ান্ত মূল্য: <strong>৳<?= number_format($finalAmount,2) ?></strong></p>

<?php if ($msg): ?><p class="msg"><?= $msg ?></p><?php endif; ?>
<?php if ($error): ?><p class="err"><?= $error ?></p><?php endif; ?>

<?php if (!$msg): ?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="proof" required>
    <button type="submit">Submit Payment</button>
</form>
<?php endif; ?>

<a href="dashboard.php">← ড্যাশবোর্ড</a>
</div>

</body>
</html>
