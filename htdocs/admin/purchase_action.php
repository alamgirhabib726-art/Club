<?php
/**
 * UNMOOR CLUB - ADMIN PURCHASE ACTION HANDLER (LOCKED BALANCES)
 */

session_start();
require_once __DIR__ . "/../db.php";

/* ADMIN / SUB-ADMIN CHECK */
$adminId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['admin', 'sub_admin'])) {
    die("ACCESS DENIED");
}

/* INPUT VALIDATION */
$id = (int)($_GET['id'] ?? 0);
$action = trim($_GET['action'] ?? '');

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    die("INVALID REQUEST");
}

/* FETCH ORDER */
$stmt = $db->prepare("
    SELECT id, user_id, amount, status
    FROM payments
    WHERE id = ? AND type = 'purchase'
    LIMIT 1
");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("ORDER NOT FOUND");
}

if ($order['status'] !== 'pending') {
    header("Location: purchase_orders.php");
    exit;
}

if ($action === 'approve') {
    $res = approve_locked_order($db, $id, $adminId);
    if (!$res['success']) {
        die("Approval failed: " . htmlspecialchars($res['error']));
    }
} elseif ($action === 'reject') {
    $res = reject_locked_order($db, $id, $adminId, 'Admin rejected order');
    if (!$res['success']) {
        die("Rejection failed: " . htmlspecialchars($res['error']));
    }
}

header("Location: purchase_orders.php");
exit;
