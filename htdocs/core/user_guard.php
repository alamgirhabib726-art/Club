<?php
/**
 * =========================================
 * USER GUARD
 * Controls access for logged-in users
 * =========================================
 */

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../db.php";

// Must be logged in
if (!auth_check()) {
    header("Location: /login.php");
    exit;
}

// Fetch user status + admin note
$stmt = $db->prepare("
    SELECT status, admin_note
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([auth_user_id()]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Invalid session
if (!$user) {
    auth_logout();
    http_response_code(403);
    die("INVALID SESSION");
}

/* -----------------------------------------
   HARD BLOCK STATES
------------------------------------------ */

// 🚫 BANNED
if ($user['status'] === 'banned') {
    auth_logout();
    http_response_code(403);
    ?>
    <div style="
        background:#3b0d0d;
        color:#f87171;
        padding:16px;
        border-radius:10px;
        font-weight:600;
        max-width:500px;
        margin:40px auto;
        text-align:center;
    ">
        ⛔ ACCOUNT BANNED<br><br>
        <?= htmlspecialchars($user['admin_note'] ?? 'This account has been banned by admin.') ?>
    </div>
    <?php
    exit;
}

// ❌ PAYMENT REJECTED
if ($user['status'] === 'rejected') {
    http_response_code(403);
    ?>
    <div style="
        background:#3b0d0d;
        color:#f87171;
        padding:16px;
        border-radius:10px;
        font-weight:600;
        max-width:500px;
        margin:40px auto;
        text-align:center;
    ">
        ❌ ACCESS NOT ACTIVATED<br><br>
        <?= htmlspecialchars($user['admin_note'] ?? 'Your payment was rejected by admin.') ?><br><br>
        Please contact support or submit payment again.
    </div>
    <?php
    exit;
}

/* -----------------------------------------
   SOFT STATES
------------------------------------------ */

// ⚠️ EXPIRED → allow access (dashboard shows renew UI)
if ($user['status'] === 'expired') {
    return;
}

// ✅ PENDING & ACTIVE → allowed
return;