<?php
session_start();
require_once __DIR__ . "/db.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['premium_amount'])
) {
    header("Location: dashboard.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== 0) {
        $msg = "Screenshot required";
    } else {

        $name = time() . '_' . basename($_FILES['proof']['name']);
        $path = "uploads/" . $name;

        if (!is_dir("uploads")) {
            mkdir("uploads",0777,true);
        }

        move_uploaded_file($_FILES['proof']['tmp_name'], $path);

        $db->prepare("
            INSERT INTO payments (user_id, type, amount, proof, status)
            VALUES (?,?,?,?, 'pending')
        ")->execute([
            $_SESSION['user_id'],
            'premium',
            $_SESSION['premium_amount'],
            $name
        ]);

        unset($_SESSION['premium_plan'], $_SESSION['premium_amount']);

        $msg = "✅ Payment submitted. Admin approval pending.";
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Upload Proof</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body{font-family:system-ui;background:#f4f4f5;padding:20px}
.card{
    max-width:380px;margin:auto;
    background:#fff;padding:20px;border-radius:18px
}
input,button{
    width:100%;padding:14px;margin-bottom:12px;
    border-radius:14px
}
button{
    border:none;background:#7c3aed;color:#fff;font-weight:600
}
.msg{color:#16a34a;font-weight:600}
</style>
</head>
<body>

<div class="card">
<h3>📤 Payment Screenshot</h3>

<?php if ($msg): ?>
<p class="msg"><?= htmlspecialchars($msg) ?></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="proof" accept="image/*" required>
    <button>Submit</button>
</form>

<a href="dashboard.php">← Dashboard</a>
</div>

</body>
</html>
