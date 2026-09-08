<?php
session_start();
require_once "db.php";

/* ================= LOGIN REQUIRED ================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* ================= CHECK USER STATUS ================= */
$stmt = $db->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
$stmt->execute([$user_id]);
$userStatus = $stmt->fetchColumn();

if ($userStatus === 'premium') {
    header("Location: dashboard.php");
    exit;
}

/* ================= CHECK LAST PREMIUM PAYMENT ================= */
$stmt = $db->prepare("
    SELECT status 
    FROM payments 
    WHERE user_id = ? AND type = 'premium'
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);

$pending = ($lastPayment && $lastPayment['status'] === 'pending');

$error = '';
$success = false;

/* ================= HANDLE FORM ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($pending) {
        $error = "আপনার একটি পেমেন্ট ইতিমধ্যে রিভিউতে আছে";
    } else {

        $plan  = $_POST['plan'] ?? '';
        $proof = $_FILES['proof'] ?? null;

        if (!in_array($plan, ['day','month'], true)) {
            $error = "প্ল্যান নির্বাচন করুন";
        }
        elseif (!$proof || $proof['error'] !== UPLOAD_ERR_OK) {
            $error = "পেমেন্ট স্ক্রিনশট দিন";
        }
        else {

            $amount = ($plan === 'day') ? 10 : 300;

            /* ===== FILE VALIDATION ===== */
            $allowedExt = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($proof['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                $error = "শুধু ছবি ফাইল অনুমোদিত";
            } else {

                $uploadDir = __DIR__ . "/uploads/premium";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = uniqid('premium_', true) . "." . $ext;
                $path = $uploadDir . "/" . $filename;

                if (!move_uploaded_file($proof['tmp_name'], $path)) {
                    $error = "ফাইল আপলোড ব্যর্থ";
                } else {

                    /* ===== SAVE PAYMENT ===== */
                    $stmt = $db->prepare("
                        INSERT INTO payments
                        (user_id, type, amount, plan, proof, status, created_at)
                        VALUES (?, 'premium', ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([
                        $user_id,
                        $amount,
                        $plan,
                        $filename
                    ]);

                    $success = true;
                    $pending = true;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Premium Payment • Unmoor</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:system-ui;background:#f6f7fb}
.card{
  max-width:420px;margin:30px auto;background:#fff;
  padding:20px;border-radius:18px;
  box-shadow:0 20px 40px rgba(0,0,0,.15)
}
select,input,button{
  width:100%;padding:14px;margin-bottom:12px;
  border-radius:14px;border:1px solid #e5e7eb
}
button{background:#7c3aed;color:#fff;font-weight:600;border:none}
.error{color:#dc2626;margin-bottom:10px}
.success{
  background:#dcfce7;color:#166534;
  padding:14px;border-radius:14px;text-align:center
}
.pending{
  background:#fff7ed;color:#9a3412;
  padding:14px;border-radius:14px;text-align:center
}
small{color:#6b7280}
</style>
</head>
<body>

<div class="card">
<h3>⬆️ Premium Upgrade</h3>

<?php if ($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($pending): ?>
  <div class="pending">
    ⏳ আপনার পেমেন্ট রিভিউতে আছে<br>
    এডমিন অনুমোদনের অপেক্ষায়
  </div>

<?php elseif ($success): ?>
  <div class="success">
    ✅ পেমেন্ট সাবমিট হয়েছে<br>
    ⏳ এডমিন অনুমোদনের অপেক্ষায়
  </div>

<?php else: ?>

<form method="post" enctype="multipart/form-data">

  <label>প্ল্যান নির্বাচন করুন</label>
  <select name="plan" required>
    <option value="">-- নির্বাচন করুন --</option>
    <option value="day">ডে প্ল্যান – ৳10</option>
    <option value="month">মাসিক প্ল্যান – ৳300</option>
  </select>

  <p>
    📱 Payment Number:<br>
    <b>01788674353</b><br>
    <small>bKash / Nagad</small>
  </p>

  <label>পেমেন্ট স্ক্রিনশট</label>
  <input type="file" name="proof" accept="image/*" required>

  <button type="submit">Submit Payment</button>
</form>

<?php endif; ?>

<a href="dashboard.php">← ড্যাশবোর্ড</a>
</div>

</body>
</html>
