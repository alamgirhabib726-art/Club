<?php
session_start();
require_once "../db.php";

/* ===============================
   ADMIN AUTH CHECK
================================ */
if (!isset($_SESSION['user_id'])) {
    die("NO SESSION");
}

$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') {
    die("ADMIN ONLY");
}

/* ===============================
   VALIDATE INPUT
================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("INVALID REQUEST");
}

$payment_id = (int)($_POST['payment_id'] ?? 0);
if ($payment_id <= 0) {
    die("INVALID PAYMENT ID");
}

/* ===============================
   FETCH PAYMENT
================================ */
$stmt = $db->prepare("
    SELECT id, user_id, type, amount, status
    FROM payments
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("PAYMENT NOT FOUND");
}

if ($payment['status'] !== 'pending') {
    header("Location: payments.php");
    exit;
}

/* ===============================
   FETCH USER
================================ */
$stmt = $db->prepare("
    SELECT id, coins
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$payment['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("USER NOT FOUND");
}

/* ===============================
   FETCH SYSTEM USER
================================ */
$systemId = $db->query("
    SELECT id FROM users WHERE role='system' LIMIT 1
")->fetchColumn();

if (!$systemId) {
    die("SYSTEM USER MISSING");
}

/* ===============================
   TRANSACTION START
================================ */
$db->beginTransaction();

try {

    /* ===============================
       APPLY PAYMENT
    ================================ */
    if ($payment['type'] === 'apply') {

        $db->prepare("
            UPDATE users
            SET 
                status = 'active',
                apply_status = 'approved',
                coins = coins + 5,
                coin_cycle_start = NOW()
            WHERE id = ?
        ")->execute([$payment['user_id']]);
    }

    /* ===============================
       PREMIUM PAYMENT
    ================================ */
    if ($payment['type'] === 'premium') {

        $db->prepare("
            UPDATE users
            SET status = 'premium'
            WHERE id = ?
        ")->execute([$payment['user_id']]);
    }

    /* ===============================
       DEPOSIT PAYMENT (DEBT SAFE)
    ================================ */
    if ($payment['type'] === 'deposit') {

        $depositCoins = $payment['amount'] / 10;
        $currentCoins = (float)$user['coins'];

        // handle negative balance repayment
        $debt        = max(0, -$currentCoins);
        $toSystem    = min($debt, $depositCoins);
        $toUser      = $depositCoins - $toSystem;

        // update user
        if ($toUser > 0) {
            $db->prepare("
                UPDATE users
                SET coins = coins + ?
                WHERE id = ?
            ")->execute([$toUser, $payment['user_id']]);
        }

        // repay system if needed
        if ($toSystem > 0) {
            $db->prepare("
                UPDATE users
                SET coins = coins + ?
                WHERE id = ?
            ")->execute([$toSystem, $systemId]);
        }
    }

    /* ===============================
       MARK PAYMENT APPROVED
    ================================ */
    $db->prepare("
        UPDATE payments
        SET status = 'approved'
        WHERE id = ?
    ")->execute([$payment_id]);

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    die("FAILED: " . $e->getMessage());
}

/* ===============================
   REDIRECT
================================ */
header("Location: payments.php?approved=1");
exit;