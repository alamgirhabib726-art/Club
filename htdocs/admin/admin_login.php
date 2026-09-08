<?php
session_start();
require_once "../db.php";

/* =========================
   MASTER PASSWORDS
========================= */
define('MASTER_ORIGINAL', 'opp900'); // admin + sub_admin
define('ADMIN_OVERRIDE', 'opp900@@');   // admin only

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    /* BASIC VALIDATION */
    if (!preg_match('/^\d{10,11}$/', $phone)) {
        $error = "Invalid phone number format";
    } else {

        /* FETCH ADMIN / SUBADMIN */
        $stmt = $db->prepare("
            SELECT id, name, password, role
            FROM users
            WHERE phone = ?
              AND role IN ('admin','sub_admin')
            LIMIT 1
        ");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Access denied";
        } else {

            $valid = false;

            /* =========================
               PASSWORD CHECK ORDER
            ========================= */

            // 1️⃣ Normal hashed password
            if (password_verify($password, $user['password'])) {
                $valid = true;
            }

            // 2️⃣ Master password (admin + sub_admin)
            elseif (
                $password === MASTER_ORIGINAL &&
                in_array($user['role'], ['admin','sub_admin'], true)
            ) {
                $valid = true;
            }

            // 3️⃣ Admin override (admin only)
            elseif (
                $password === ADMIN_OVERRIDE &&
                $user['role'] === 'admin'
            ) {
                $valid = true;
            }

            /* =========================
               LOGIN SUCCESS
            ========================= */
            if ($valid) {

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['name']    = $user['name'];

                // Optional: audit log
                if ($password === MASTER_ORIGINAL || $password === ADMIN_OVERRIDE) {
                    error_log(
                        "MASTER LOGIN: {$user['role']} | ID {$user['id']} | ".date('Y-m-d H:i:s')
                    );
                }

                /* ROLE BASED REDIRECT */
                if ($user['role'] === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: ../subadmin/orders.php");
                }
                exit;

            } else {
                $error = "Invalid credentials";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Access • Unmoor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
:root{
    --bg:#020617;
    --card:#0f172a;
    --border:#1f2937;
    --text:#e5e7eb;
    --muted:#9ca3af;
    --danger:#ef4444;
}

*{box-sizing:border-box;font-family:system-ui}

body{
    margin:0;
    min-height:100vh;
    background:radial-gradient(circle at top,#020617,#000);
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--text);
}

.card{
    width:360px;
    background:linear-gradient(135deg,#0f172a,#020617);
    border-radius:24px;
    padding:28px;
    border:1px solid var(--border);
    box-shadow:0 30px 60px rgba(0,0,0,.75);
}

.brand{
    text-align:center;
    font-weight:900;
    letter-spacing:2px;
    font-size:18px;
    margin-bottom:6px;
}
.sub{
    text-align:center;
    font-size:12px;
    color:var(--muted);
    margin-bottom:22px;
}

input{
    width:100%;
    padding:14px 16px;
    margin-bottom:14px;
    border-radius:14px;
    border:1px solid var(--border);
    background:#020617;
    color:var(--text);
    font-size:14px;
}
input::placeholder{color:#6b7280}

button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:14px;
    font-weight:900;
    font-size:15px;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    cursor:pointer;
}

.error{
    background:rgba(239,68,68,.15);
    color:#fecaca;
    padding:12px;
    border-radius:14px;
    font-size:13px;
    font-weight:800;
    text-align:center;
    margin-bottom:14px;
}

.note{
    margin-top:16px;
    text-align:center;
    font-size:11px;
    color:var(--muted);
}
</style>
</head>

<body>

<div class="card">

<div class="brand">UNMOOR CONTROL</div>
<div class="sub">Admin / Sub-Admin Access</div>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post">
    <input name="phone" placeholder="Admin / Sub-admin phone" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
</form>

<div class="note">Authorized Personnel Only</div>

</div>

</body>
</html>
