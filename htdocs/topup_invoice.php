<?php
session_start();
require_once "db.php";

/* ===============================
   AUTH CHECK
================================ */
if (!isset($_SESSION['user_id'])) {
    die("Login required");
}

/* ===============================
   VALIDATE PAYMENT ID
================================ */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$payment_id = (int)$_GET['id'];

/* ===============================
   FETCH PAYMENT
================================ */
$stmt = $db->prepare("
    SELECT *
    FROM payments
    WHERE id = ? AND user_id = ? AND status = 'pending'
");
$stmt->execute([$payment_id, $_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("Invoice not found or already processed");
}

/* ===============================
   PAYMENT METHODS (INLINE SAFE)
================================ */
$methods = [
    'bkash' => [
        'name'   => 'bKash',
        'number' => '01788674353'
    ],
    'nagad' => [
        'name'   => 'Nagad',
        'number' => '018XXXXXXXX'
    ]
];

$msg = '';

/* ===============================
   HANDLE SCREENSHOT SUBMIT
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $method = $_POST['method'] ?? '';

    if (!isset($methods[$method])) {
        $msg = "❌ ভুল পেমেন্ট মেথড";
    } elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== 0) {
        $msg = "❌ স্ক্রিনশট দিন";
    } else {

        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            $msg = "❌ শুধু ছবি ফাইল অনুমোদিত";
        } else {

            $dir = __DIR__ . "/uploads";
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            $file = "pay_".$payment_id."_".time().".".$ext;

            if (!move_uploaded_file($_FILES['proof']['tmp_name'], "$dir/$file")) {
                $msg = "❌ আপলোড ব্যর্থ";
            } else {

                $db->prepare("
                    UPDATE payments
                    SET method = ?, proof = ?, status = 'pending'
                    WHERE id = ?
                ")->execute([$method, $file, $payment_id]);

                $msg = "✅ পেমেন্ট সাবমিট হয়েছে। অ্যাডমিন যাচাই করবে।";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Payment Invoice</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:system-ui;background:#f5f6fa;margin:0}
.card{
    max-width:420px;
    margin:40px auto;
    background:#fff;
    padding:22px;
    border-radius:18px;
    box-shadow:0 20px 40px rgba(0,0,0,.15)
}
.notice{
    background:#eef2ff;
    padding:14px;
    border-radius:14px;
    margin-bottom:14px;
    font-size:14px
}
select,input,button{
    width:100%;
    padding:14px;
    margin-top:10px;
    border-radius:14px;
    border:none
}
button{
    background:#7c3aed;
    color:#fff;
    font-weight:600
}
.msg{
    margin-top:14px;
    text-align:center;
    font-weight:600
}
.back{
    display:block;
    text-align:center;
    margin-top:16px;
    color:#7c3aed;
    font-weight:600;
    text-decoration:none
}
</style>
</head>
<body>

<div class="card">
<h3>🧾 Payment Invoice</h3>

<div class="notice">
<b>Amount:</b> ৳<?=number_format($payment['amount'],2)?><br>
<b>Status:</b> Pending approval
</div>

<?php if ($msg): ?>
<div class="msg"><?=$msg?></div>
<?php else: ?>
<form method="post" enctype="multipart/form-data">

    <select name="method" required>
        <option value="">-- পেমেন্ট মেথড নির্বাচন করুন --</option>
        <?php foreach ($methods as $k=>$m): ?>
            <option value="<?=$k?>">
                <?=$m['name']?> (<?=$m['number']?>)
            </option>
        <?php endforeach; ?>
    </select>

    <input type="file" name="proof" accept="image/*" required>

    <button>📤 Submit Screenshot</button>
</form>
<?php endif; ?>

<a class="back" href="dashboard.php">← Dashboard</a>
</div>

</body>
</html>
