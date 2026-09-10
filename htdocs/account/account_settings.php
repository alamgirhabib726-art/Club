<?php
/**
 * UNMOOR CLUB - ACCOUNT CREDENTIALS & SECURITY SETTINGS
 */

session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../core/components.php";

/* LOGIN CHECK */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

/* FETCH USER */
$stmt = $db->prepare("
    SELECT id, name, phone, password
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$msg = $err = "";

/* CHANGE PHONE */
if (isset($_POST['change_phone'])) {
    $newPhone = trim($_POST['new_phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^\d{10,11}$/', $newPhone)) {
        $err = "Invalid phone number format. Must be 10-11 digits.";
    } elseif (!password_verify($password, $user['password'])) {
        $err = "Incorrect account password confirmation.";
    } else {
        $db->prepare("UPDATE users SET phone=? WHERE id=?")
           ->execute([$newPhone, $user['id']]);
        $msg = "Phone number updated successfully to $newPhone.";
        $user['phone'] = $newPhone;
    }
}

/* CHANGE PASSWORD */
if (isset($_POST['change_pass'])) {
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($oldPass, $user['password'])) {
        $err = "Current password is incorrect.";
    } elseif (strlen($newPass) < 6) {
        $err = "New password must be at least 6 characters long.";
    } elseif ($newPass !== $confirm) {
        $err = "New password confirmation does not match.";
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")
           ->execute([$hash, $user['id']]);
        $msg = "Password updated successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header('Account Settings', '/account/') ?>

        <?php if ($msg): ?>
            <?= render_alert($msg, 'success') ?>
        <?php endif; ?>

        <?php if ($err): ?>
            <?= render_alert($err, 'danger') ?>
        <?php endif; ?>

        <!-- USER IDENTITY INFO -->
        <div class="card">
            <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin-bottom: 12px;">👤 Account Identity</h3>
            
            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px; margin-bottom: 10px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Full Legal / Club Name</div>
                <div style="font-size: 14.5px; font-weight: 700; color: #ffffff; margin-top: 2px;"><?= htmlspecialchars($user['name']) ?></div>
            </div>

            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px; margin-bottom: 10px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Linked Mobile Number</div>
                <div style="font-size: 14.5px; font-weight: 700; color: var(--accent-gold); margin-top: 2px;"><?= htmlspecialchars($user['phone']) ?></div>
            </div>

            <div style="background: var(--bg-dark); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px;">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Serial User ID</div>
                <div style="font-size: 14.5px; font-weight: 700; color: #ffffff; margin-top: 2px;">#<?= $user['id'] ?></div>
            </div>
        </div>

        <!-- UPDATE PHONE -->
        <div class="card">
            <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin-bottom: 12px;">📱 Update Phone Number</h3>
            
            <form method="post">
                <div class="form-group">
                    <label class="form-label">New Mobile Number</label>
                    <input type="text" name="new_phone" class="form-input" placeholder="e.g. 01700000000" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Current Account Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Confirm your current password" required>
                </div>

                <button type="submit" name="change_phone" class="btn btn-secondary btn-block">
                    Update Phone Number
                </button>
            </form>
        </div>

        <!-- UPDATE PASSWORD -->
        <div class="card">
            <h3 style="font-size: 15px; font-weight: 800; color: #ffffff; margin-bottom: 12px;">🔑 Change Password</h3>
            
            <form method="post">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="old_password" class="form-input" placeholder="Enter current password" required>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" placeholder="Minimum 6 characters" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="Re-type new password" required>
                </div>

                <button type="submit" name="change_pass" class="btn btn-primary btn-block">
                    Update Password
                </button>
            </form>
        </div>

        <!-- SUPPORT -->
        <?= render_support_widget() ?>

    </div>

    <!-- GLOBAL BOTTOM NAVIGATION -->
    <?php require_once __DIR__ . "/../bottom_nav.php"; ?>

    <script src="../assets/app.js"></script>
</body>
</html>
