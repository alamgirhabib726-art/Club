<?php
session_start();
require_once __DIR__ . "/../db.php";

/* =========================
   ADMIN / SUB-ADMIN CHECK
========================= */
$stmt = $db->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
$role = $stmt->fetchColumn();

if (!in_array($role, ['admin','sub_admin'])) {
    die("ACCESS DENIED");
}

/* =========================
   INPUT VALIDATION
========================= */
$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!$id || !in_array($action, ['approve','reject'])) {
    die("INVALID REQUEST");
}

/* =========================
   FETCH ORDER
========================= */
$stmt = $db->prepare("
    SELECT id, user_id, amount, status
    FROM payments
    WHERE id=? AND type='purchase'
    LIMIT 1
");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("ORDER NOT FOUND");
}

/* ALREADY HANDLED */
if ($order['status'] !== 'pending') {
    header("Location: purchase_orders.php");
    exit;
}

/* =========================
   PROCESS ACTION
========================= */
$db->beginTransaction();

try {

    /* APPROVE */
    if ($action === 'approve') {

        // mark approved
        $db->prepare("
            UPDATE payments
            SET status='approved'
            WHERE id=?
        ")->execute([$id]);

        // optional: log ledger
        $db->prepare("
            INSERT INTO coin_history (user_id, change, type, reference)
            VALUES (?, 0, 'purchase', 'Order approved')
        ")->execute([$order['user_id']]);
    }

    /* REJECT */
    if ($action === 'reject') {

        // refund coins
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id=?
        ")->execute([
            $order['amount'],
            $order['user_id']
        ]);

        // mark rejected
        $db->prepare("
            UPDATE payments
            SET status='rejected'
            WHERE id=?
        ")->execute([$id]);

        // ledger refund
        $db->prepare("
            INSERT INTO coin_history (user_id, change, type, reference)
            VALUES (?, ?, 'refund', 'Purchase rejected')
        ")->execute([
            $order['user_id'],
            $order['amount']
        ]);
    }

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    die("FAILED");
}

/* =========================
   REDIRECT BACK
========================= */
header("Location: purchase_orders.php");
exit;
