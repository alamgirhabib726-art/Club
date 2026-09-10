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
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

    /* ===============================
       APPROVE
    ================================ */
    if ($action === 'approve') {

        /* ===== APPLY PAYMENT ===== */
        if ($payment['type'] === 'apply') {
            $db->prepare("
                UPDATE users
                SET
                    status = 'active',
                    apply_status = 'approved',
                    coins = COALESCE(coins,0) + 5,
                    coin_cycle_start = $nowExpr,
                    last_coin_cut = NULL
                WHERE id = ?
            ")->execute([$payment['user_id']]);

            $db->prepare("
                INSERT INTO coin_history
                (user_id, amount, type, source, created_at)
                VALUES (?, 5, 'apply_bonus', 'Application Approved', $nowExpr)
            ")->execute([$payment['user_id']]);

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
                    (user_id, amount, type, source, created_at)
                    VALUES (?, 10, 'registration_income', 'User Registration Approved', $nowExpr)
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
                (user_id, amount, type, source, created_at)
                VALUES (?, 0, 'premium_upgrade', 'Premium Approved', $nowExpr)
            ")->execute([$payment['user_id']]);
        }

        /* ===== DEPOSIT ===== */
        if ($payment['type'] === 'deposit') {
            $coins = (float)$payment['amount'] / 10;

            $db->prepare("
                UPDATE users
                SET coins = coins + ?
                WHERE id = ?
            ")->execute([$coins, $payment['user_id']]);

            $db->prepare("
                INSERT INTO coin_history
                (user_id, amount, type, source, created_at)
                VALUES (?, ?, 'deposit', 'Deposit Approved', $nowExpr)
            ")->execute([
                $payment['user_id'],
                $coins
            ]);
        }

        /* ===== PURCHASE / STORE ORDER ===== */
        if ($payment['type'] === 'purchase') {
            $amount = (float)$payment['amount'];
            $userId = (int)$payment['user_id'];

            // Deduct permanently from buyer's locked_coins
            $db->prepare("
                UPDATE users
                SET locked_coins = CASE WHEN locked_coins >= ? THEN locked_coins - ? ELSE 0 END
                WHERE id = ?
            ")->execute([$amount, $amount, $userId]);

            // Credit to Club Fund (System Treasury account)
            $systemId = $db->query("
                SELECT id FROM users WHERE role = 'system' LIMIT 1
            ")->fetchColumn();

            if ($systemId) {
                $db->prepare("
                    UPDATE users SET coins = coins + ? WHERE id = ?
                ")->execute([$amount, (int)$systemId]);

                $db->prepare("
                    INSERT INTO coin_history (user_id, amount, type, reference, source_user_id, created_at)
                    VALUES (?, ?, 'purchase_revenue', ?, ?, $nowExpr)
                ")->execute([(int)$systemId, $amount, "Product Sale Revenue from Order #$id", $userId]);
            }

            $db->prepare("
                INSERT INTO coin_history (user_id, amount, type, reference, created_at)
                VALUES (?, 0, 'purchase_completed', ?, $nowExpr)
            ")->execute([$userId, "Order #$id approved and completed (Coins transferred to Club Fund)"]);

            $db->prepare("
                INSERT INTO system_ledger (type, amount, source, reference, created_at)
                VALUES ('purchase_approved', ?, 'STORE', ?, $nowExpr)
            ")->execute([$amount, "Order #$id approved for User #$userId (Funded Club Reserve)"]);
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

        if ($payment['type'] === 'purchase') {
            $amount = (float)$payment['amount'];
            $userId = (int)$payment['user_id'];

            // Refund locked coins back to available coins
            $db->prepare("
                UPDATE users
                SET coins = coins + ?,
                    locked_coins = CASE WHEN locked_coins >= ? THEN locked_coins - ? ELSE 0 END
                WHERE id = ?
            ")->execute([$amount, $amount, $amount, $userId]);

            $db->prepare("
                INSERT INTO coin_history (user_id, amount, type, reference, created_at)
                VALUES (?, ?, 'order_refund', ?, $nowExpr)
            ")->execute([$userId, $amount, "Order #$id rejected - coins returned to available balance"]);
        }

        $db->prepare("
            UPDATE payments
            SET status = 'rejected'
            WHERE id = ?
        ")->execute([$id]);
    }

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    die("ACTION FAILED: " . $e->getMessage());
}

header("Location: payments.php");
exit;
