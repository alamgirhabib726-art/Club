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
$id     = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!$id || !in_array($action, ['approve','reject'], true)) {
    die("INVALID REQUEST");
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
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("PAYMENT NOT FOUND");
}

if ($payment['status'] !== 'pending') {
    header("Location: payments.php");
    exit;
}

/* ===============================
   TRANSACTION START
================================ */
$db->beginTransaction();

try {

    /* ===============================
       APPROVE
    ================================ */
    if ($action === 'approve') {

        /* ===== APPLY PAYMENT ===== */
        if ($payment['type'] === 'apply') {

            /* ACTIVATE USER (CORRECT 24H LOGIC) */
            $db->prepare("
                UPDATE users
                SET
                    status = 'active',
                    apply_status = 'approved',
                    coins = COALESCE(coins,0) + 5,
                    coin_cycle_start = NOW(),
                    last_coin_cut = NULL
                WHERE id = ?
            ")->execute([$payment['user_id']]);

            /* USER LEDGER */
            $db->prepare("
                INSERT INTO coin_history
                (user_id, amount, type, reference, created_at)
                VALUES (?, 5, 'apply_bonus', 'Application Approved', NOW())
            ")->execute([$payment['user_id']]);

            /* SYSTEM +10 COINS */
            $systemId = $db->query("
                SELECT id
                FROM users
                WHERE role = 'system'
                  AND status = 'active'
                LIMIT 1
            ")->fetchColumn();

            if ($systemId) {
                $db->prepare("
                    UPDATE users
                    SET coins = coins + 10
                    WHERE id = ?
                ")->execute([$systemId]);

                $db->prepare("
                    INSERT INTO coin_history
                    (user_id, amount, type, reference, created_at)
                    VALUES (?, 10, 'registration_income', 'User Registration Approved', NOW())
                ")->execute([$systemId]);
            }
        }

        /* ===== PREMIUM ===== */
        if ($payment['type'] === 'premium') {

            $db->prepare("
                UPDATE users
                SET status = 'premium'
                WHERE id = ?
            ")->execute([$payment['user_id']]);

            $db->prepare("
                INSERT INTO coin_history
                (user_id, amount, type, reference, created_at)
                VALUES (?, 0, 'premium_upgrade', 'Premium Approved', NOW())
            ")->execute([$payment['user_id']]);
        }

        /* ===== DEPOSIT ===== */
        if ($payment['type'] === 'deposit') {

            $coins = $payment['amount'] / 10;

            /* ADD USER COINS */
            $db->prepare("
                UPDATE users
                SET coins = coins + ?
                WHERE id = ?
            ")->execute([$coins, $payment['user_id']]);

            /* USER LEDGER */
            $db->prepare("
                INSERT INTO coin_history
                (user_id, amount, type, reference, created_at)
                VALUES (?, ?, 'deposit', 'Deposit Approved', NOW())
            ")->execute([
                $payment['user_id'],
                $coins
            ]);
        }

        /* MARK PAYMENT APPROVED */
        $db->prepare("
            UPDATE payments
            SET status = 'approved'
            WHERE id = ?
        ")->execute([$id]);
    }

    /* ===============================
       REJECT
    ================================ */
    if ($action === 'reject') {

        if ($payment['type'] === 'apply') {
            $db->prepare("
                UPDATE users
                SET apply_status = 'rejected'
                WHERE id = ?
            ")->execute([$payment['user_id']]);
        }

        $db->prepare("
            UPDATE payments
            SET status = 'rejected'
            WHERE id = ?
        ")->execute([$id]);
    }

    /* ===============================
       COMMIT
    ================================ */
    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    die("ACTION FAILED");
}

/* ===============================
   REDIRECT
================================ */
header("Location: payments.php");
exit;
