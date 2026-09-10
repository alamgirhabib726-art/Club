<?php
/**
 * UNMOOR CLUB - MEMBER PROFILE PHOTO & AVATAR MANAGER
 */

session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../core/components.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH USER */
$stmt = $db->prepare("SELECT photo, name FROM users WHERE id=? LIMIT 1");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$avatar = !empty($user['photo'])
    ? "../uploads/avatars/" . htmlspecialchars($user['photo'])
    : "../assets/default-avatar.png";

$msg = '';
$err = '';

/* UPLOAD */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];

    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            $err = "Invalid image format. Allowed formats: JPG, PNG, WEBP.";
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $err = "Image size too large. Maximum file size is 3MB.";
        } else {
            $uploadDir = __DIR__ . "/../uploads/avatars/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $name = "avatar_" . $uid . "_" . time() . "." . $ext;
            $targetPath = $uploadDir . $name;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $db->prepare("UPDATE users SET photo=? WHERE id=?")->execute([$name, $uid]);
                $msg = "Profile photo updated successfully!";
                $avatar = "../uploads/avatars/" . $name;
            } else {
                $err = "Failed to save uploaded image. Please check server permissions.";
            }
        }
    } else {
        $err = "No file selected or upload error occurred.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Photo • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header('Profile Photo', '/account/') ?>

        <?php if ($msg): ?>
            <?= render_alert($msg, 'success') ?>
        <?php endif; ?>

        <?php if ($err): ?>
            <?= render_alert($err, 'danger') ?>
        <?php endif; ?>

        <div class="card" style="text-align: center; padding: 32px 16px;">
            <div style="width: 140px; height: 140px; margin: 0 auto 20px; position: relative;">
                <img src="<?= $avatar ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid var(--accent-gold); box-shadow: var(--shadow-gold);" onerror="this.src='../assets/default-avatar.png'">
            </div>

            <h3 style="font-size: 17px; font-weight: 800; color: #ffffff; margin-bottom: 6px;">
                <?= htmlspecialchars($user['name'] ?? 'Member') ?>
            </h3>

            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 24px;">
                Upload a clear portrait. Image will be formatted across club community pages.
            </p>

            <form method="post" enctype="multipart/form-data">
                <label class="btn btn-gold btn-block" style="cursor: pointer; display: block; margin-bottom: 12px;">
                    📷 Choose New Photo
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()" style="display: none;">
                </label>
            </form>

            <div style="font-size: 11.5px; color: var(--text-dim);">
                Supported formats: JPG, PNG, WEBP (Max 3MB)
            </div>
        </div>

        <?= render_support_widget() ?>

    </div>

    <!-- GLOBAL BOTTOM NAVIGATION -->
    <?php require_once __DIR__ . "/../bottom_nav.php"; ?>

    <script src="../assets/app.js"></script>
</body>
</html>
