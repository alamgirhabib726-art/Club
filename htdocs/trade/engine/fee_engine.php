<?php
/**
 * ============================================
 * UC FEE ENGINE
 * Leverage-based fee → system income
 * ============================================
 */

require_once __DIR__ . "/../../db.php";

/* ================= SYSTEM ACCOUNT ================= */
$systemId = $db->query("
    SELECT id FROM users WHERE role='system' LIMIT 1
")->fetchColumn();

if (!$systemId) {
    die("SYSTEM ACCOUNT MISSING");
}

/* ================= FEE CALCULATOR ================= */
function calculateFee(float $margin, int $leverage): float
{
    // base rule: 0.04% per leverage
    $percent = $leverage * 0.0004;

    // cap fee at 3%
    if ($percent > 0.03) {
        $percent = 0.03;
    }

    return round($margin * $percent, 2);
}

/* ================= APPLY FEE ================= */
function applyTradeFee(PDO $db, int $userId, float $margin, int $leverage): float
{
    global $systemId;

    $fee = calculateFee($margin, $leverage);

    if ($fee <= 0) return 0;

    $db->beginTransaction();
    try {

        /* CUT FROM USER */
        $db->prepare("
            UPDATE users
            SET coins = coins - ?
            WHERE id = ?
        ")->execute([$fee, $userId]);

        /* ADD TO SYSTEM */
        $db->prepare("
            UPDATE users
            SET coins = coins + ?
            WHERE id = ?
        ")->execute([$fee, $systemId]);

        /* LOG FEE */
        $db->prepare("
            INSERT INTO coin_history
            (user_id, amount, type, reference, created_at)
            VALUES (?, ?, 'trade_fee', 'UC Leverage Fee', NOW())
        ")->execute([$systemId, $fee]);

        $db->commit();

        return $fee;

    } catch (Exception $e) {
        $db->rollBack();
        return 0;
    }
}