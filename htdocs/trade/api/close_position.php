<?php
/**
 * UNMOOR CLUB - CLOSE POSITION API
 * Endpoint for closing open positions with market settlement.
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../engine/fee_engine.php';
require_once __DIR__ . '/../engine/wallet_engine.php';
require_once __DIR__ . '/../engine/position_engine.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$positionId = (int)($_POST['position_id'] ?? ($_GET['position_id'] ?? 0));

if ($positionId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid position ID provided.']);
    exit;
}

try {
    $result = PositionEngine::closePosition($db, $userId, $positionId, 'user_close');
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to close position: ' . $e->getMessage()
    ]);
}
