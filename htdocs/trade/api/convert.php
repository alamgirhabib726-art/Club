<?php
/**
 * UNMOOR CLUB - CONVERSION & WALLET API
 * Handles Club UC <-> BDT conversion (1 Club UC = 10 BDT with 1.345% fee)
 * and Main BDT <-> Trading BDT transfers.
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../engine/fee_engine.php';
require_once __DIR__ . '/../engine/wallet_engine.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);

try {
    switch ($action) {
        case 'club_uc_to_bdt':
            $result = WalletEngine::convertClubUcToBdt($db, $userId, $amount);
            echo json_encode($result);
            break;

        case 'bdt_to_club_uc':
            $result = WalletEngine::convertBdtToClubUc($db, $userId, $amount);
            echo json_encode($result);
            break;

        case 'main_to_trading':
            $result = WalletEngine::transferBdtWallet($db, $userId, $amount, 'main_to_trading');
            echo json_encode($result);
            break;

        case 'trading_to_main':
            $result = WalletEngine::transferBdtWallet($db, $userId, $amount, 'trading_to_main');
            echo json_encode($result);
            break;

        case 'get_balances':
            $result = WalletEngine::getComprehensiveBalances($db, $userId);
            echo json_encode($result);
            break;

        case 'calculate_quote':
            $type = $_GET['type'] ?? 'club_uc_to_bdt';
            $amt = (float)($_GET['amount'] ?? 0);
            if ($amt <= 0) {
                echo json_encode(['success' => false, 'error' => 'Amount must be greater than 0']);
                exit;
            }

            if ($type === 'club_uc_to_bdt') {
                $grossBdt = round($amt * 10.0, 4);
                $feeData = FeeEngine::calculateConversionFee($db, $grossBdt);
                echo json_encode([
                    'success' => true,
                    'type' => 'club_uc_to_bdt',
                    'from_amount' => $amt,
                    'from_asset' => 'Club_UC',
                    'rate' => '1 Club UC = 10 BDT',
                    'gross_bdt' => $grossBdt,
                    'fee_rate_pct' => ($feeData['rate'] * 100) . '%',
                    'fee_bdt' => $feeData['fee'],
                    'net_bdt' => $feeData['net']
                ]);
            } else {
                $grossClubUc = round($amt / 10.0, 4);
                $feeData = FeeEngine::calculateConversionFee($db, $amt);
                $feeClubUc = round($grossClubUc * $feeData['rate'], 4);
                $netClubUc = round($grossClubUc - $feeClubUc, 4);
                echo json_encode([
                    'success' => true,
                    'type' => 'bdt_to_club_uc',
                    'from_amount' => $amt,
                    'from_asset' => 'BDT',
                    'rate' => '10 BDT = 1 Club UC',
                    'gross_club_uc' => $grossClubUc,
                    'fee_rate_pct' => ($feeData['rate'] * 100) . '%',
                    'fee_bdt' => $feeData['fee'],
                    'fee_club_uc' => $feeClubUc,
                    'net_club_uc' => $netClubUc
                ]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unrecognized conversion action.']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Conversion API Error: ' . $e->getMessage()
    ]);
}
