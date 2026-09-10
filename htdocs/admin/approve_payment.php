<?php
session_start();
require_once __DIR__ . "/../db.php";

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
$payment_id = (int)($_POST['payment_id'] ?? ($_GET['id'] ?? 0));
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
    $now = date('Y-m-d H:i:s');
    $db->prepare("INSERT INTO users (name, phone, role, status, coins, created_at) VALUES ('SYSTEM', '00000000000', 'system', 'active', 0, ?)")->execute([$now]);
    $systemId = $db->lastInsertId();
}

/* ===============================
   TRANSACTION START
================================ */
$db->beginTransaction();

try {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

    /* APPLY PAYMENT */
    if ($payment['type'] === 'apply') {
        $db->prepare("
            UPDATE users
            SET 
                status = 'active',
                apply_status = 'approved',
                coins = coins + 5,
                coin_cycle_start = $nowExpr
            WHERE id = ?
        ")->execute([$payment['user_id']]);
        
        $db->prepare("
            INSERT INTO coin_history (user_id, amount, type, source, reference, created_at)
            VALUES (?, 5, 'apply_bonus', 'REGISTRATION_BONUS', 'Registration bonus', $nowExpr)
        ")->execute([$payment['user_id']]);
    }

    /* PREMIUM PAYMENT */
    if ($payment['type'] === 'premium') {
        $db->prepare("
            UPDATE users
            SET status = 'premium'
            WHERE id = ?
        ")->execute([$payment['user_id']]);
    }

    /* DEPOSIT PAYMENT */
    if ($payment['type'] === 'deposit') {
        $depositCoins = (float)$payment['amount'] / 10;
        $currentCoins = (float)$user['coins'];

        $debt = max(0, -$currentCoins);
        $toSystem = min($debt, $depositCoins);
        $toUser = $depositCoins - $toSystem;

        if ($toUser > 0) {
            $db->prepare("
                UPDATE users
                SET coins = coins + ?
                WHERE id = ?
            ")->execute([$toUser, $payment['user_id']]);

            $db->prepare("
                INSERT INTO coin_history (user_id, amount, type, source, reference, created_at)
                VALUES (?, ?, 'deposit', 'DEPOSIT_APPROVAL', 'Deposit approved', $nowExpr)
            ")->execute([$payment['user_id'], $toUser]);
        }

        if ($toSystem > 0) {
            $db->prepare("
                UPDATE users
                SET coins = coins + ?
                WHERE id = ?
            ")->execute([$toSystem, $systemId]);
        }
    }

    /* DONATION PAYMENT */
    if ($payment['type'] === 'donation') {
        try {
            $db->prepare("
                INSERT INTO donations (user_id, amount, method, created_at)
                VALUES (?, ?, 'Manual/Payment', $nowExpr)
            ")->execute([$payment['user_id'], $payment['amount']]);
        } catch (Throwable $dt) {}
    }

    /* MARK PAYMENT APPROVED */
    $db->prepare("
        UPDATE payments
        SET status = 'approved'
        WHERE id = ?
    ")->execute([$payment_id]);

    try {
        $db->prepare("INSERT INTO logs (user_id, action, created_at) VALUES (?, ?, $nowExpr)")
           ->execute([$_SESSION['user_id'], "Approved payment #$payment_id ({$payment['type']} ৳{$payment['amount']})"]);
    } catch (Throwable $t) {}

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    die("FAILED: " . $e->getMessage());
}

header("Location: payments.php?approved=1");
exit;
