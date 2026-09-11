<?php
/**
 * UNMOOR CLUB - PROFESSIONAL CURRENCY CONVERSION & WALLET MANAGER
 * 1 Club UC = 10 BDT (1.345% Liquidity Pool Fee)
 * Instant Main BDT <-> Trading Margin Transfers (0% Fee)
 */

session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/components.php";
require_once __DIR__ . "/trade/engine/fee_engine.php";
require_once __DIR__ . "/trade/engine/wallet_engine.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$balances = WalletEngine::getComprehensiveBalances($db, $uid);

// Fetch recent conversions for this user
$cHistStmt = $db->prepare("SELECT * FROM conversions WHERE user_id = ? ORDER BY id DESC LIMIT 15");
$cHistStmt->execute([$uid]);
$conversions = $cHistStmt->fetchAll(PDO::FETCH_ASSOC);

$clubUc = (float)($balances['club_wallet']['club_uc'] ?? 0);
$mainBdt = (float)($balances['main_wallet']['bdt_balance'] ?? 0);
$tradingBdt = (float)($balances['trading_wallet']['bdt_balance'] ?? 0);
$availMargin = (float)($balances['trading_wallet']['available_margin'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Instant Convert & Wallets — Unmoor Club</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        :root {
            --bg-body: #070b14;
            --surface-1: #0f172a;
            --surface-2: #131d35;
            --surface-3: #1a2744;
            --border-line: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(59, 130, 246, 0.4);
            --brand-blue: #3b82f6;
            --brand-green: #10b981;
            --brand-gold: #f59e0b;
            --brand-purple: #8b5cf6;
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .convert-container {
            max-width: 580px;
            margin: 0 auto;
            padding: 16px 14px 60px;
        }

        /* Top Bar Navigation */
        .page-nav-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding: 4px 0;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-sub);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-line);
            padding: 8px 14px;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
        }
        .terminal-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #60a5fa;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            background: rgba(59, 130, 246, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.25);
            padding: 8px 14px;
            border-radius: 10px;
            transition: all 0.2s;
        }
        .terminal-link:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        /* Balance Cards Strip */
        .balance-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .bal-chip {
            background: var(--surface-1);
            border: 1px solid var(--border-line);
            border-radius: 12px;
            padding: 10px 8px;
            text-align: center;
        }
        .bal-chip-label {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bal-chip-val {
            font-size: 14px;
            font-weight: 800;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bal-gold { color: #fbbf24; }
        .bal-green { color: #34d399; }
        .bal-blue { color: #60a5fa; }

        /* Segmented Mode Tabs */
        .segmented-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--surface-1);
            border: 1px solid var(--border-line);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 16px;
        }
        .tab-trigger {
            background: transparent;
            border: none;
            color: var(--text-sub);
            padding: 10px 8px;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .tab-trigger.active {
            background: var(--surface-3);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        /* Swap Card Container */
        .swap-box {
            background: var(--surface-1);
            border: 1px solid var(--border-line);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            margin-bottom: 20px;
        }

        /* Asset Input Field Block */
        .asset-input-block {
            background: var(--surface-2);
            border: 1px solid var(--border-line);
            border-radius: 14px;
            padding: 14px;
            transition: border-color 0.2s;
        }
        .asset-input-block:focus-within {
            border-color: var(--brand-blue);
        }
        .asset-input-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .asset-input-label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .asset-input-balance {
            font-size: 11.5px;
            color: var(--text-sub);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .max-btn {
            background: rgba(59, 130, 246, 0.18);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 6px;
            padding: 2px 7px;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
        }
        .max-btn:hover {
            background: rgba(59, 130, 246, 0.3);
            color: #ffffff;
        }

        .asset-input-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .asset-number-input {
            width: 100%;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 24px;
            font-weight: 800;
            outline: none;
            font-family: inherit;
        }
        .asset-number-input::placeholder {
            color: rgba(255, 255, 255, 0.2);
        }
        .asset-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 800;
            white-space: nowrap;
            color: #ffffff;
        }

        /* Swap Divider / Flip Button */
        .swap-divider {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -8px 0;
            z-index: 5;
        }
        .flip-btn {
            background: var(--surface-3);
            border: 2px solid var(--surface-1);
            color: #60a5fa;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }
        .flip-btn:hover {
            background: var(--brand-blue);
            color: #ffffff;
            transform: rotate(180deg) scale(1.08);
        }

        /* Exchange Breakdown Card */
        .breakdown-card {
            background: rgba(255, 255, 255, 0.025);
            border: 1px dashed rgba(255, 255, 255, 0.09);
            border-radius: 12px;
            padding: 12px 14px;
            margin: 16px 0;
            font-size: 12px;
        }
        .breakdown-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 0;
            color: var(--text-sub);
        }
        .breakdown-row strong {
            color: #ffffff;
        }
        .breakdown-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            margin: 6px 0;
        }

        /* Action Buttons */
        .btn-action-primary {
            width: 100%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            padding: 14px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 18px rgba(37, 99, 235, 0.35);
        }
        .btn-action-primary:hover {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            transform: translateY(-1px);
        }
        .btn-action-gold {
            background: linear-gradient(135deg, #eab308, #ca8a04);
            border: 1px solid rgba(234, 179, 8, 0.4);
            box-shadow: 0 4px 18px rgba(234, 179, 8, 0.3);
            color: #0f172a;
        }
        .btn-action-gold:hover {
            background: linear-gradient(135deg, #facc15, #eab308);
        }

        /* Transfer Mode Specifics */
        .transfer-direction-card {
            background: var(--surface-2);
            border: 1px solid var(--border-line);
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .transfer-node {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .transfer-node-label {
            font-size: 10.5px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
        }
        .transfer-node-val {
            font-size: 13.5px;
            font-weight: 800;
            color: #ffffff;
        }

        /* History Section */
        .history-card {
            background: var(--surface-1);
            border: 1px solid var(--border-line);
            border-radius: 16px;
            padding: 16px;
        }
        .history-header {
            font-size: 13.5px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .history-item {
            background: var(--surface-2);
            border: 1px solid var(--border-line);
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
        }
        .history-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .history-pair {
            font-weight: 800;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .history-time {
            font-size: 10.5px;
            color: var(--text-muted);
        }
        .history-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .history-amt {
            font-weight: 800;
            color: #34d399;
        }
        .history-fee {
            font-size: 10.5px;
            color: #f59e0b;
        }

        /* CUSTOM PROFESSIONAL MODAL SYSTEM */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .custom-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .custom-modal-card {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            padding: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
            transform: scale(0.95);
            transition: transform 0.2s ease;
            text-align: center;
        }
        .custom-modal-overlay.active .custom-modal-card {
            transform: scale(1);
        }
        .modal-icon-badge {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 14px;
        }
        .modal-icon-confirm {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .modal-icon-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .modal-icon-error {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .modal-icon-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .modal-title {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .modal-desc {
            font-size: 13px;
            color: var(--text-sub);
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .modal-details-box {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 12.5px;
        }
        .modal-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            color: var(--text-sub);
        }
        .modal-row strong {
            color: #ffffff;
        }
        .modal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .modal-btn-cancel {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-weight: 700;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13.5px;
        }
        .modal-btn-confirm {
            background: #2563eb;
            border: 1px solid #3b82f6;
            color: #ffffff;
            font-weight: 800;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13.5px;
        }
        .modal-btn-single {
            grid-column: span 2;
        }

        /* TOAST NOTIFICATION */
        .toast-container {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }
        .toast-bubble {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            gap: 8px;
            pointer-events: auto;
            animation: slideUpToast 0.25s ease-out;
        }
        @keyframes slideUpToast {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
    <?php render_unified_nav('dashboard'); ?>

    <main class="convert-container">
        <!-- Navigation Strip -->
        <div class="page-nav-bar">
            <a href="/dashboard.php" class="back-link">
                <span>←</span>
                <span>Dashboard</span>
            </a>
            <a href="/trade/" class="terminal-link">
                <span>📈</span>
                <span>Open Trading</span>
            </a>
        </div>

        <!-- Wallets Snapshot -->
        <div class="balance-strip">
            <div class="bal-chip">
                <div class="bal-chip-label">Club UC</div>
                <div class="bal-chip-val bal-gold" id="bal-club-uc"><?= number_format($clubUc, 2) ?> UC</div>
            </div>
            <div class="bal-chip">
                <div class="bal-chip-label">Main BDT</div>
                <div class="bal-chip-val bal-green" id="bal-main-bdt">৳ <?= number_format($mainBdt, 2) ?></div>
            </div>
            <div class="bal-chip">
                <div class="bal-chip-label">Trading Margin</div>
                <div class="bal-chip-val bal-blue" id="bal-trading-bdt">৳ <?= number_format($tradingBdt, 2) ?></div>
            </div>
        </div>

        <!-- Segmented Tab Bar -->
        <div class="segmented-tabs">
            <button class="tab-trigger active" id="tab-btn-convert" onclick="setMode('convert')">
                <span>💱</span>
                <span>Instant Convert</span>
            </button>
            <button class="tab-trigger" id="tab-btn-transfer" onclick="setMode('transfer')">
                <span>⇄</span>
                <span>Margin Transfer</span>
            </button>
        </div>

        <!-- MODE 1: CONVERT BOX -->
        <div id="panel-convert" class="swap-box">
            <!-- Source Asset Block -->
            <div class="asset-input-block">
                <div class="asset-input-header">
                    <span class="asset-input-label">You Pay</span>
                    <div class="asset-input-balance">
                        <span>Avail: <strong id="from-avail-val"><?= number_format($clubUc, 2) ?></strong> <span id="from-avail-unit">UC</span></span>
                        <button type="button" class="max-btn" onclick="applyMaxFrom()">MAX</button>
                    </div>
                </div>
                <div class="asset-input-row">
                    <input type="number" id="from-amount-input" class="asset-number-input" placeholder="0.00" step="any" min="0" oninput="handleFromInput()">
                    <div class="asset-badge" id="from-badge">
                        <span id="from-icon">🪙</span>
                        <span id="from-symbol">Club UC</span>
                    </div>
                </div>
            </div>

            <!-- Flip Button -->
            <div class="swap-divider">
                <button type="button" class="flip-btn" title="Switch Direction" onclick="toggleConvertDirection()">
                    ⇅
                </button>
            </div>

            <!-- Target Asset Block -->
            <div class="asset-input-block">
                <div class="asset-input-header">
                    <span class="asset-input-label">You Receive (Estimated)</span>
                    <div class="asset-input-balance">
                        <span>Balance: <strong id="to-avail-val">৳ <?= number_format($mainBdt, 2) ?></strong></span>
                    </div>
                </div>
                <div class="asset-input-row">
                    <input type="text" id="to-amount-input" class="asset-number-input" placeholder="0.00" readonly>
                    <div class="asset-badge" id="to-badge">
                        <span id="to-icon">৳</span>
                        <span id="to-symbol">Main BDT</span>
                    </div>
                </div>
            </div>

            <!-- Breakdown -->
            <div class="breakdown-card">
                <div class="breakdown-row">
                    <span>Guaranteed Rate</span>
                    <strong id="rate-display">1 Club UC = 10.00 BDT</strong>
                </div>
                <div class="breakdown-row">
                    <span>Platform Fee (1.345%)</span>
                    <span id="fee-display" style="color: #f59e0b;">৳ 0.00</span>
                </div>
                <div class="breakdown-divider"></div>
                <div class="breakdown-row">
                    <span>Net Expected</span>
                    <strong id="net-display" style="color: #34d399; font-size: 13.5px;">৳ 0.00</strong>
                </div>
            </div>

            <!-- Action Button -->
            <button type="button" class="btn-action-primary btn-action-gold" id="btn-convert-action" onclick="promptConvertConfirm()">
                <span>Convert Club UC to BDT</span>
            </button>
        </div>

        <!-- MODE 2: MARGIN TRANSFER BOX -->
        <div id="panel-transfer" class="swap-box" style="display: none;">
            <div class="transfer-direction-card">
                <div class="transfer-node">
                    <span class="transfer-node-label">From Account</span>
                    <span class="transfer-node-val" id="trans-from-label">Main BDT Wallet</span>
                    <span style="font-size: 11px; color: #34d399;">Avail: ৳ <span id="trans-from-avail"><?= number_format($mainBdt, 2) ?></span></span>
                </div>
                <button type="button" class="flip-btn" style="width: 34px; height: 34px;" title="Switch Wallet Direction" onclick="toggleTransferDirection()">
                    ⇄
                </button>
                <div class="transfer-node" style="text-align: right;">
                    <span class="transfer-node-label">To Account</span>
                    <span class="transfer-node-val" id="trans-to-label">Trading Margin Wallet</span>
                    <span style="font-size: 11px; color: #60a5fa;">Balance: ৳ <span id="trans-to-avail"><?= number_format($tradingBdt, 2) ?></span></span>
                </div>
            </div>

            <div class="asset-input-block" style="margin-top: 14px;">
                <div class="asset-input-header">
                    <span class="asset-input-label">Transfer Amount</span>
                    <div class="asset-input-balance">
                        <button type="button" class="max-btn" onclick="applyMaxTransfer()">MAX</button>
                    </div>
                </div>
                <div class="asset-input-row">
                    <input type="number" id="transfer-amount-input" class="asset-number-input" placeholder="0.00" step="any" min="1">
                    <div class="asset-badge">
                        <span>৳</span>
                        <span>BDT</span>
                    </div>
                </div>
            </div>

            <div class="breakdown-card" style="margin-top: 14px;">
                <div class="breakdown-row">
                    <span>Internal Transfer Fee</span>
                    <strong style="color: #34d399;">0.00% (Instant Free)</strong>
                </div>
                <div class="breakdown-row">
                    <span>Settlement</span>
                    <span>Direct Balance Synchronization</span>
                </div>
            </div>

            <button type="button" class="btn-action-primary" id="btn-transfer-action" onclick="promptTransferConfirm()">
                <span>Transfer Funds</span>
            </button>
        </div>

        <!-- RECENT CONVERSIONS -->
        <div class="history-card">
            <div class="history-header">
                <span>Recent Conversions</span>
                <span style="font-size: 11px; color: var(--text-muted);">Last 15 records</span>
            </div>

            <?php if (empty($conversions)): ?>
                <div style="text-align: center; padding: 24px 0; color: var(--text-muted); font-size: 12.5px;">
                    No conversion records yet.
                </div>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($conversions as $c): ?>
                        <div class="history-item">
                            <div class="history-left">
                                <div class="history-pair">
                                    <span><?= $c['from_asset'] === 'Club_UC' ? '🪙 Club UC ➔ ৳ BDT' : '৳ BDT ➔ 🪙 Club UC' ?></span>
                                </div>
                                <span class="history-time"><?= date('M d, H:i', strtotime($c['created_at'])) ?></span>
                            </div>
                            <div class="history-right">
                                <span class="history-amt">
                                    +<?= $c['to_asset'] === 'BDT' ? '৳ ' . number_format($c['to_amount_net'], 2) : number_format($c['to_amount_net'], 4) . ' UC' ?>
                                </span>
                                <span class="history-fee">Fee: ৳ <?= number_format($c['fee_amount'], 2) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- CUSTOM CONFIRMATION & ALERT MODAL (NO GOOGLE BROWSER POPUPS) -->
    <div class="custom-modal-overlay" id="custom-modal-overlay">
        <div class="custom-modal-card" id="custom-modal-card">
            <div class="modal-icon-badge modal-icon-confirm" id="modal-icon-wrapper">
                <span id="modal-icon-glyph">💱</span>
            </div>
            <h3 class="modal-title" id="modal-title">Confirm Action</h3>
            <p class="modal-desc" id="modal-desc">Please review the details below.</p>

            <div class="modal-details-box" id="modal-details-box" style="display: none;">
                <!-- dynamic rows injected here -->
            </div>

            <div class="modal-actions" id="modal-actions">
                <button type="button" class="modal-btn-cancel" id="modal-btn-cancel" onclick="closeCustomModal()">Cancel</button>
                <button type="button" class="modal-btn-confirm" id="modal-btn-confirm">Confirm</button>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFICATION ROOT -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        // State
        let userClubUc = <?= $clubUc ?>;
        let userMainBdt = <?= $mainBdt ?>;
        let userTradingBdt = <?= $tradingBdt ?>;
        let userAvailMargin = <?= $availMargin ?>;

        let convertDirection = 'club_to_bdt'; // or 'bdt_to_club'
        let transferDirection = 'main_to_trading'; // or 'trading_to_main'
        const feePercent = 0.01345; // 1.345%

        // UI Toast Helper
        function showToast(msg, icon = 'ℹ️') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast-bubble';
            toast.innerHTML = `<span>${icon}</span><span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'all 0.25s ease';
                setTimeout(() => toast.remove(), 250);
            }, 3000);
        }

        // Custom Modal Controllers (Replaces window.alert and window.confirm completely)
        function openCustomModal({ icon = '💱', iconType = 'confirm', title, desc, details = [], confirmText = 'Confirm', onConfirm, isAlert = false }) {
            const overlay = document.getElementById('custom-modal-overlay');
            const iconWrapper = document.getElementById('modal-icon-wrapper');
            const iconGlyph = document.getElementById('modal-icon-glyph');
            const modalTitle = document.getElementById('modal-title');
            const modalDesc = document.getElementById('modal-desc');
            const detailsBox = document.getElementById('modal-details-box');
            const actionsBox = document.getElementById('modal-actions');
            const confirmBtn = document.getElementById('modal-btn-confirm');
            const cancelBtn = document.getElementById('modal-btn-cancel');

            // Set styling
            iconWrapper.className = `modal-icon-badge modal-icon-${iconType}`;
            iconGlyph.textContent = icon;
            modalTitle.textContent = title;
            modalDesc.textContent = desc;

            // Details rows
            if (details && details.length > 0) {
                detailsBox.style.display = 'block';
                detailsBox.innerHTML = details.map(d => `
                    <div class="modal-row">
                        <span>${d.label}</span>
                        <strong>${d.value}</strong>
                    </div>
                `).join('');
            } else {
                detailsBox.style.display = 'none';
            }

            // Actions
            if (isAlert) {
                cancelBtn.style.display = 'none';
                confirmBtn.className = 'modal-btn-confirm modal-btn-single';
                confirmBtn.textContent = confirmText || 'Got it';
                confirmBtn.onclick = () => closeCustomModal();
            } else {
                cancelBtn.style.display = 'block';
                confirmBtn.className = 'modal-btn-confirm';
                confirmBtn.textContent = confirmText || 'Confirm';
                confirmBtn.onclick = () => {
                    closeCustomModal();
                    if (typeof onConfirm === 'function') onConfirm();
                };
            }

            overlay.classList.add('active');
        }

        function closeCustomModal() {
            document.getElementById('custom-modal-overlay').classList.remove('active');
        }

        function showCustomAlert(title, message, iconType = 'warning', icon = '⚠️') {
            openCustomModal({
                icon,
                iconType,
                title,
                desc: message,
                details: [],
                isAlert: true
            });
        }

        // Mode Switching
        function setMode(mode) {
            document.getElementById('tab-btn-convert').classList.toggle('active', mode === 'convert');
            document.getElementById('tab-btn-transfer').classList.toggle('active', mode === 'transfer');
            document.getElementById('panel-convert').style.display = mode === 'convert' ? 'block' : 'none';
            document.getElementById('panel-transfer').style.display = mode === 'transfer' ? 'block' : 'none';
        }

        // Convert Logic
        function toggleConvertDirection() {
            convertDirection = (convertDirection === 'club_to_bdt') ? 'bdt_to_club' : 'club_to_bdt';
            updateConvertUI();
        }

        function updateConvertUI() {
            const fromIcon = document.getElementById('from-icon');
            const fromSymbol = document.getElementById('from-symbol');
            const fromAvailVal = document.getElementById('from-avail-val');
            const fromAvailUnit = document.getElementById('from-avail-unit');

            const toIcon = document.getElementById('to-icon');
            const toSymbol = document.getElementById('to-symbol');
            const toAvailVal = document.getElementById('to-avail-val');

            const rateDisplay = document.getElementById('rate-display');
            const btnAction = document.getElementById('btn-convert-action');

            if (convertDirection === 'club_to_bdt') {
                fromIcon.textContent = '🪙';
                fromSymbol.textContent = 'Club UC';
                fromAvailVal.textContent = userClubUc.toFixed(2);
                fromAvailUnit.textContent = 'UC';

                toIcon.textContent = '৳';
                toSymbol.textContent = 'Main BDT';
                toAvailVal.textContent = '৳ ' + userMainBdt.toFixed(2);

                rateDisplay.textContent = '1 Club UC = 10.00 BDT';
                btnAction.innerHTML = '<span>Convert Club UC to BDT</span>';
                btnAction.className = 'btn-action-primary btn-action-gold';
            } else {
                fromIcon.textContent = '৳';
                fromSymbol.textContent = 'Main BDT';
                fromAvailVal.textContent = '৳ ' + userMainBdt.toFixed(2);
                fromAvailUnit.textContent = '';

                toIcon.textContent = '🪙';
                toSymbol.textContent = 'Club UC';
                toAvailVal.textContent = userClubUc.toFixed(2) + ' UC';

                rateDisplay.textContent = '10.00 BDT = 1 Club UC';
                btnAction.innerHTML = '<span>Convert BDT to Club UC</span>';
                btnAction.className = 'btn-action-primary';
            }

            handleFromInput();
        }

        function applyMaxFrom() {
            const input = document.getElementById('from-amount-input');
            if (convertDirection === 'club_to_bdt') {
                input.value = userClubUc;
            } else {
                input.value = userMainBdt;
            }
            handleFromInput();
        }

        function handleFromInput() {
            const inputVal = parseFloat(document.getElementById('from-amount-input').value) || 0;
            const toInput = document.getElementById('to-amount-input');
            const feeDisplay = document.getElementById('fee-display');
            const netDisplay = document.getElementById('net-display');

            if (inputVal <= 0) {
                toInput.value = '0.00';
                feeDisplay.textContent = '৳ 0.00';
                netDisplay.textContent = '৳ 0.00';
                return;
            }

            if (convertDirection === 'club_to_bdt') {
                const grossBdt = inputVal * 10.0;
                const feeBdt = grossBdt * feePercent;
                const netBdt = grossBdt - feeBdt;

                toInput.value = netBdt.toFixed(2);
                feeDisplay.textContent = `৳ ${feeBdt.toFixed(2)} (1.345%)`;
                netDisplay.textContent = `৳ ${netBdt.toFixed(2)}`;
            } else {
                const grossUc = inputVal / 10.0;
                const feeBdt = inputVal * feePercent;
                const feeUc = grossUc * feePercent;
                const netUc = grossUc - feeUc;

                toInput.value = netUc.toFixed(4);
                feeDisplay.textContent = `৳ ${feeBdt.toFixed(2)} (${feeUc.toFixed(4)} UC)`;
                netDisplay.textContent = `${netUc.toFixed(4)} UC`;
            }
        }

        // Custom Confirmation Modal for Conversion
        function promptConvertConfirm() {
            const amount = parseFloat(document.getElementById('from-amount-input').value) || 0;
            
            if (amount <= 0) {
                showCustomAlert('Invalid Amount', 'Please enter an amount greater than 0 to proceed.', 'warning', '⚠️');
                return;
            }

            if (convertDirection === 'club_to_bdt') {
                if (amount > userClubUc) {
                    showCustomAlert(
                        'Insufficient Club UC', 
                        `You have ${userClubUc.toFixed(2)} UC available, but entered ${amount.toFixed(2)} UC. Please reduce the amount or participate in club activities to earn more.`,
                        'error',
                        '❌'
                    );
                    return;
                }

                const grossBdt = amount * 10.0;
                const feeBdt = grossBdt * feePercent;
                const netBdt = grossBdt - feeBdt;

                openCustomModal({
                    icon: '💱',
                    iconType: 'confirm',
                    title: 'Confirm UC Conversion',
                    desc: 'You are converting Club UC into Main BDT at the guaranteed 1:10 rate.',
                    details: [
                        { label: 'Pay Amount', value: `${amount.toFixed(2)} Club UC` },
                        { label: 'Fixed Rate', value: '1 UC = 10 BDT' },
                        { label: 'Platform Fee (1.345%)', value: `৳ ${feeBdt.toFixed(2)}` },
                        { label: 'Net BDT Received', value: `৳ ${netBdt.toFixed(2)}` },
                        { label: 'Destination', value: 'Main BDT Balance' }
                    ],
                    confirmText: 'Confirm & Convert',
                    onConfirm: () => executeConversion('club_uc_to_bdt', amount)
                });
            } else {
                if (amount > userMainBdt) {
                    showCustomAlert(
                        'Insufficient Main BDT', 
                        `You have ৳ ${userMainBdt.toFixed(2)} in your Main Balance, but entered ৳ ${amount.toFixed(2)}. Please deposit funds or adjust the amount.`,
                        'error',
                        '❌'
                    );
                    return;
                }

                const grossUc = amount / 10.0;
                const feeBdt = amount * feePercent;
                const netUc = grossUc - (grossUc * feePercent);

                openCustomModal({
                    icon: '🪙',
                    iconType: 'confirm',
                    title: 'Confirm BDT to UC',
                    desc: 'You are converting Main BDT into standard Club UC.',
                    details: [
                        { label: 'Pay Amount', value: `৳ ${amount.toFixed(2)}` },
                        { label: 'Fixed Rate', value: '10 BDT = 1 UC' },
                        { label: 'Platform Fee (1.345%)', value: `৳ ${feeBdt.toFixed(2)}` },
                        { label: 'Net UC Received', value: `${netUc.toFixed(4)} Club UC` },
                        { label: 'Destination', value: 'Club UC Wallet' }
                    ],
                    confirmText: 'Confirm & Convert',
                    onConfirm: () => executeConversion('bdt_to_club_uc', amount)
                });
            }
        }

        async function executeConversion(action, amount) {
            const btn = document.getElementById('btn-convert-action');
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<span>Processing Conversion...</span>';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('action', action);
            fd.append('amount', amount);

            try {
                const res = await fetch('/trade/api/convert.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    showToast('Conversion successfully settled!', '✅');
                    openCustomModal({
                        icon: '✅',
                        iconType: 'success',
                        title: 'Conversion Complete',
                        desc: data.message || 'Your conversion has settled instantly.',
                        details: [
                            { label: 'Status', value: 'Settled (Instant)' }
                        ],
                        confirmText: 'Done',
                        isAlert: true
                    });
                    refreshBalances();
                    document.getElementById('from-amount-input').value = '';
                    handleFromInput();
                } else {
                    showCustomAlert('Conversion Failed', data.error || 'The system could not process your conversion.', 'error', '❌');
                }
            } catch (err) {
                showCustomAlert('Network Error', err.message || 'Could not connect to conversion server.', 'error', '❌');
            } finally {
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
        }

        // Transfer Logic
        function toggleTransferDirection() {
            transferDirection = (transferDirection === 'main_to_trading') ? 'trading_to_main' : 'main_to_trading';
            const fromLabel = document.getElementById('trans-from-label');
            const toLabel = document.getElementById('trans-to-label');
            const fromAvail = document.getElementById('trans-from-avail');
            const toAvail = document.getElementById('trans-to-avail');

            if (transferDirection === 'main_to_trading') {
                fromLabel.textContent = 'Main BDT Wallet';
                toLabel.textContent = 'Trading Margin Wallet';
                fromAvail.textContent = userMainBdt.toFixed(2);
                toAvail.textContent = userTradingBdt.toFixed(2);
            } else {
                fromLabel.textContent = 'Trading Margin Wallet';
                toLabel.textContent = 'Main BDT Wallet';
                fromAvail.textContent = userAvailMargin.toFixed(2);
                toAvail.textContent = userMainBdt.toFixed(2);
            }
        }

        function applyMaxTransfer() {
            const input = document.getElementById('transfer-amount-input');
            input.value = (transferDirection === 'main_to_trading') ? userMainBdt : userAvailMargin;
        }

        function promptTransferConfirm() {
            const amount = parseFloat(document.getElementById('transfer-amount-input').value) || 0;
            if (amount <= 0) {
                showCustomAlert('Invalid Amount', 'Please enter a valid transfer amount.', 'warning', '⚠️');
                return;
            }

            const maxAvail = (transferDirection === 'main_to_trading') ? userMainBdt : userAvailMargin;
            if (amount > maxAvail) {
                showCustomAlert(
                    'Insufficient Funds', 
                    `You only have ৳ ${maxAvail.toFixed(2)} available to transfer.`, 
                    'error', 
                    '❌'
                );
                return;
            }

            const srcName = (transferDirection === 'main_to_trading') ? 'Main BDT Balance' : 'Trading Margin Wallet';
            const dstName = (transferDirection === 'main_to_trading') ? 'Trading Margin Wallet' : 'Main BDT Balance';

            openCustomModal({
                icon: '⇄',
                iconType: 'confirm',
                title: 'Confirm Margin Transfer',
                desc: `Transfer ৳ ${amount.toFixed(2)} from ${srcName} to ${dstName}.`,
                details: [
                    { label: 'Amount', value: `৳ ${amount.toFixed(2)}` },
                    { label: 'Fee', value: '0.00% (Free)' },
                    { label: 'Execution', value: 'Instant' }
                ],
                confirmText: 'Confirm Transfer',
                onConfirm: () => executeTransfer(amount)
            });
        }

        async function executeTransfer(amount) {
            const btn = document.getElementById('btn-transfer-action');
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<span>Processing Transfer...</span>';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('action', transferDirection);
            fd.append('amount', amount);

            try {
                const res = await fetch('/trade/api/convert.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    showToast('Margin transfer successful!', '✅');
                    openCustomModal({
                        icon: '✅',
                        iconType: 'success',
                        title: 'Transfer Complete',
                        desc: data.message || 'Funds transferred successfully.',
                        confirmText: 'Done',
                        isAlert: true
                    });
                    refreshBalances();
                    document.getElementById('transfer-amount-input').value = '';
                } else {
                    showCustomAlert('Transfer Failed', data.error || 'Could not complete transfer.', 'error', '❌');
                }
            } catch (err) {
                showCustomAlert('Network Error', err.message, 'error', '❌');
            } finally {
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
        }

        async function refreshBalances() {
            try {
                const res = await fetch('/trade/api/convert.php?action=get_balances');
                const data = await res.json();
                if (data.success) {
                    userClubUc = parseFloat(data.club_wallet.club_uc);
                    userMainBdt = parseFloat(data.main_wallet.bdt_balance);
                    userTradingBdt = parseFloat(data.trading_wallet.bdt_balance);
                    userAvailMargin = parseFloat(data.trading_wallet.available_margin);

                    document.getElementById('bal-club-uc').textContent = userClubUc.toFixed(2) + ' UC';
                    document.getElementById('bal-main-bdt').textContent = '৳ ' + userMainBdt.toFixed(2);
                    document.getElementById('bal-trading-bdt').textContent = '৳ ' + userTradingBdt.toFixed(2);

                    updateConvertUI();
                    toggleTransferDirection(); // refresh transfer limits
                    toggleTransferDirection();
                }
            } catch (e) {
                console.error('Failed to refresh balances', e);
            }
        }

        // Initialize UI
        updateConvertUI();
    </script>
</body>
</html>
