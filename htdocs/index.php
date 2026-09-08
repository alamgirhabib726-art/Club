<?php
session_start();
require_once "db.php";

/* BLOCK LOGGED IN USERS */
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($name) < 3) {
        $error = "Name must be at least 3 characters.";
    } elseif (!preg_match('/^\d{10,11}$/', $phone)) {
        $error = "Phone number must be 10 or 11 digits.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        /* CHECK DUPLICATE PHONE */
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);

        if ($stmt->fetch()) {
            $error = "Phone number already registered.";
        } else {

            /* CREATE PENDING USER */
            $stmt = $db->prepare("
                INSERT INTO users
                (name, phone, password, role, status, apply_status)
                VALUES (?, ?, ?, 'user', 'pending', 'pending')
            ");
            $stmt->execute([
                $name,
                $phone,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $uid = $db->lastInsertId();

            /* TEMP SESSION (NOT FULL LOGIN) */
            $_SESSION['apply_user_id'] = $uid;
            $_SESSION['user_id'] = $uid; // needed for payment + admin linkage

            /* REDIRECT TO PAYMENT */
            header("Location: apply_payment.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Apply • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box;font-family:system-ui}
body{
    margin:0;
    background:url('assets/bg/bg.jpg') center/cover fixed;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.card{
    width:90%;
    max-width:360px;
    background:#fff;
    border-radius:22px;
    padding:22px;
    box-shadow:0 20px 40px rgba(0,0,0,.35);
    text-align:center;
}
.logo img{height:60px;margin-bottom:10px}
input{
    width:100%;
    padding:14px;
    border:none;
    border-radius:14px;
    background:#f2f2f2;
    margin-bottom:14px;
}
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:16px;
    color:#fff;
    font-weight:600;
    background:linear-gradient(135deg,#8b5cf6,#7c3aed);
}
.footer{margin-top:14px;font-size:14px}
.footer a{color:#7c3aed;font-weight:600;text-decoration:none}
.error{color:#dc2626;margin-bottom:10px}
.note{
    font-size:13px;
    color:#6b7280;
    margin-bottom:12px
}
</style>
</head>

<body>

<div class="card">
    <div class="logo">
        <img src="assets/logo/logo.png" alt="Unmoor Club">
    </div>

    <div class="note">
        📝 Apply fee: <b>৳150</b><br>
        ⏳ Admin approval required
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input name="name" placeholder="Full Name" required>
        <input name="phone" placeholder="Phone Number" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Apply</button>
    </form>

    <div class="footer">
        Already applied?
        <a href="login.php">Log In</a>
    </div>
</div>

</body>
</html>
