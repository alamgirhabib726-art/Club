<?php
/**
 * UNMOOR CLUB - UNIFIED TRADING ORDER API
 * File: /trade/api/order.php
 * Handles Leveraged Futures Orders, Spot Market/Limit Orders, Close, and Cancellations.
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
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'open_leverage':
        case 'open':
            $side = strtolower(trim($_POST['side'] ?? ''));
            // Normalize direction if passed as numeric 1 / -1
            if (isset($_POST['direction'])) {
                $dir = (int)$_POST['direction'];
                $side = ($dir === 1) ? 'long' : 'short';
            }

            $margin = (float)($_POST['margin'] ?? 0);
            $leverage = (int)($_POST['leverage'] ?? 1);
            $sl = isset($_POST['stop_loss']) && is_numeric($_POST['stop_loss']) ? (float)$_POST['stop_loss'] : null;
            $tp = isset($_POST['take_profit']) && is_numeric($_POST['take_profit']) ? (float)$_POST['take_profit'] : null;

            $result = PositionEngine::openPosition($db, $userId, $side, $margin, $leverage, $sl, $tp);
            echo json_encode($result);
            break;

        case 'close_position':
        case 'close':
            $posId = (int)($_POST['position_id'] ?? 0);
            if ($posId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid position ID.']);
                exit;
            }

            $result = PositionEngine::closePosition($db, $userId, $posId, 'user_close');
            echo json_encode($result);
            break;

        case 'update_sl_tp':
            $posId = (int)($_POST['position_id'] ?? 0);
            $sl = (isset($_POST['stop_loss']) && is_numeric($_POST['stop_loss']) && (float)$_POST['stop_loss'] > 0) ? (float)$_POST['stop_loss'] : null;
            $tp = (isset($_POST['take_profit']) && is_numeric($_POST['take_profit']) && (float)$_POST['take_profit'] > 0) ? (float)$_POST['take_profit'] : null;

            if ($posId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid position ID.']);
                exit;
            }

            $result = PositionEngine::updateSlTp($db, $userId, $posId, $sl, $tp);
            echo json_encode($result);
            break;

        case 'spot_order':
            $side = strtolower(trim($_POST['side'] ?? ''));
            $amount = (float)($_POST['amount'] ?? 0);
            $orderType = strtolower(trim($_POST['order_type'] ?? 'market'));
            $limitPrice = isset($_POST['limit_price']) && is_numeric($_POST['limit_price']) ? (float)$_POST['limit_price'] : null;

            $result = MarketEngine::placeSpotOrder($db, $userId, $side, $amount, $orderType, $limitPrice);
            echo json_encode($result);
            break;

        case 'cancel_order':
            $orderId = (int)($_POST['order_id'] ?? 0);
            if ($orderId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid order ID.']);
                exit;
            }

            $result = MarketEngine::cancelOrder($db, $userId, $orderId);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unrecognized order action.']);
            break;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Order processing exception: ' . $e->getMessage()
    ]);
}
