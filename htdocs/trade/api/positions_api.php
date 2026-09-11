<?php
/**
 * UNMOOR CLUB - POSITIONS & USER PORTFOLIO API
 * Returns live open positions with dynamically calculated PnL/ROI, open orders, and trade history.
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../engine/fee_engine.php';
require_once __DIR__ . '/../engine/wallet_engine.php';
require_once __DIR__ . '/../engine/position_engine.php';
require_once __DIR__ . '/../engine/market_engine.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    MarketEngine::heartbeat($db);

    $mState = $db->query("SELECT price FROM market_state WHERE symbol = 'UC' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $markPrice = (float)($mState['price'] ?? 2.0000);

    // 1. Fetch Open Positions
    $posStmt = $db->prepare("SELECT * FROM trading_positions WHERE user_id = ? AND status = 'open' ORDER BY id DESC");
    $posStmt->execute([$userId]);
    $openRaw = $posStmt->fetchAll(PDO::FETCH_ASSOC);

    $openPositions = [];
    $totalUnrealizedPnl = 0.0;

    foreach ($openRaw as $p) {
        $side = $p['side'];
        $entry = (float)$p['entry_price'];
        $size = (float)$p['position_size'];
        $margin = (float)$p['margin'];
        $leverage = (int)$p['leverage'];
        $liqPrice = (float)$p['liquidation_price'];

        $pnl = PositionEngine::calculatePnL($side, $entry, $markPrice, $size);
        $roi = PositionEngine::calculateRoi($pnl, $margin);
        $totalUnrealizedPnl += $pnl;

        // Distance to liquidation %
        $distToLiq = 0.0;
        if ($entry > 0) {
            $distToLiq = abs(($markPrice - $liqPrice) / $markPrice) * 100.0;
        }

        $openPositions[] = [
            'id' => (int)$p['id'],
            'symbol' => $p['symbol'],
            'side' => $side,
            'entry_price' => $entry,
            'mark_price' => $markPrice,
            'position_size' => $size,
            'margin' => $margin,
            'leverage' => $leverage,
            'liquidation_price' => $liqPrice,
            'distance_to_liq_pct' => round($distToLiq, 2),
            'stop_loss' => ($p['stop_loss'] !== null && (float)$p['stop_loss'] > 0) ? (float)$p['stop_loss'] : null,
            'take_profit' => ($p['take_profit'] !== null && (float)$p['take_profit'] > 0) ? (float)$p['take_profit'] : null,
            'entry_fee' => (float)$p['entry_fee'],
            'unrealized_pnl' => round($pnl, 4),
            'roi_pct' => round($roi, 2),
            'created_at' => $p['created_at']
        ];
    }

    // 2. Fetch Closed/Liquidated Positions
    $closedStmt = $db->prepare("SELECT * FROM trading_positions WHERE user_id = ? AND status IN ('closed', 'liquidated') ORDER BY id DESC LIMIT 25");
    $closedStmt->execute([$userId]);
    $closedRaw = $closedStmt->fetchAll(PDO::FETCH_ASSOC);

    $closedPositions = [];
    foreach ($closedRaw as $c) {
        $closedPositions[] = [
            'id' => (int)$c['id'],
            'symbol' => $c['symbol'],
            'side' => $c['side'],
            'entry_price' => (float)$c['entry_price'],
            'exit_price' => (float)($c['exit_price'] ?? 0),
            'margin' => (float)$c['margin'],
            'leverage' => (int)$c['leverage'],
            'position_size' => (float)$c['position_size'],
            'pnl' => (float)$c['pnl'],
            'roi_pct' => (float)$c['roi'],
            'status' => $c['status'],
            'close_reason' => $c['close_reason'] ?? 'closed',
            'created_at' => $c['created_at'],
            'closed_at' => $c['closed_at']
        ];
    }

    // 3. Fetch Open Limit Orders
    $orderStmt = $db->prepare("SELECT * FROM trading_orders WHERE user_id = ? AND status = 'open' ORDER BY id DESC");
    $orderStmt->execute([$userId]);
    $openOrders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Order History
    $histStmt = $db->prepare("SELECT * FROM trading_orders WHERE user_id = ? AND status IN ('filled', 'cancelled', 'rejected') ORDER BY id DESC LIMIT 25");
    $histStmt->execute([$userId]);
    $orderHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch Balances
    $balances = WalletEngine::getComprehensiveBalances($db, $userId);

    echo json_encode([
        'success' => true,
        'open_positions' => $openPositions,
        'closed_positions' => $closedPositions,
        'open_orders' => $openOrders,
        'order_history' => $orderHistory,
        'total_unrealized_pnl' => round($totalUnrealizedPnl, 4),
        'balances' => $balances,
        'mark_price' => $markPrice
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
