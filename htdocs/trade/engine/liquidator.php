<?php
/**
 * ============================================
 * UC LIQUIDATION ENGINE
 * ============================================
 * - SL / TP / Liquidation
 * - PnL settlement
 */

require_once __DIR__ . "/../../db.php";

/* ================= CONFIG ================= */
$SYMBOL = 'UC';
$MAINTENANCE_FLOOR = 5; // system coin floor

/* ================= MARKET PRICE ================= */
$price = (float)$db->query("
    SELECT price FROM market_state
    WHERE symbol='$SYMBOL'
    LIMIT 1
")->fetchColumn();

/* ================= SYSTEM ================= */
$systemId = $db->query("
    SELECT id FROM users WHERE role='system' LIMIT 1
")->fetchColumn();

$systemCoins = (float)$db->query("
    SELECT coins FROM users WHERE id=$systemId
")->fetchColumn();

/* ================= POSITIONS ================= */
$positions = $db->query("
    SELECT * FROM positions
    WHERE status='open'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($positions as $p) {

    $close = false;
    $reason = '';
    $pnl = 0;

    /* ===== PRICE DIRECTION ===== */
    $dir = ($p['side'] === 'long') ? 1 : -1;

    /* ===== LIQUIDATION ===== */
    if (
        ($p['side'] === 'long' && $price <= $p['liq_price']) ||
        ($p['side'] === 'short' && $price >= $p['liq_price'])
    ) {
        $close = true;
        $reason = 'liquidation';
        $pnl = -$p['margin'];
    }

    /* ===== STOP LOSS ===== */
    elseif (
        ($p['side'] === 'long' && $price <= $p['sl']) ||
        ($p['side'] === 'short' && $price >= $p['sl'])
    ) {
        $close = true;
        $reason = 'stop_loss';
        $pnl = -$p['margin'] * 0.9;
    }

    /* ===== TAKE PROFIT ===== */
    elseif (
        ($p['side'] === 'long' && $price >= $p['tp']) ||
        ($p['side'] === 'short' && $price <= $p['tp'])
    ) {
        if ($systemCoins > $MAINTENANCE_FLOOR) {
            $close = true;
            $reason = 'take_profit';
            $pnl = $p['margin'] * ($p['leverage'] / 10);
        }
    }

    if (!$close) continue;

    /* ================= FEES ================= */
    $fee = max(0.5, $p['leverage'] * 0.02); // leverage-based fee
    $pnl -= $fee;

    /* ================= SETTLEMENT ================= */
    $db->beginTransaction();

    try {

        /* CLOSE POSITION */
        $db->prepare("
            UPDATE positions
            SET status='closed',
                close_price=?,
                pnl=?,
                close_reason=?,
                closed_at=NOW()
            WHERE id=?
        ")->execute([$price, $pnl, $reason, $p['id']]);

        /* USER UPDATE */
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id = ?
        ")->execute([$pnl, $p['user_id']]);

        /* SYSTEM UPDATE */
        $db->prepare("
            UPDATE users
            SET coins = coins - ?
            WHERE id = ?
        ")->execute([$pnl, $systemId]);

        /* HISTORY */
        $db->prepare("
            INSERT INTO trade_history
            (user_id, symbol, side, pnl, reason, closed_at)
            VALUES (?, 'UC', ?, ?, ?, NOW())
        ")->execute([
            $p['user_id'],
            $p['side'],
            $pnl,
            $reason
        ]);

        $db->commit();

    } catch (Exception $e) {
        $db->rollBack();
    }
}

echo "LIQUIDATION DONE";