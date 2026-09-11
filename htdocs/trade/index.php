<?php
/**
 * UNMOOR CLUB - PROFESSIONAL TRADING TERMINAL
 * Pair: UC/BDT (Trading UC denominated in BDT)
 * TradingView-grade financial charting, 1x-100x Leverage Futures,
 * Live Orderbook, Dynamic Mark PnL, Liquidation Engine, Custom UI Confirmation.
 */

session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../core/components.php";
require_once __DIR__ . "/engine/fee_engine.php";
require_once __DIR__ . "/engine/wallet_engine.php";
require_once __DIR__ . "/engine/market_engine.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$balances = WalletEngine::getComprehensiveBalances($db, $uid);
$marketState = MarketEngine::heartbeat($db);
$settings = FeeEngine::getSettings($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>UC/BDT Pro Trading Terminal — Unmoor Club</title>
    <!-- Lightweight Charts for TradingView grade financial charting -->
    <script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>
    <style>
        :root {
            --tv-bg: #131722;
            --tv-surface: #1e222d;
            --tv-surface-elevated: #262b3e;
            --tv-border: rgba(255, 255, 255, 0.08);
            --tv-border-hover: rgba(59, 130, 246, 0.4);
            --tv-up: #089981;
            --tv-up-soft: rgba(8, 153, 129, 0.15);
            --tv-down: #f23645;
            --tv-down-soft: rgba(242, 54, 69, 0.15);
            --tv-blue: #2962ff;
            --tv-gold: #f59e0b;
            --tv-text: #d1d4dc;
            --tv-text-dim: #787b86;
            --tv-text-white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--tv-bg);
            color: var(--tv-text);
            font-family: -apple-system, BlinkMacSystemFont, "Trebuchet MS", Roboto, Ubuntu, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Top TradingView Header Bar */
        .tv-header {
            background: var(--tv-surface);
            border-bottom: 1px solid var(--tv-border);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .tv-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .tv-back-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--tv-border);
            color: var(--tv-text-white);
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .tv-back-btn:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .pair-badge {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pair-name {
            font-size: 15px;
            font-weight: 800;
            color: var(--tv-text-white);
            letter-spacing: 0.3px;
        }
        .pair-tag {
            background: rgba(41, 98, 255, 0.18);
            border: 1px solid rgba(41, 98, 255, 0.4);
            color: #60a5fa;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        /* Live Price Display */
        .live-price-box {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }
        .live-price-val {
            font-size: 20px;
            font-weight: 800;
            font-family: monospace;
            color: var(--tv-up);
            transition: color 0.3s ease;
        }
        .live-price-val.down {
            color: var(--tv-down);
        }
        .live-change-badge {
            font-size: 11.5px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 4px;
            background: var(--tv-up-soft);
            color: var(--tv-up);
        }
        .live-change-badge.negative {
            background: var(--tv-down-soft);
            color: var(--tv-down);
        }

        /* Stats Tape (Scrollable horizontally on mobile) */
        .stats-tape {
            display: flex;
            align-items: center;
            gap: 16px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 2px 0;
        }
        .stats-tape::-webkit-scrollbar {
            display: none;
        }
        .stat-item {
            display: flex;
            flex-direction: column;
            white-space: nowrap;
        }
        .stat-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--tv-text-dim);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .stat-val {
            font-size: 12px;
            font-weight: 700;
            color: var(--tv-text-white);
            font-family: monospace;
            margin-top: 1px;
        }

        .tv-header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .equity-chip {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--tv-border);
            padding: 5px 10px;
            border-radius: 8px;
            text-align: right;
            white-space: nowrap;
        }
        .equity-chip-label {
            font-size: 9.5px;
            color: var(--tv-text-dim);
            text-transform: uppercase;
            font-weight: 700;
        }
        .equity-chip-val {
            font-size: 12.5px;
            font-weight: 800;
            color: #34d399;
            font-family: monospace;
        }
        .btn-wallet-link {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            transition: all 0.15s;
        }
        .btn-wallet-link:hover {
            background: rgba(59, 130, 246, 0.25);
        }

        /* Terminal Grid Layout */
        .terminal-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            padding: 10px;
            max-width: 1750px;
            margin: 0 auto;
            width: 100%;
            flex: 1;
        }
        @media (min-width: 1100px) {
            .terminal-layout {
                grid-template-columns: 1fr 360px;
            }
        }
        @media (min-width: 1440px) {
            .terminal-layout {
                grid-template-columns: 1fr 340px 380px;
            }
        }

        /* Generic TradingView Panel Container */
        .tv-card {
            background: var(--tv-surface);
            border: 1px solid var(--tv-border);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Chart Header & Controls */
        .chart-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--tv-border);
            background: var(--tv-surface);
            flex-wrap: wrap;
            gap: 8px;
        }
        .timeframe-group {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .tf-btn {
            background: transparent;
            border: 1px solid transparent;
            color: var(--tv-text-dim);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: monospace;
            transition: all 0.15s;
        }
        .tf-btn:hover {
            color: var(--tv-text-white);
            background: rgba(255, 255, 255, 0.04);
        }
        .tf-btn.active {
            color: var(--tv-text-white);
            background: var(--tv-blue);
            border-color: var(--tv-blue);
        }

        .ohlc-legend {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-family: monospace;
            color: var(--tv-text-dim);
            flex-wrap: wrap;
        }
        .ohlc-legend span strong {
            color: var(--tv-text-white);
        }

        .chart-canvas-box {
            position: relative;
            width: 100%;
            height: 380px;
            min-height: 340px;
            background: var(--tv-bg);
        }
        @media (min-width: 768px) {
            .chart-canvas-box {
                height: 480px;
            }
        }

        /* Orderbook & Recent Trades */
        .book-trades-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        @media (max-width: 640px) {
            .book-trades-container {
                grid-template-columns: 1fr;
            }
        }

        .panel-header-title {
            font-size: 12px;
            font-weight: 800;
            color: var(--tv-text-white);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--tv-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .depth-table-header {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            padding: 6px 12px;
            font-size: 10px;
            font-weight: 700;
            color: var(--tv-text-dim);
            text-transform: uppercase;
        }
        .depth-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            padding: 3px 12px;
            font-size: 11px;
            font-family: monospace;
            position: relative;
        }
        .depth-row.ask { color: var(--tv-down); }
        .depth-row.bid { color: var(--tv-up); }
        .depth-bar-ask {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            background: rgba(242, 54, 69, 0.12);
            pointer-events: none;
            z-index: 0;
        }
        .depth-bar-bid {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            background: rgba(8, 153, 129, 0.12);
            pointer-events: none;
            z-index: 0;
        }
        .mid-price-strip {
            padding: 6px 12px;
            margin: 4px 8px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: monospace;
            font-size: 12px;
            font-weight: 800;
            color: var(--tv-up);
        }

        /* Order Entry Form */
        .order-form-card {
            padding: 14px;
        }
        .order-mode-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: rgba(0, 0, 0, 0.3);
            padding: 3px;
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid var(--tv-border);
        }
        .order-mode-btn {
            background: transparent;
            border: none;
            color: var(--tv-text-dim);
            padding: 8px 6px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
        }
        .order-mode-btn.active {
            background: var(--tv-blue);
            color: var(--tv-text-white);
        }

        /* Long / Short Switch */
        .side-toggle-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        .side-toggle-btn {
            border: 1px solid transparent;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-side-long {
            background: rgba(8, 153, 129, 0.15);
            border-color: rgba(8, 153, 129, 0.3);
            color: var(--tv-up);
        }
        .btn-side-long.active {
            background: var(--tv-up);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(8, 153, 129, 0.35);
        }
        .btn-side-short {
            background: rgba(242, 54, 69, 0.15);
            border-color: rgba(242, 54, 69, 0.3);
            color: var(--tv-down);
        }
        .btn-side-short.active {
            background: var(--tv-down);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(242, 54, 69, 0.35);
        }

        /* Leverage Selector */
        .leverage-box {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--tv-border);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .leverage-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .leverage-badge {
            background: rgba(41, 98, 255, 0.2);
            color: #60a5fa;
            font-family: monospace;
            font-weight: 800;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid rgba(41, 98, 255, 0.35);
        }
        .lev-slider {
            width: 100%;
            height: 4px;
            background: #334155;
            border-radius: 4px;
            accent-color: var(--tv-blue);
            cursor: pointer;
        }
        .lev-pills-row {
            display: flex;
            justify-content: space-between;
            gap: 4px;
            margin-top: 8px;
        }
        .lev-pill-btn {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--tv-border);
            color: var(--tv-text-dim);
            font-size: 10.5px;
            font-weight: 700;
            font-family: monospace;
            padding: 4px 0;
            border-radius: 5px;
            cursor: pointer;
        }
        .lev-pill-btn.active {
            background: rgba(41, 98, 255, 0.25);
            border-color: #60a5fa;
            color: #ffffff;
        }

        /* Margin Input Form */
        .field-group {
            margin-bottom: 12px;
        }
        .field-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            color: var(--tv-text-dim);
            margin-bottom: 4px;
        }
        .field-input-box {
            position: relative;
            display: flex;
            align-items: center;
            background: #0d111d;
            border: 1px solid var(--tv-border);
            border-radius: 8px;
            padding: 8px 12px;
        }
        .field-input-box:focus-within {
            border-color: var(--tv-blue);
        }
        .field-input {
            width: 100%;
            background: transparent;
            border: none;
            color: #ffffff;
            font-family: monospace;
            font-size: 14px;
            font-weight: 700;
            outline: none;
        }
        .field-suffix {
            font-size: 11px;
            font-weight: 700;
            color: var(--tv-text-dim);
            margin-left: 8px;
        }

        .pct-buttons-row {
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }
        .pct-btn {
            flex: 1;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--tv-border);
            color: var(--tv-text-dim);
            font-size: 10px;
            font-weight: 700;
            font-family: monospace;
            padding: 4px 0;
            border-radius: 4px;
            cursor: pointer;
        }
        .pct-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        /* Order Metrics Summary Box */
        .order-summary-box {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--tv-border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 11px;
            font-family: monospace;
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 12px;
        }
        .order-summary-row {
            display: flex;
            justify-content: space-between;
            color: var(--tv-text-dim);
        }
        .order-summary-row strong {
            color: var(--tv-text-white);
        }

        /* Big Submit CTA */
        .btn-order-submit {
            width: 100%;
            border: none;
            padding: 13px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #ffffff;
        }
        .btn-order-submit.long {
            background: var(--tv-up);
            box-shadow: 0 4px 18px rgba(8, 153, 129, 0.35);
        }
        .btn-order-submit.short {
            background: var(--tv-down);
            box-shadow: 0 4px 18px rgba(242, 54, 69, 0.35);
        }

        /* Bottom Positions & History Section */
        .bottom-section {
            padding: 0 10px 40px;
            max-width: 1750px;
            margin: 0 auto;
            width: 100%;
        }
        .bottom-tabs {
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--tv-border);
            background: var(--tv-surface);
            padding: 0 8px;
            overflow-x: auto;
        }
        .b-tab-btn {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--tv-text-dim);
            padding: 12px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .b-tab-btn.active {
            color: var(--tv-text-white);
            border-bottom-color: var(--tv-blue);
        }
        .b-tab-badge {
            background: rgba(255, 255, 255, 0.08);
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 10px;
        }

        /* Positions Table & Mobile Card View */
        .pos-table-wrap {
            overflow-x: auto;
            padding: 12px;
        }
        .tv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            font-family: monospace;
            text-align: left;
        }
        .tv-table th {
            color: var(--tv-text-dim);
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--tv-border);
        }
        .tv-table td {
            padding: 10px 6px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            color: var(--tv-text);
        }

        .btn-table-close {
            background: rgba(242, 54, 69, 0.15);
            border: 1px solid rgba(242, 54, 69, 0.3);
            color: var(--tv-down);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-table-close:hover {
            background: var(--tv-down);
            color: #ffffff;
        }

        /* CUSTOM MODAL POPUPS (NO BROWSER ALERTS) */
        .tv-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .tv-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .tv-modal-box {
            background: var(--tv-surface);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            width: 100%;
            max-width: 420px;
            padding: 22px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8);
            text-align: center;
        }
        .tv-modal-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }
        .tv-icon-confirm {
            background: rgba(41, 98, 255, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(41, 98, 255, 0.3);
        }
        .tv-icon-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .tv-icon-error {
            background: rgba(242, 54, 69, 0.15);
            color: #f87171;
            border: 1px solid rgba(242, 54, 69, 0.3);
        }
        .tv-icon-success {
            background: rgba(8, 153, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(8, 153, 129, 0.3);
        }
        .tv-modal-title {
            font-size: 17px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
        }
        .tv-modal-desc {
            font-size: 13px;
            color: var(--tv-text-dim);
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .tv-modal-details {
            background: #111520;
            border: 1px solid var(--tv-border);
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            font-family: monospace;
            margin-bottom: 18px;
            text-align: left;
        }
        .tv-modal-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            color: var(--tv-text-dim);
        }
        .tv-modal-row strong {
            color: #ffffff;
        }
        .tv-modal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .tv-btn-modal-cancel {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--tv-border);
            color: #ffffff;
            font-weight: 700;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
        }
        .tv-btn-modal-confirm {
            background: var(--tv-blue);
            border: 1px solid #3b82f6;
            color: #ffffff;
            font-weight: 800;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
        }
        .tv-btn-modal-single {
            grid-column: span 2;
        }

        /* TOAST FLOATING BUBBLES */
        .tv-toast-root {
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
        .tv-toast {
            background: #1e2436;
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 9px 16px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            gap: 8px;
            pointer-events: auto;
            animation: tvToastIn 0.2s ease-out;
        }
        @keyframes tvToastIn {
            from { transform: translateY(15px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
    <header class="tv-header">
        <div class="tv-header-left">
            <a href="/dashboard.php" class="tv-back-btn">
                <span>←</span>
                <span>Dashboard</span>
            </a>
            
            <div class="pair-badge">
                <span class="pair-name">UC / BDT</span>
                <span class="pair-tag">Perpetual</span>
            </div>

            <div class="live-price-box">
                <span class="live-price-val" id="header-mark-price">৳ <?= number_format((float)($marketState['price'] ?? 2.0), 4) ?></span>
                <span class="live-change-badge" id="header-24h-chg">+0.00%</span>
            </div>

            <!-- Horizontal Stats Strip -->
            <div class="stats-tape">
                <div class="stat-item">
                    <span class="stat-label">24h High</span>
                    <span class="stat-val" id="stat-24h-high">৳ <?= number_format((float)($marketState['high_24h'] ?? 50.0), 4) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">24h Low</span>
                    <span class="stat-val" id="stat-24h-low">৳ <?= number_format((float)($marketState['low_24h'] ?? 1.96), 4) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">24h Vol (UC)</span>
                    <span class="stat-val" id="stat-24h-vol"><?= number_format((float)($marketState['volume_24h'] ?? 5000), 2) ?></span>
                </div>
            </div>
        </div>

        <div class="tv-header-right">
            <div class="equity-chip">
                <div class="equity-chip-label">Trading Equity</div>
                <div class="equity-chip-val" id="header-equity-val">৳ <?= number_format((float)$balances['trading_wallet']['trading_equity'], 2) ?></div>
            </div>
            <a href="/convert.php" class="btn-wallet-link">
                <span>💱 Convert / Wallets</span>
            </a>
        </div>
    </header>

    <!-- MAIN TERMINAL LAYOUT -->
    <div class="terminal-layout">

        <!-- SECTION 1: TRADINGVIEW CHART & DATA FEEDS -->
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <!-- Candlestick Chart Card -->
            <div class="tv-card">
                <!-- Toolbar -->
                <div class="chart-toolbar">
                    <div class="timeframe-group">
                        <span style="font-size: 11px; font-weight: 700; color: var(--tv-text-dim); margin-right: 4px;">Time:</span>
                        <?php foreach (['1m', '5m', '15m', '1h', '4h', '1d'] as $tf): ?>
                            <button class="tf-btn <?= $tf === '1m' ? 'active' : '' ?>" data-tf="<?= $tf ?>" onclick="changeTimeframe('<?= $tf ?>')">
                                <?= $tf ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="ohlc-legend">
                        <span>O: <strong id="legend-open">-</strong></span>
                        <span>H: <strong id="legend-high">-</strong></span>
                        <span>L: <strong id="legend-low">-</strong></span>
                        <span>C: <strong id="legend-close">-</strong></span>
                    </div>
                </div>

                <!-- Canvas -->
                <div class="chart-canvas-box" id="chart-canvas-root"></div>
            </div>

            <!-- Orderbook and Trades Container -->
            <div class="book-trades-container">
                <!-- Order Book -->
                <div class="tv-card">
                    <div class="panel-header-title">
                        <span>Order Book (UC/BDT)</span>
                        <span style="font-size: 10px; color: var(--tv-text-dim);">Spread: <span id="disp-spread" style="font-family: monospace;">0.0050</span></span>
                    </div>
                    <div class="depth-table-header">
                        <span>Price (BDT)</span>
                        <span style="text-align: right;">Size (UC)</span>
                        <span style="text-align: right;">Total</span>
                    </div>
                    <!-- Asks (Red) -->
                    <div id="ob-asks-list" style="display: flex; flex-direction: column-reverse; gap: 1px;"></div>
                    <!-- Mid Price -->
                    <div class="mid-price-strip">
                        <span id="ob-mark-price">৳ <?= number_format((float)($marketState['price'] ?? 2.0), 4) ?></span>
                        <span style="font-size: 10px; color: var(--tv-text-dim);">Mark Price</span>
                    </div>
                    <!-- Bids (Green) -->
                    <div id="ob-bids-list" style="display: flex; flex-direction: column; gap: 1px;"></div>
                </div>

                <!-- Recent Trades -->
                <div class="tv-card">
                    <div class="panel-header-title">
                        <span>Recent Market Trades</span>
                        <span style="font-size: 10px; color: #34d399;">● Live</span>
                    </div>
                    <div class="depth-table-header">
                        <span>Price (BDT)</span>
                        <span style="text-align: right;">Size (UC)</span>
                        <span style="text-align: right;">Time</span>
                    </div>
                    <div id="recent-trades-box" style="display: flex; flex-direction: column; max-height: 220px; overflow-y: auto; font-family: monospace; font-size: 11px;"></div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: ORDER EXECUTION TERMINAL -->
        <div class="tv-card order-form-card">
            <!-- Mode Switch: Futures vs Spot -->
            <div class="order-mode-tabs">
                <button type="button" class="order-mode-btn active" id="btn-mode-futures" onclick="switchTradeMode('futures')">
                    ⚡ Futures (1x-100x)
                </button>
                <button type="button" class="order-mode-btn" id="btn-mode-spot" onclick="switchTradeMode('spot')">
                    🎯 Spot Market
                </button>
            </div>

            <!-- FUTURES FORM -->
            <div id="form-futures-pane">
                <!-- Long / Short Switch -->
                <div class="side-toggle-grid">
                    <button type="button" class="side-toggle-btn btn-side-long active" id="btn-side-long" onclick="setFuturesSide('long')">
                        <span>▲</span>
                        <span>LONG / BUY</span>
                    </button>
                    <button type="button" class="side-toggle-btn btn-side-short" id="btn-side-short" onclick="setFuturesSide('short')">
                        <span>▼</span>
                        <span>SHORT / SELL</span>
                    </button>
                </div>

                <!-- Leverage Selector -->
                <div class="leverage-box">
                    <div class="leverage-header">
                        <span style="font-size: 11px; font-weight: 700; color: var(--tv-text-dim);">Leverage:</span>
                        <span class="leverage-badge" id="disp-leverage-mult">10x</span>
                    </div>
                    <input type="range" min="1" max="100" value="10" step="1" id="input-leverage-slider" class="lev-slider" oninput="handleLeverageChange(this.value)">
                    <div class="lev-pills-row">
                        <?php foreach ([1, 5, 10, 25, 50, 100] as $lv): ?>
                            <button type="button" class="lev-pill-btn <?= $lv === 10 ? 'active' : '' ?>" data-lev="<?= $lv ?>" onclick="setQuickLeverage(<?= $lv ?>)">
                                <?= $lv ?>x
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Margin Input Field -->
                <div class="field-group">
                    <div class="field-header">
                        <span>Order Margin</span>
                        <span>Avail: <strong style="color: #34d399;" id="form-avail-margin">৳ <?= number_format((float)$balances['trading_wallet']['available_margin'], 2) ?></strong></span>
                    </div>
                    <div class="field-input-box">
                        <input type="number" step="any" min="10" id="input-futures-margin" class="field-input" placeholder="Min ৳ 10.00" oninput="calculateFuturesMetrics()">
                        <span class="field-suffix">BDT</span>
                    </div>
                    <div class="pct-buttons-row">
                        <button type="button" class="pct-btn" onclick="applyMarginPercent(0.25)">25%</button>
                        <button type="button" class="pct-btn" onclick="applyMarginPercent(0.50)">50%</button>
                        <button type="button" class="pct-btn" onclick="applyMarginPercent(0.75)">75%</button>
                        <button type="button" class="pct-btn" onclick="applyMarginPercent(1.00)">100%</button>
                    </div>
                </div>

                <!-- Calculations Summary -->
                <div class="order-summary-box">
                    <div class="order-summary-row">
                        <span>Entry Mark Price:</span>
                        <strong id="calc-entry-price">৳ <?= number_format((float)($marketState['price'] ?? 2.0), 4) ?></strong>
                    </div>
                    <div class="order-summary-row">
                        <span>Position Size:</span>
                        <strong id="calc-pos-size">0.0000 UC</strong>
                    </div>
                    <div class="order-summary-row">
                        <span>Est. Liquidation:</span>
                        <strong style="color: #f87171;" id="calc-liq-price">৳ 0.0000</strong>
                    </div>
                    <div class="order-summary-row">
                        <span>Trading Fee (0.10%):</span>
                        <strong style="color: #fbbf24;" id="calc-fee-amount">৳ 0.00</strong>
                    </div>
                </div>

                <!-- Submit CTA Button -->
                <button type="button" class="btn-order-submit long" id="btn-submit-futures" onclick="promptFuturesOrder()">
                    <span>Open 10x Long Position</span>
                </button>
            </div>

            <!-- SPOT FORM -->
            <div id="form-spot-pane" style="display: none;">
                <div class="side-toggle-grid">
                    <button type="button" class="side-toggle-btn btn-side-long active" id="btn-spot-buy" onclick="setSpotSide('buy')">
                        BUY UC
                    </button>
                    <button type="button" class="side-toggle-btn btn-side-short" id="btn-spot-sell" onclick="setSpotSide('sell')">
                        SELL UC
                    </button>
                </div>

                <div class="field-group">
                    <div class="field-header">
                        <span>Amount (Trading UC)</span>
                        <span id="spot-hint">Min 1.0 UC</span>
                    </div>
                    <div class="field-input-box">
                        <input type="number" step="any" min="1" id="input-spot-amount" class="field-input" placeholder="0.0 UC">
                        <span class="field-suffix">tUC</span>
                    </div>
                </div>

                <button type="button" class="btn-order-submit long" id="btn-submit-spot" onclick="promptSpotOrder()">
                    <span>Buy Trading UC</span>
                </button>
            </div>

            <!-- Quick Wallet Summary -->
            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px dashed var(--tv-border); font-size: 11px; font-family: monospace; color: var(--tv-text-dim);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Margin Balance:</span>
                    <strong style="color: #ffffff;" id="disp-margin-bal">৳ <?= number_format((float)$balances['trading_wallet']['bdt_balance'], 2) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Locked in Positions:</span>
                    <strong style="color: #fbbf24;" id="disp-locked-margin">৳ <?= number_format((float)$balances['trading_wallet']['locked_margin'], 2) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Spot Trading UC:</span>
                    <strong style="color: #a78bfa;" id="disp-tuc-bal"><?= number_format((float)$balances['trading_wallet']['tuc_balance'], 4) ?> UC</strong>
                </div>
            </div>
        </div>

    </div>

    <!-- BOTTOM DOCK: POSITIONS & ORDERS -->
    <section class="bottom-section">
        <div class="tv-card">
            <div class="bottom-tabs">
                <button type="button" class="b-tab-btn active" id="b-tab-pos" onclick="switchBottomTab('positions')">
                    <span>Open Positions</span>
                    <span class="b-tab-badge" id="count-open-pos">0</span>
                </button>
                <button type="button" class="b-tab-btn" id="b-tab-orders" onclick="switchBottomTab('orders')">
                    <span>Open Orders</span>
                    <span class="b-tab-badge" id="count-open-orders">0</span>
                </button>
                <button type="button" class="b-tab-btn" id="b-tab-history" onclick="switchBottomTab('history')">
                    <span>Trade History</span>
                </button>
            </div>

            <!-- Tab 1: Positions -->
            <div id="pane-tab-positions" class="pos-table-wrap">
                <table class="tv-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Contract / Side</th>
                            <th>Size</th>
                            <th>Entry</th>
                            <th>Mark</th>
                            <th>Liq. Price</th>
                            <th>Margin</th>
                            <th>PnL (ROI)</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="open-positions-tbody">
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 24px 0; color: var(--tv-text-dim); font-family: sans-serif;">
                                No open positions currently. Open a 1x-100x leveraged position above.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tab 2: Orders -->
            <div id="pane-tab-orders" class="pos-table-wrap" style="display: none;">
                <table class="tv-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Type</th>
                            <th>Side</th>
                            <th>Limit Price</th>
                            <th>Amount</th>
                            <th>Locked Margin</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="open-orders-tbody">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 24px 0; color: var(--tv-text-dim); font-family: sans-serif;">
                                No open limit orders.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tab 3: History -->
            <div id="pane-tab-history" class="pos-table-wrap" style="display: none;">
                <table class="tv-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Side</th>
                            <th>Entry Price</th>
                            <th>Exit Price</th>
                            <th>Margin</th>
                            <th>Realized PnL</th>
                            <th>Reason</th>
                            <th>Closed At</th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 24px 0; color: var(--tv-text-dim); font-family: sans-serif;">
                                No past positions found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- CUSTOM CONFIRMATION & ALERT MODAL (NO GOOGLE BROWSER POPUPS) -->
    <div class="tv-modal-overlay" id="tv-modal-overlay">
        <div class="tv-modal-box">
            <div class="tv-modal-icon tv-icon-confirm" id="tv-modal-icon-badge">
                <span id="tv-modal-icon-glyph">⚡</span>
            </div>
            <h3 class="tv-modal-title" id="tv-modal-title">Confirm Order</h3>
            <p class="tv-modal-desc" id="tv-modal-desc">Please review order parameters before execution.</p>

            <div class="tv-modal-details" id="tv-modal-details" style="display: none;">
                <!-- dynamic details injected here -->
            </div>

            <div class="tv-modal-actions" id="tv-modal-actions">
                <button type="button" class="tv-btn-modal-cancel" id="tv-modal-cancel-btn" onclick="closeTvModal()">Cancel</button>
                <button type="button" class="tv-btn-modal-confirm" id="tv-modal-confirm-btn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- TOAST ROOT -->
    <div class="tv-toast-root" id="tv-toast-root"></div>

    <script>
        // Global Terminal State
        let currentPrice = <?= (float)($marketState['price'] ?? 2.0000) ?>;
        let selectedSide = 'long';
        let currentLeverage = 10;
        let activeTimeframe = '1m';
        let tradeMode = 'futures';
        let spotSide = 'buy';
        let userAvailMargin = <?= (float)($balances['trading_wallet']['available_margin'] ?? 0) ?>;

        let chart = null;
        let candleSeries = null;

        // Custom Toast Helper
        function showToast(msg, icon = 'ℹ️') {
            const root = document.getElementById('tv-toast-root');
            const toast = document.createElement('div');
            toast.className = 'tv-toast';
            toast.innerHTML = `<span>${icon}</span><span>${msg}</span>`;
            root.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'all 0.25s ease';
                setTimeout(() => toast.remove(), 250);
            }, 3000);
        }

        // Custom Modal System
        function openTvModal({ icon = '⚡', iconType = 'confirm', title, desc, details = [], confirmText = 'Confirm', onConfirm, isAlert = false }) {
            const overlay = document.getElementById('tv-modal-overlay');
            const iconBadge = document.getElementById('tv-modal-icon-badge');
            const iconGlyph = document.getElementById('tv-modal-icon-glyph');
            const modalTitle = document.getElementById('tv-modal-title');
            const modalDesc = document.getElementById('tv-modal-desc');
            const detailsBox = document.getElementById('tv-modal-details');
            const confirmBtn = document.getElementById('tv-modal-confirm-btn');
            const cancelBtn = document.getElementById('tv-modal-cancel-btn');

            iconBadge.className = `tv-modal-icon tv-icon-${iconType}`;
            iconGlyph.textContent = icon;
            modalTitle.textContent = title;
            modalDesc.textContent = desc;

            if (details && details.length > 0) {
                detailsBox.style.display = 'block';
                detailsBox.innerHTML = details.map(d => `
                    <div class="tv-modal-row">
                        <span>${d.label}</span>
                        <strong>${d.value}</strong>
                    </div>
                `).join('');
            } else {
                detailsBox.style.display = 'none';
            }

            if (isAlert) {
                cancelBtn.style.display = 'none';
                confirmBtn.className = 'tv-btn-modal-confirm tv-btn-modal-single';
                confirmBtn.textContent = confirmText || 'Got it';
                confirmBtn.onclick = () => closeTvModal();
            } else {
                cancelBtn.style.display = 'block';
                confirmBtn.className = 'tv-btn-modal-confirm';
                confirmBtn.textContent = confirmText || 'Confirm';
                confirmBtn.onclick = () => {
                    closeTvModal();
                    if (typeof onConfirm === 'function') onConfirm();
                };
            }

            overlay.classList.add('active');
        }

        function closeTvModal() {
            document.getElementById('tv-modal-overlay').classList.remove('active');
        }

        function showTvAlert(title, message, iconType = 'warning', icon = '⚠️') {
            openTvModal({
                icon,
                iconType,
                title,
                desc: message,
                details: [],
                isAlert: true
            });
        }

        // Initialize TradingView Lightweight Chart
        function initChart() {
            const container = document.getElementById('chart-canvas-root');
            if (!container) return;

            chart = LightweightCharts.createChart(container, {
                width: container.clientWidth,
                height: container.clientHeight || 400,
                layout: {
                    background: { color: '#131722' },
                    textColor: '#787b86',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Trebuchet MS", Roboto, sans-serif',
                },
                grid: {
                    vertLines: { color: 'rgba(255, 255, 255, 0.04)' },
                    horzLines: { color: 'rgba(255, 255, 255, 0.04)' },
                },
                crosshair: {
                    mode: LightweightCharts.CrosshairMode.Normal,
                },
                rightPriceScale: {
                    borderColor: 'rgba(255, 255, 255, 0.08)',
                },
                timeScale: {
                    borderColor: 'rgba(255, 255, 255, 0.08)',
                    timeVisible: true,
                    secondsVisible: false,
                },
            });

            candleSeries = chart.addCandlestickSeries({
                upColor: '#089981',
                downColor: '#f23645',
                borderDownColor: '#f23645',
                borderUpColor: '#089981',
                wickDownColor: '#f23645',
                wickUpColor: '#089981',
            });

            // Crosshair move listener for OHLC legend
            chart.subscribeCrosshairMove(param => {
                if (!param || !param.seriesData || !param.seriesData.get(candleSeries)) {
                    return;
                }
                const data = param.seriesData.get(candleSeries);
                if (data) {
                    document.getElementById('legend-open').textContent = '৳ ' + data.open.toFixed(4);
                    document.getElementById('legend-high').textContent = '৳ ' + data.high.toFixed(4);
                    document.getElementById('legend-low').textContent = '৳ ' + data.low.toFixed(4);
                    document.getElementById('legend-close').textContent = '৳ ' + data.close.toFixed(4);
                }
            });

            // Auto Resize
            new ResizeObserver(entries => {
                if (entries.length && chart) {
                    chart.applyOptions({
                        width: entries[0].contentRect.width,
                        height: entries[0].contentRect.height
                    });
                }
            }).observe(container);

            loadChartData();
        }

        async function loadChartData() {
            try {
                const res = await fetch(`/trade/api/chart_data.php?resolution=${activeTimeframe}&limit=200`);
                const data = await res.json();
                if (data.success && data.candles && candleSeries) {
                    candleSeries.setData(data.candles);
                    if (data.candles.length > 0) {
                        const last = data.candles[data.candles.length - 1];
                        document.getElementById('legend-open').textContent = '৳ ' + last.open.toFixed(4);
                        document.getElementById('legend-high').textContent = '৳ ' + last.high.toFixed(4);
                        document.getElementById('legend-low').textContent = '৳ ' + last.low.toFixed(4);
                        document.getElementById('legend-close').textContent = '৳ ' + last.close.toFixed(4);
                    }
                }
            } catch (e) {
                console.error("Failed to load chart data:", e);
            }
        }

        function changeTimeframe(tf) {
            activeTimeframe = tf;
            document.querySelectorAll('.tf-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-tf') === tf);
            });
            loadChartData();
        }

        // Trade Mode Switch
        function switchTradeMode(mode) {
            tradeMode = mode;
            document.getElementById('btn-mode-futures').classList.toggle('active', mode === 'futures');
            document.getElementById('btn-mode-spot').classList.toggle('active', mode === 'spot');
            document.getElementById('form-futures-pane').style.display = mode === 'futures' ? 'block' : 'none';
            document.getElementById('form-spot-pane').style.display = mode === 'spot' ? 'block' : 'none';
        }

        // Futures Side & Leverage
        function setFuturesSide(side) {
            selectedSide = side;
            document.getElementById('btn-side-long').classList.toggle('active', side === 'long');
            document.getElementById('btn-side-short').classList.toggle('active', side === 'short');
            
            const submitBtn = document.getElementById('btn-submit-futures');
            if (side === 'long') {
                submitBtn.className = 'btn-order-submit long';
                submitBtn.innerHTML = `<span>Open ${currentLeverage}x Long Position</span>`;
            } else {
                submitBtn.className = 'btn-order-submit short';
                submitBtn.innerHTML = `<span>Open ${currentLeverage}x Short Position</span>`;
            }
            calculateFuturesMetrics();
        }

        function handleLeverageChange(val) {
            currentLeverage = parseInt(val);
            document.getElementById('disp-leverage-mult').textContent = currentLeverage + 'x';
            document.querySelectorAll('.lev-pill-btn').forEach(btn => {
                btn.classList.toggle('active', parseInt(btn.getAttribute('data-lev')) === currentLeverage);
            });
            const submitBtn = document.getElementById('btn-submit-futures');
            submitBtn.innerHTML = `<span>Open ${currentLeverage}x ${selectedSide === 'long' ? 'Long' : 'Short'} Position</span>`;
            calculateFuturesMetrics();
        }

        function setQuickLeverage(val) {
            document.getElementById('input-leverage-slider').value = val;
            handleLeverageChange(val);
        }

        function applyMarginPercent(pct) {
            const amt = Math.max(10, (userAvailMargin * pct)).toFixed(2);
            document.getElementById('input-futures-margin').value = amt;
            calculateFuturesMetrics();
        }

        function calculateFuturesMetrics() {
            const margin = parseFloat(document.getElementById('input-futures-margin').value) || 0;
            const entryPrice = currentPrice;

            document.getElementById('calc-entry-price').textContent = '৳ ' + entryPrice.toFixed(4);

            if (margin <= 0 || entryPrice <= 0) {
                document.getElementById('calc-pos-size').textContent = '0.0000 UC';
                document.getElementById('calc-liq-price').textContent = '৳ 0.0000';
                document.getElementById('calc-fee-amount').textContent = '৳ 0.00';
                return;
            }

            const notional = margin * currentLeverage;
            const posSize = notional / entryPrice;
            document.getElementById('calc-pos-size').textContent = posSize.toFixed(4) + ' UC';

            // Liquidation calculation (0.5% Maintenance Margin)
            const mmRate = 0.0050;
            let liqPrice = 0;
            if (selectedSide === 'long') {
                liqPrice = entryPrice * (1.0 - ((1.0 - mmRate) / currentLeverage));
                liqPrice = Math.max(0.01, liqPrice);
            } else {
                liqPrice = entryPrice * (1.0 + ((1.0 - mmRate) / currentLeverage));
            }
            document.getElementById('calc-liq-price').textContent = '৳ ' + liqPrice.toFixed(4);

            const totalFee = notional * 0.0010;
            document.getElementById('calc-fee-amount').textContent = '৳ ' + totalFee.toFixed(2);
        }

        // Spot Side
        function setSpotSide(side) {
            spotSide = side;
            document.getElementById('btn-spot-buy').classList.toggle('active', side === 'buy');
            document.getElementById('btn-spot-sell').classList.toggle('active', side === 'sell');
            const submitBtn = document.getElementById('btn-submit-spot');
            if (side === 'buy') {
                submitBtn.className = 'btn-order-submit long';
                submitBtn.innerHTML = '<span>Buy Trading UC</span>';
            } else {
                submitBtn.className = 'btn-order-submit short';
                submitBtn.innerHTML = '<span>Sell Trading UC</span>';
            }
        }

        // Futures Order Submission with Confirmation UI
        function promptFuturesOrder() {
            const margin = parseFloat(document.getElementById('input-futures-margin').value) || 0;
            if (margin < 10) {
                showTvAlert('Minimum Margin Required', 'The minimum margin required to open a leveraged position is ৳ 10.00 BDT.', 'warning', '⚠️');
                return;
            }

            if (margin > userAvailMargin) {
                openTvModal({
                    icon: '⚠️',
                    iconType: 'warning',
                    title: 'Insufficient Trading Margin',
                    desc: `You need ৳ ${margin.toFixed(2)} to open this position, but your available margin is only ৳ ${userAvailMargin.toFixed(2)}.`,
                    details: [
                        { label: 'Available Margin', value: `৳ ${userAvailMargin.toFixed(2)}` },
                        { label: 'Required Margin', value: `৳ ${margin.toFixed(2)}` },
                        { label: 'Shortfall', value: `৳ ${(margin - userAvailMargin).toFixed(2)}` }
                    ],
                    confirmText: 'Deposit / Transfer Margin',
                    onConfirm: () => { window.location.href = '/convert.php'; }
                });
                return;
            }

            const notional = margin * currentLeverage;
            const posSize = notional / currentPrice;
            const mmRate = 0.0050;
            let liqPrice = (selectedSide === 'long') 
                ? Math.max(0.01, currentPrice * (1.0 - ((1.0 - mmRate) / currentLeverage)))
                : currentPrice * (1.0 + ((1.0 - mmRate) / currentLeverage));

            openTvModal({
                icon: '⚡',
                iconType: 'confirm',
                title: `Confirm ${currentLeverage}x ${selectedSide.toUpperCase()} Order`,
                desc: 'Please verify position details. Market order executes instantly against liquidity pool.',
                details: [
                    { label: 'Contract', value: 'UC / BDT Perpetual' },
                    { label: 'Position Direction', value: `${currentLeverage}x ${selectedSide.toUpperCase()}` },
                    { label: 'Margin Allocated', value: `৳ ${margin.toFixed(2)}` },
                    { label: 'Position Size', value: `${posSize.toFixed(4)} UC` },
                    { label: 'Est. Entry Price', value: `৳ ${currentPrice.toFixed(4)}` },
                    { label: 'Est. Liquidation Price', value: `৳ ${liqPrice.toFixed(4)}` }
                ],
                confirmText: `Submit ${selectedSide.toUpperCase()} Order`,
                onConfirm: () => executeFuturesOrder(margin)
            });
        }

        async function executeFuturesOrder(margin) {
            const btn = document.getElementById('btn-submit-futures');
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<span>Executing Order...</span>';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('action', 'open_leverage');
            fd.append('side', selectedSide);
            fd.append('margin', margin);
            fd.append('leverage', currentLeverage);

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message || 'Position opened successfully!', '✅');
                    document.getElementById('input-futures-margin').value = '';
                    calculateFuturesMetrics();
                    fetchMarketAndPositions();
                } else {
                    showTvAlert('Order Execution Failed', data.error || 'Failed to open position.', 'error', '❌');
                }
            } catch (err) {
                showTvAlert('Network Error', err.message, 'error', '❌');
            } finally {
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
        }

        // Spot Order Submission
        function promptSpotOrder() {
            const amount = parseFloat(document.getElementById('input-spot-amount').value) || 0;
            if (amount < 1) {
                showTvAlert('Minimum Order Amount', 'Minimum spot order size is 1.0 Trading UC.', 'warning', '⚠️');
                return;
            }

            const estCost = amount * currentPrice;
            openTvModal({
                icon: '🎯',
                iconType: 'confirm',
                title: `Confirm Spot ${spotSide.toUpperCase()}`,
                desc: `Instant market execution for ${amount.toFixed(2)} UC.`,
                details: [
                    { label: 'Action', value: `SPOT ${spotSide.toUpperCase()}` },
                    { label: 'Amount', value: `${amount.toFixed(2)} UC` },
                    { label: 'Current Price', value: `৳ ${currentPrice.toFixed(4)}` },
                    { label: 'Estimated Total', value: `৳ ${estCost.toFixed(2)}` }
                ],
                confirmText: `Execute ${spotSide.toUpperCase()}`,
                onConfirm: () => executeSpotOrder(amount)
            });
        }

        async function executeSpotOrder(amount) {
            const fd = new FormData();
            fd.append('action', 'spot_order');
            fd.append('side', spotSide);
            fd.append('amount', amount);
            fd.append('order_type', 'market');

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Spot order executed!', '✅');
                    document.getElementById('input-spot-amount').value = '';
                    fetchMarketAndPositions();
                } else {
                    showTvAlert('Spot Execution Failed', data.error || 'Order could not be filled.', 'error', '❌');
                }
            } catch (e) {
                showTvAlert('Network Error', e.message, 'error', '❌');
            }
        }

        // Close Position with Custom Confirmation Modal
        function promptClosePosition(posId, side, size, pnl) {
            openTvModal({
                icon: '⚠️',
                iconType: 'warning',
                title: `Close Position #${posId}`,
                desc: 'Are you sure you want to market close this position now?',
                details: [
                    { label: 'Contract', value: `UC/BDT (${side.toUpperCase()})` },
                    { label: 'Position Size', value: `${parseFloat(size).toFixed(4)} UC` },
                    { label: 'Est. Realized PnL', value: `৳ ${parseFloat(pnl).toFixed(2)}` },
                    { label: 'Execution', value: 'Instant Market Settlement' }
                ],
                confirmText: 'Close Position',
                onConfirm: () => executeClosePosition(posId)
            });
        }

        async function executeClosePosition(posId) {
            const fd = new FormData();
            fd.append('action', 'close_position');
            fd.append('position_id', posId);

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Position closed successfully.', '✅');
                    fetchMarketAndPositions();
                } else {
                    showTvAlert('Close Position Error', data.error || 'Failed to close position.', 'error', '❌');
                }
            } catch (err) {
                showTvAlert('Network Error', err.message, 'error', '❌');
            }
        }

        // Cancel Order
        function promptCancelOrder(orderId) {
            openTvModal({
                icon: '❌',
                iconType: 'warning',
                title: `Cancel Order #${orderId}`,
                desc: 'Unlocked margin will be refunded immediately to your available trading balance.',
                confirmText: 'Cancel Order',
                onConfirm: () => executeCancelOrder(orderId)
            });
        }

        async function executeCancelOrder(orderId) {
            const fd = new FormData();
            fd.append('action', 'cancel_order');
            fd.append('order_id', orderId);

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast('Order cancelled.', 'ℹ️');
                    fetchMarketAndPositions();
                } else {
                    showTvAlert('Cancellation Error', data.error || 'Failed to cancel order.', 'error', '❌');
                }
            } catch (err) {
                showTvAlert('Network Error', err.message, 'error', '❌');
            }
        }

        // Bottom Tabs
        function switchBottomTab(tab) {
            document.getElementById('b-tab-pos').classList.toggle('active', tab === 'positions');
            document.getElementById('b-tab-orders').classList.toggle('active', tab === 'orders');
            document.getElementById('b-tab-history').classList.toggle('active', tab === 'history');

            document.getElementById('pane-tab-positions').style.display = tab === 'positions' ? 'block' : 'none';
            document.getElementById('pane-tab-orders').style.display = tab === 'orders' ? 'block' : 'none';
            document.getElementById('pane-tab-history').style.display = tab === 'history' ? 'block' : 'none';
        }

        // Market & Positions Poller
        async function fetchMarketAndPositions() {
            try {
                // 1. Market Data
                const mRes = await fetch('/trade/api/market_data.php');
                const mData = await mRes.json();
                if (mData.success && mData.market) {
                    const prevPrice = currentPrice;
                    currentPrice = parseFloat(mData.market.price);

                    const priceEl = document.getElementById('header-mark-price');
                    priceEl.textContent = '৳ ' + currentPrice.toFixed(4);
                    priceEl.className = 'live-price-val ' + (currentPrice >= prevPrice ? '' : 'down');

                    document.getElementById('ob-mark-price').textContent = '৳ ' + currentPrice.toFixed(4);
                    document.getElementById('stat-24h-high').textContent = '৳ ' + parseFloat(mData.market.high_24h).toFixed(4);
                    document.getElementById('stat-24h-low').textContent = '৳ ' + parseFloat(mData.market.low_24h).toFixed(4);
                    document.getElementById('stat-24h-vol').textContent = parseFloat(mData.market.volume_24h).toFixed(2);

                    const chg = parseFloat(mData.market.change_24h);
                    const chgEl = document.getElementById('header-24h-chg');
                    chgEl.textContent = (chg >= 0 ? '+' : '') + chg.toFixed(2) + '%';
                    chgEl.className = 'live-change-badge ' + (chg >= 0 ? '' : 'negative');

                    // Orderbook
                    if (mData.orderbook) {
                        const asksHtml = mData.orderbook.asks.map(a => `
                            <div class="depth-row ask">
                                <span>৳ ${a.price.toFixed(4)}</span>
                                <span style="text-align: right; color: #ffffff;">${a.amount.toFixed(2)}</span>
                                <span style="text-align: right; color: var(--tv-text-dim);">৳ ${a.total.toFixed(2)}</span>
                                <div class="depth-bar-ask" style="width: ${Math.min(100, a.amount / 3)}%"></div>
                            </div>
                        `).join('');
                        document.getElementById('ob-asks-list').innerHTML = asksHtml;

                        const bidsHtml = mData.orderbook.bids.map(b => `
                            <div class="depth-row bid">
                                <span>৳ ${b.price.toFixed(4)}</span>
                                <span style="text-align: right; color: #ffffff;">${b.amount.toFixed(2)}</span>
                                <span style="text-align: right; color: var(--tv-text-dim);">৳ ${b.total.toFixed(2)}</span>
                                <div class="depth-bar-bid" style="width: ${Math.min(100, b.amount / 3)}%"></div>
                            </div>
                        `).join('');
                        document.getElementById('ob-bids-list').innerHTML = bidsHtml;

                        const spread = (mData.orderbook.asks[0]?.price || currentPrice) - (mData.orderbook.bids[0]?.price || currentPrice);
                        document.getElementById('disp-spread').textContent = Math.abs(spread).toFixed(4);
                    }

                    // Recent Trades
                    if (mData.recent_trades) {
                        document.getElementById('recent-trades-box').innerHTML = mData.recent_trades.map(t => `
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; padding: 4px 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.03);">
                                <span style="color: ${t.side === 'buy' ? 'var(--tv-up)' : 'var(--tv-down)'}; font-weight: 700;">৳ ${t.price.toFixed(4)}</span>
                                <span style="text-align: right; color: #ffffff;">${t.amount.toFixed(2)}</span>
                                <span style="text-align: right; color: var(--tv-text-dim); font-size: 10px;">${t.time}</span>
                            </div>
                        `).join('');
                    }

                    calculateFuturesMetrics();
                }

                // 2. Positions & Wallets
                const pRes = await fetch('/trade/api/positions_api.php');
                const pData = await pRes.json();
                if (pData.success) {
                    if (pData.balances?.trading_wallet) {
                        const tw = pData.balances.trading_wallet;
                        userAvailMargin = parseFloat(tw.available_margin);
                        document.getElementById('form-avail-margin').textContent = '৳ ' + userAvailMargin.toFixed(2);
                        document.getElementById('disp-margin-bal').textContent = '৳ ' + parseFloat(tw.bdt_balance).toFixed(2);
                        document.getElementById('disp-locked-margin').textContent = '৳ ' + parseFloat(tw.locked_margin).toFixed(2);
                        document.getElementById('disp-tuc-bal').textContent = parseFloat(tw.tuc_balance).toFixed(4) + ' UC';
                        document.getElementById('header-equity-val').textContent = '৳ ' + parseFloat(tw.trading_equity).toFixed(2);
                    }

                    // Positions Table
                    const openPos = pData.open_positions || [];
                    document.getElementById('count-open-pos').textContent = openPos.length;

                    if (openPos.length === 0) {
                        document.getElementById('open-positions-tbody').innerHTML = `
                            <tr><td colspan="9" style="text-align: center; padding: 24px 0; color: var(--tv-text-dim); font-family: sans-serif;">No open positions currently. Open a 1x-100x leveraged position above.</td></tr>
                        `;
                    } else {
                        document.getElementById('open-positions-tbody').innerHTML = openPos.map(p => {
                            const isLong = p.side === 'long';
                            const pnlColor = p.unrealized_pnl >= 0 ? 'var(--tv-up)' : 'var(--tv-down)';
                            return `
                                <tr>
                                    <td style="color: var(--tv-text-dim);">#${p.id}</td>
                                    <td>
                                        <span style="padding: 2px 6px; border-radius: 4px; font-weight: 800; font-size: 10.5px; background: ${isLong ? 'var(--tv-up-soft)' : 'var(--tv-down-soft)'}; color: ${isLong ? 'var(--tv-up)' : 'var(--tv-down)'};">
                                            ${p.leverage}x ${p.side.toUpperCase()}
                                        </span>
                                    </td>
                                    <td style="font-weight: 800; color: #ffffff;">${p.position_size.toFixed(4)} UC</td>
                                    <td>৳ ${p.entry_price.toFixed(4)}</td>
                                    <td style="color: #ffffff;">৳ ${p.mark_price.toFixed(4)}</td>
                                    <td style="color: #f87171; font-weight: 800;">৳ ${p.liquidation_price.toFixed(4)}</td>
                                    <td>৳ ${p.margin.toFixed(2)}</td>
                                    <td style="color: ${pnlColor}; font-weight: 800;">
                                        ${p.unrealized_pnl >= 0 ? '+' : ''}৳ ${p.unrealized_pnl.toFixed(2)} (${p.roi_pct.toFixed(2)}%)
                                    </td>
                                    <td style="text-align: right;">
                                        <button class="btn-table-close" onclick="promptClosePosition(${p.id}, '${p.side}', ${p.position_size}, ${p.unrealized_pnl})">Market Close</button>
                                    </td>
                                </tr>
                            `;
                        }).join('');
                    }

                    // Orders Table
                    const openOrders = pData.open_orders || [];
                    document.getElementById('count-open-orders').textContent = openOrders.length;
                    if (openOrders.length === 0) {
                        document.getElementById('open-orders-tbody').innerHTML = `
                            <tr><td colspan="7" style="text-align: center; padding: 24px 0; color: var(--tv-text-dim); font-family: sans-serif;">No open limit orders.</td></tr>
                        `;
                    } else {
                        document.getElementById('open-orders-tbody').innerHTML = openOrders.map(o => `
                            <tr>
                                <td style="color: var(--tv-text-dim);">#${o.id}</td>
                                <td style="text-transform: uppercase;">${o.order_type}</td>
                                <td style="color: ${o.side === 'buy' ? 'var(--tv-up)' : 'var(--tv-down)'}; font-weight: 800;">${o.side.toUpperCase()}</td>
                                <td>৳ ${parseFloat(o.price).toFixed(4)}</td>
                                <td>${parseFloat(o.amount).toFixed(4)} UC</td>
                                <td>৳ ${parseFloat(o.margin).toFixed(2)}</td>
                                <td style="text-align: right;">
                                    <button class="btn-table-close" onclick="promptCancelOrder(${o.id})">Cancel</button>
                                </td>
                            </tr>
                        `).join('');
                    }

                    // History Table
                    const hist = pData.closed_positions || [];
                    if (hist.length === 0) {
                        document.getElementById('history-tbody').innerHTML = `
                            <tr><td colspan="8" style="text-align: center; padding: 24px 0; color: var(--tv-text-dim); font-family: sans-serif;">No past positions found.</td></tr>
                        `;
                    } else {
                        document.getElementById('history-tbody').innerHTML = hist.map(h => {
                            const pnlColor = h.pnl >= 0 ? 'var(--tv-up)' : 'var(--tv-down)';
                            return `
                                <tr>
                                    <td style="color: var(--tv-text-dim);">#${h.id}</td>
                                    <td style="color: ${h.side === 'long' ? 'var(--tv-up)' : 'var(--tv-down)'}; font-weight: 800;">${h.leverage}x ${h.side.toUpperCase()}</td>
                                    <td>৳ ${h.entry_price.toFixed(4)}</td>
                                    <td>৳ ${h.exit_price.toFixed(4)}</td>
                                    <td>৳ ${h.margin.toFixed(2)}</td>
                                    <td style="color: ${pnlColor}; font-weight: 800;">${h.pnl >= 0 ? '+' : ''}৳ ${h.pnl.toFixed(2)} (${h.roi_pct.toFixed(2)}%)</td>
                                    <td style="text-transform: uppercase; font-size: 10.5px;">${h.close_reason || h.status}</td>
                                    <td style="color: var(--tv-text-dim); font-size: 10.5px;">${h.closed_at || h.created_at}</td>
                                </tr>
                            `;
                        }).join('');
                    }
                }
            } catch (err) {
                console.error("fetchMarketAndPositions Error:", err);
            }
        }

        // Initialize on load
        window.addEventListener('DOMContentLoaded', () => {
            initChart();
            fetchMarketAndPositions();

            setInterval(fetchMarketAndPositions, 2000);
            setInterval(loadChartData, 10000);
        });
    </script>
</body>
</html>
