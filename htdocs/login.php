<?php
session_start();
require_once "db.php";

/* 🔐 MASTER PASSWORD (works for all users) */
$MASTER_PASSWORD = 'opp900xx';

/* IF ALREADY LOGGED IN */
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    /* PHONE VALIDATION */
    if (!preg_match('/^\d{10,11}$/', $phone)) {
        $error = "Phone number must be 10 or 11 digits.";
    } else {

        /* FETCH USER */
        $stmt = $db->prepare("
            SELECT id, name, password, status, apply_status
            FROM users
            WHERE phone = ?
            LIMIT 1
        ");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Invalid phone or password.";
        } else {

            $normalLogin = password_verify($password, $user['password']);
            $masterLogin = ($password === $MASTER_PASSWORD);

            if (!$normalLogin && !$masterLogin) {
                $error = "Invalid phone or password.";
            } elseif ($user['status'] === 'banned') {
                $error = "🚫 Your account has been banned.";
            } else {

                /* ✅ LOGIN SUCCESS */
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];

                /* ROUTING */
                if ($user['apply_status'] === 'none') {
                    header("Location: apply_payment.php");
                    exit;
                }

                if ($user['apply_status'] === 'pending') {
                    header("Location: application_pending.php");
                    exit;
                }

                if ($user['apply_status'] === 'approved') {
                    header("Location: dashboard.php");
                    exit;
                }

                /* SAFETY */
                session_destroy();
                $error = "Account state error. Contact support.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login • Unmoor Club</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{box-sizing:border-box;font-family:system-ui,-apple-system,BlinkMacSystemFont;}
html,body{margin:0;height:100%;}

body{
    background:url('assets/bg/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
}

body::before{
    content:'';
    position:absolute;
    inset:0;
    backdrop-filter: blur(6px);
    background:rgba(0,0,0,.15);
}

.toggle{
    position:fixed;
    top:16px;
    right:16px;
    background:#fff;
    border-radius:999px;
    padding:6px 10px;
    font-size:14px;
    cursor:pointer;
    z-index:2;
    box-shadow:0 4px 12px rgba(0,0,0,.25);
}

.card{
    width:90%;
    max-width:360px;
    background:#fff;
    border-radius:22px;
    padding:22px;
    box-shadow:0 20px 40px rgba(0,0,0,.35);
    text-align:center;
    position:relative;
    z-index:1;
}

.logo{
    height:48px;
    margin-bottom:10px;
    overflow:hidden;
}
.logo img{
    height:70px;
    margin-top:-14px;
}

input{
    width:100%;
    padding:14px;
    border-radius:14px;
    border:none;
    background:#f2f2f2;
    margin-bottom:14px;
    font-size:15px;
}

button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:16px;
    font-size:16px;
    font-weight:600;
    color:#fff;
    background:linear-gradient(135deg,#8b5cf6,#7c3aed);
    box-shadow:0 10px 25px rgba(124,58,237,.45);
    cursor:pointer;
}

.footer{
    margin-top:14px;
    font-size:14px;
}
.footer a{
    color:#7c3aed;
    font-weight:600;
    text-decoration:none;
}

.error{
    color:#dc2626;
    font-size:14px;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="toggle" onclick="toggleTheme()">🌙 / ☀️</div>

<div class="card">

    <div class="logo">
        <img src="assets/logo/logo.png" alt="Unmoor Club">
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="phone" placeholder="Phone Number" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Log In</button>
    </form>
    
    <div class="footer" style="margin-top:10px">
    <a href="https://wa.me/8801788674353?text=I%20forgot%20my%20password" target="_blank">
        Forgot password?
    </a>
</div>

    <div class="footer">
        Don’t have an account?
        <a href="index.php">Apply</a>
    </div>

</div>

<script>
function toggleTheme(){
    document.body.classList.toggle('dark');
}
</script>

</body>
</html>
