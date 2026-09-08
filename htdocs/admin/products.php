<?php
session_start();
require_once "../db.php";

/* ADMIN CHECK */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ACCESS DENIED");
}

/* UPDATE PRODUCT */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->prepare("
        UPDATE products
        SET price = ?, discount = ?, delivery_time = ?, active = ?
        WHERE id = ?
    ")->execute([
        (float)$_POST['price'],
        (float)$_POST['discount'],
        trim($_POST['delivery_time'] ?? ''),
        isset($_POST['active']) ? 1 : 0,
        (int)$_POST['id']
    ]);
}

/* FETCH PRODUCTS */
$products = $db->query("SELECT * FROM products ORDER BY id ASC")
               ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Purchase Products</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{background:#fff;padding:16px;border-radius:14px;margin-bottom:16px}
input{width:100%;padding:10px;margin:6px 0}
button{padding:10px 14px;background:#7c3aed;color:#fff;border:none;border-radius:10px}
label{font-size:14px}
</style>
</head>
<body>

<h2>🛒 Purchase Products</h2>

<?php foreach ($products as $p): ?>
<div class="card">
<form method="post">

<input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

<strong>
    <?= htmlspecialchars($p['name'] ?? 'PRODUCT') ?>
</strong>

<label>Price (BDT)</label>
<input name="price" value="<?= (float)($p['price'] ?? 0) ?>" required>

<label>Discount (BDT)</label>
<input name="discount" value="<?= (float)($p['discount'] ?? 0) ?>" required>

<label>Delivery Time</label>
<input name="delivery_time"
       value="<?= htmlspecialchars($p['delivery_time'] ?? '') ?>"
       placeholder="e.g. Instant / 24 Hours">

<label>
<input type="checkbox" name="active"
<?= !empty($p['active']) ? 'checked' : '' ?>>
 Active
</label>

<br><br>
<button type="submit">Save</button>

</form>
</div>
<?php endforeach; ?>

</body>
</html>
