<?php
session_start();
require_once __DIR__ . "/../db.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* FETCH USER */
$stmt = $db->prepare("SELECT photo FROM users WHERE id=? LIMIT 1");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$avatar = !empty($user['photo'])
    ? "../uploads/avatars/".$user['photo']
    : "assets/default-avatar.png";

$msg = '';

/* UPLOAD */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {

    $file = $_FILES['photo'];

    if ($file['error'] === 0) {

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];

        if (!in_array($ext, $allowed)) {
            $msg = "Invalid image format";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $msg = "Image too large (max 2MB)";
        } else {

            $name = "avatar_".$uid."_".time().".".$ext;
            $path = "../uploads/avatars/".$name;

            if (move_uploaded_file($file['tmp_name'], $path)) {

                $db->prepare("UPDATE users SET photo=? WHERE id=?")
                   ->execute([$name, $uid]);

                header("Location: avatar.php");
                exit;
            } else {
                $msg = "Upload failed";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Profile Photo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui}
body{background:#0b141a;color:#e9edef}

.container{max-width:480px;margin:auto;min-height:100vh}

/* HEADER */
.header{
    height:56px;
    display:flex;
    align-items:center;
    padding:0 16px;
}
.header a{
    color:#00a884;
    font-size:22px;
    margin-right:16px;
    text-decoration:none;
}
.header h1{font-size:18px;font-weight:600}

/* AVATAR */
.avatar-wrap{
    display:flex;
    justify-content:center;
    margin-top:40px;
}
.avatar{
    width:160px;
    height:160px;
    border-radius:50%;
    overflow:hidden;
    border:4px solid #202c33;
}
.avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}

/* ACTION */
.actions{
    margin-top:30px;
    text-align:center;
}
label.upload{
    display:inline-block;
    background:#00a884;
    color:#022c22;
    padding:12px 20px;
    border-radius:24px;
    font-weight:700;
    cursor:pointer;
}
input[type=file]{display:none}

/* MSG */
.msg{
    margin-top:16px;
    color:#ef4444;
    text-align:center;
    font-size:14px;
}

/* NOTE */
.note{
    margin-top:18px;
    text-align:center;
    font-size:13px;
    color:#8696a0;
}
</style>
</head>

<body>
<div class="container">

    <!-- HEADER -->
    <div class="header">
        <a href="account.php">←</a>
        <h1>Profile photo</h1>
    </div>

    <!-- AVATAR -->
    <div class="avatar-wrap">
        <div class="avatar">
            <img src="<?=htmlspecialchars($avatar)?>">
        </div>
    </div>

    <!-- ACTION -->
    <form method="post" enctype="multipart/form-data" class="actions">
        <label class="upload">
            Change photo
            <input type="file" name="photo" accept="image/*" onchange="this.form.submit()">
        </label>
    </form>

    <?php if($msg): ?>
        <div class="msg"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>

    <div class="note">
        JPG, PNG or WEBP • Max 2MB
    </div>

</div>
</body>
</html>