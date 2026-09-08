<?php
/**
 * ============================================
 * UC POSITIONS API (READ ONLY)
 * ============================================
 */

session_start();
require_once __DIR__ . "/../../db.php";

/* ================= AUTH ================= */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$uid = (int)$_SESSION['user_id'];

/* ================= CURRENT PRICE ================= */
$price = (float)$db->query("
    SELECT price
    FROM uc_market
    ORDER BY id DESC
    LIMIT 1
")->fetchColumn();

/* ================= USER POSITIONS ================= */
$stmt = $db->prepare("
    SELECT
        id,
        side,
        size,
        margin,
        leverage,
        entry_price,
        stop_loss,
        created_at
    FROM uc_positions
    WHERE user_id = ?
      AND status = 'open'
    ORDER BY id DESC
");
$stmt->execute([$uid]);
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= BUILD RESPONSE ================= */
$out = [];

foreach ($positions as $p) {

    if ($p['side'] === 'long') {
        $pnl = ($price - $p['entry_price']) * $p['size'];
    } else {
        $pnl = ($p['entry_price'] - $price) * $p['size'];
    }

    $out[] = [
        'id'        => $p['id'],
        'side'      => $p['side'],
        'size'      => round($p['size'], 4),
        'leverage'  => (int)$p['leverage'],
        'entry'     => round($p['entry_price'], 4),
        'stop'      => round($p['stop_loss'], 4),
        'pnl'       => round($pnl, 2),
        'pnl_pct'   => round(($pnl / $p['margin']) * 100, 2),
        'time'      => date("H:i:s", strtotime($p['created_at']))
    ];
}

header("Content-Type: application/json");
echo json_encode($out);