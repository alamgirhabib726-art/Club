<?php
/**
 * ============================================
 * UC LIQUIDATION ENGINE
 * ============================================
 */

require_once __DIR__ . "/../../db.php";

/* ================= CURRENT PRICE ================= */
$price = (float)$db->query("
    SELECT price
    FROM uc_market
    ORDER BY id DESC
    LIMIT 1
")->fetchColumn();

if ($price <= 0) {
    die("NO PRICE");
}

/* ================= OPEN POSITIONS ================= */
$positions = $db->query("
    SELECT *
    FROM uc_positions
    WHERE status = 'open'
")->fetchAll(PDO::FETCH_ASSOC);

if (!$positions) {
    exit;
}

/* ================= LIQUIDITY ================= */
$liqId = $db->query("
    SELECT id FROM users WHERE role='liquidity' LIMIT 1
")->fetchColumn();

if (!$liqId) {
    die("NO LIQUIDITY");
}

/* ================= LOOP ================= */
foreach ($positions as $p) {

    $liquidate = false;

    if ($p['side'] === 'long') {
        if ($price <= $p['stop_loss']) $liquidate = true;
    } else {
        if ($price >= $p['stop_loss']) $liquidate = true;
    }

    if (!$liquidate) continue;

    /* ================= PNL ================= */
    if ($p['side'] === 'long') {
        $pnl = ($price - $p['entry_price']) * $p['size'];
    } else {
        $pnl = ($p['entry_price'] - $price) * $p['size'];
    }

    $pnl = max(-$p['margin'], $pnl);

    /* ================= TRANSACTION ================= */
    $db->beginTransaction();

    try {

        /* CLOSE */
        $db->prepare("
            UPDATE uc_positions
            SET
                status='liquidated',
                exit_price=?,
                pnl=?,
                closed_at=NOW()
            WHERE id=?
        ")->execute([$price, $pnl, $p['id']]);

        if ($pnl > 0) {
            // rare but allowed
            $db->prepare("
                UPDATE users
                SET coins = coins + ?
                WHERE id=?
            ")->execute([$p['margin'] + $pnl, $p['user_id']]);

            $db->prepare("
                UPDATE users
                SET coins = coins - ?
                WHERE id=?
            ")->execute([$pnl, $liqId]);

        } else {
            // loss
            $db->prepare("
                UPDATE users
                SET coins = coins + ?
                WHERE id=?
            ")->execute([$p['margin'] + $pnl, $liqId]);
        }

        /* HISTORY */
        $db->prepare("
            INSERT INTO coin_history
            (user_id, amount, type, reference, created_at)
            VALUES (?, ?, 'liquidation', 'UC Auto Liquidation', NOW())
        ")->execute([$p['user_id'], $pnl]);

        $db->prepare("
            INSERT INTO coin_history
            (user_id, amount, type, reference, created_at)
            VALUES (?, ?, 'liquidity_liq', 'UC Liquidation', NOW())
        ")->execute([$liqId, -$pnl]);

        $db->commit();

    } catch (Exception $e) {
        $db->rollBack();
    }
}

echo "LIQUIDATION DONE";