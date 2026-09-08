<?php
session_start();
require_once __DIR__ . "/db.php";
$pay = require "config/payment_numbers.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}

$productId = (int)($_GET['product_id'] ?? 0);

$stmt = $db->prepare("SELECT name, price, discount FROM products WHERE id=?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) die("Invalid product");

$amount = $product['price'] - $product['discount'];
$invoiceId = uniqid("INV-");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:system-ui;background:#f4f6fb}
.card{max-width:420px;margin:30px auto;background:#fff;
padding:20px;border-radius:20px;box-shadow:0 20px 40px rgba(0,0,0,.1)}
select,input,button{width:100%;padding:14px;margin-top:12px;border-radius:14px}
button{background:#7c3aed;color:#fff;border:none;font-weight:700}
.method-box{background:#f1f5f9;padding:14px;border-radius:14px;margin-top:12px}
</style>
</head>
<body>

<div class="card">
<h3>🧾 Invoice</h3>

<p><b>Product:</b> <?= htmlspecialchars($product['name']) ?></p>
<p><b>Amount:</b> ৳<?= number_format($amount,2) ?></p>
<p><b>Invoice ID:</b> <?= $invoiceId ?></p>

<form method="post" action="submit_invoice.php" enctype="multipart/form-data">

<input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
<input type="hidden" name="product_id" value="<?= $productId ?>">
<input type="hidden" name="amount" value="<?= $amount ?>">

<label>Payment Method</label>
<select name="method" required onchange="showNumber(this.value)">
  <option value="">-- Select --</option>
  <option value="bkash">bKash</option>
  <option value="nagad">Nagad</option>
  <option value="rocket">Rocket</option>
</select>

<div id="numberBox" class="method-box" style="display:none"></div>

<label>Payment Screenshot</label>
<input type="file" name="proof" accept="image/*" required>

<button type="submit">Submit Payment</button>
</form>
</div>

<script>
const numbers = <?= json_encode($pay) ?>;
function showNumber(m){
  if(!numbers[m]) return;
  document.getElementById("numberBox").style.display="block";
  document.getElementById("numberBox").innerHTML =
    "📱 <b>"+numbers[m].name+"</b><br>"+numbers[m].number;
}
</script>

</body>
</html>
