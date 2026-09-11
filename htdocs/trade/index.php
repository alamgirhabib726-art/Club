<?php
/**
 * UNMOOR CLUB - PROFESSIONAL TRADING TERMINAL
 * Pair: UC/BDT (Trading UC denominated in BDT)
 * Features: 1x-100x Leverage Futures, Spot Market/Limit, Real-time Candlestick Chart,
 * Live Orderbook, Dynamic Mark PnL, Liquidation Guard, and Liquidity Pool.
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UC/BDT Pro Trading Terminal — Unmoor Club</title>
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Lightweight Charts for TradingView grade financial charting -->
    <script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>
    <style>
        body {
            background-color: #030712;
            color: #f3f4f6;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            overflow-x: hidden;
        }
        .trade-header {
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
        }
        .stat-badge {
            display: flex;
            flex-direction: column;
            padding: 0 14px;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
        }
        .terminal-panel {
            background: #0b1120;
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 12px;
        }
        .tab-active {
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }
        .price-up { color: #10b981; }
        .price-down { color: #ef4444; }
        .btn-long {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            transition: all 0.2s;
        }
        .btn-long:hover { background: linear-gradient(135deg, #34d399, #10b981); }
        .btn-short {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
            transition: all 0.2s;
        }
        .btn-short:hover { background: linear-gradient(135deg, #f87171, #ef4444); }
        .depth-row {
            display: flex;
            justify-content: space-between;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            padding: 3px 6px;
            position: relative;
        }
        .depth-bar-bid {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            background: rgba(16, 185, 129, 0.12);
            pointer-events: none;
        }
        .depth-bar-ask {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            background: rgba(239, 68, 68, 0.12);
            pointer-events: none;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: #030712; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Top Navigation & Market Ticker -->
    <header class="trade-header px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 sticky top-0 z-50">
        <div class="flex items-center gap-4">
            <a href="/dashboard.php" class="flex items-center gap-2 text-slate-300 hover:text-white text-xs font-semibold bg-slate-800/80 px-2.5 py-1.5 rounded-lg border border-slate-700">
                <i class="fa-solid fa-arrow-left"></i>
                Club
            </a>
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center font-bold text-sm border border-blue-500/30">
                    UC
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-base text-white tracking-wide">UC / BDT</span>
                        <span class="text-[10px] bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded font-mono">Trading UC</span>
                    </div>
                    <div class="text-[11px] text-slate-400">Unmoor Club Exchange</div>
                </div>
            </div>
            <div class="hidden lg:flex items-center pl-4 border-l border-slate-800">
                <div class="text-xl font-mono font-bold transition-colors duration-300" id="header-mark-price">
                    ৳ <?= number_format((float)($marketState['price'] ?? 2.0), 4) ?>
                </div>
            </div>
        </div>

        <!-- 24h Rolling Ticker Stats -->
        <div class="hidden md:flex items-center overflow-x-auto text-xs py-1">
            <div class="stat-badge">
                <span class="text-[10px] text-slate-400 uppercase tracking-wider">24h Change</span>
                <span id="stat-24h-change" class="font-mono font-semibold <?= ((float)($marketState['change_24h'] ?? 0) >= 0) ? 'text-emerald-400' : 'text-red-400' ?>">
                    <?= ((float)($marketState['change_24h'] ?? 0) >= 0 ? '+' : '') . number_format((float)($marketState['change_24h'] ?? 0), 2) ?>%
                </span>
            </div>
            <div class="stat-badge">
                <span class="text-[10px] text-slate-400 uppercase tracking-wider">24h High</span>
                <span id="stat-24h-high" class="font-mono text-slate-200">৳ <?= number_format((float)($marketState['high_24h'] ?? 2.0), 4) ?></span>
            </div>
            <div class="stat-badge">
                <span class="text-[10px] text-slate-400 uppercase tracking-wider">24h Low</span>
                <span id="stat-24h-low" class="font-mono text-slate-200">৳ <?= number_format((float)($marketState['low_24h'] ?? 2.0), 4) ?></span>
            </div>
            <div class="stat-badge">
                <span class="text-[10px] text-slate-400 uppercase tracking-wider">24h Volume (UC)</span>
                <span id="stat-24h-vol" class="font-mono text-slate-200"><?= number_format((float)($marketState['volume_24h'] ?? 0), 2) ?> UC</span>
            </div>
            <div class="stat-badge">
                <span class="text-[10px] text-slate-400 uppercase tracking-wider">Liquidity Pool</span>
                <span id="stat-pool-health" class="font-mono text-emerald-400 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Operational
                </span>
            </div>
        </div>

        <!-- User Quick Balances & Conversion Link -->
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <div class="text-xs text-slate-400">Trading Equity</div>
                <div class="text-xs font-mono font-bold text-emerald-400" id="user-total-equity">
                    ৳ <?= number_format((float)$balances['trading_wallet']['trading_equity'], 2) ?>
                </div>
            </div>
            <a href="/convert.php" class="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-700 flex items-center gap-1.5">
                <i class="fa-solid fa-arrows-rotate text-blue-400"></i>
                Convert / Wallet
            </a>
        </div>
    </header>

    <!-- Main Workspace Container -->
    <div class="flex-1 p-3 grid grid-cols-1 xl:grid-cols-12 gap-3 max-w-[1920px] mx-auto w-full">

        <!-- Column 1: Chart & Orderbook (8 Cols on XL) -->
        <div class="xl:col-span-8 flex flex-col gap-3">
            
            <!-- Candlestick Chart Box -->
            <div class="terminal-panel p-3 flex flex-col h-[480px]">
                <!-- Chart Controls -->
                <div class="flex items-center justify-between pb-2 border-b border-slate-800 mb-2">
                    <div class="flex items-center gap-1">
                        <span class="text-xs font-bold text-slate-400 uppercase mr-2"><i class="fa-solid fa-chart-candlestick text-blue-500 mr-1"></i> Timeframe</span>
                        <?php foreach (['1m', '5m', '15m', '1h', '4h', '1d'] as $tf): ?>
                            <button class="timeframe-btn px-2 py-0.5 text-xs rounded font-mono font-medium <?= $tf === '1m' ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800/60' ?>" data-tf="<?= $tf ?>" onclick="changeTimeframe('<?= $tf ?>')">
                                <?= $tf ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-mono text-slate-400">
                        <span>O: <span id="c-open" class="text-white">-</span></span>
                        <span>H: <span id="c-high" class="text-white">-</span></span>
                        <span>L: <span id="c-low" class="text-white">-</span></span>
                        <span>C: <span id="c-close" class="text-white">-</span></span>
                    </div>
                </div>

                <!-- Lightweight Chart Canvas Container -->
                <div id="trading-chart-container" class="flex-1 w-full relative"></div>
            </div>

            <!-- Orderbook & Recent Trades Split -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <!-- Live Order Book -->
                <div class="terminal-panel p-3">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-300 pb-2 border-b border-slate-800 mb-2">
                        <span><i class="fa-solid fa-bars-staggered text-blue-400 mr-1"></i> Order Book</span>
                        <span class="text-[10px] text-slate-500 font-normal">Spread: <span id="ob-spread" class="font-mono text-slate-400">0.0050</span></span>
                    </div>
                    <div class="grid grid-cols-3 text-[10px] text-slate-500 font-semibold px-1 pb-1">
                        <span>PRICE (BDT)</span>
                        <span class="text-right">SIZE (UC)</span>
                        <span class="text-right">TOTAL</span>
                    </div>
                    <!-- Asks (Red) -->
                    <div id="orderbook-asks" class="flex flex-col-reverse gap-0.5 mb-1"></div>
                    <!-- Mid Price -->
                    <div class="py-1 my-1 px-2 bg-slate-900/90 rounded border border-slate-800/80 flex items-center justify-between font-mono text-xs">
                        <span id="ob-mid-price" class="font-bold text-emerald-400">৳ <?= number_format((float)($marketState['price'] ?? 2.0), 4) ?></span>
                        <span class="text-[10px] text-slate-500">Mark Price</span>
                    </div>
                    <!-- Bids (Green) -->
                    <div id="orderbook-bids" class="flex flex-col gap-0.5"></div>
                </div>

                <!-- Recent Market Trades -->
                <div class="terminal-panel p-3">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-300 pb-2 border-b border-slate-800 mb-2">
                        <span><i class="fa-solid fa-bolt text-amber-400 mr-1"></i> Recent Market Trades</span>
                        <span class="text-[10px] text-slate-500">Real-time Executions</span>
                    </div>
                    <div class="grid grid-cols-3 text-[10px] text-slate-500 font-semibold px-1 pb-1">
                        <span>PRICE (BDT)</span>
                        <span class="text-right">AMOUNT (UC)</span>
                        <span class="text-right">TIME</span>
                    </div>
                    <div id="recent-trades-list" class="flex flex-col gap-1 max-h-[195px] overflow-y-auto font-mono text-xs"></div>
                </div>
            </div>
        </div>

        <!-- Column 2: Order Execution Terminal (4 Cols on XL) -->
        <div class="xl:col-span-4 flex flex-col gap-3">
            
            <!-- Order Form Panel -->
            <div class="terminal-panel p-4">
                <!-- Mode Switcher: Futures Leverage vs Spot -->
                <div class="flex bg-slate-900 p-1 rounded-lg border border-slate-800 mb-4">
                    <button class="flex-1 py-1.5 text-xs font-bold rounded-md transition-all text-white bg-blue-600" id="mode-tab-futures" onclick="switchTradeMode('futures')">
                        <i class="fa-solid fa-bolt mr-1"></i> Futures (1x-100x)
                    </button>
                    <button class="flex-1 py-1.5 text-xs font-bold rounded-md transition-all text-slate-400 hover:text-white" id="mode-tab-spot" onclick="switchTradeMode('spot')">
                        <i class="fa-solid fa-store mr-1"></i> Spot Exchange
                    </button>
                </div>

                <!-- FUTURES TRADING FORM -->
                <div id="form-futures-container">
                    <!-- Long / Short Buttons -->
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <button type="button" class="py-2.5 rounded-lg font-bold text-xs flex items-center justify-center gap-1.5 border border-emerald-500/30 btn-long" id="btn-side-long" onclick="selectFuturesSide('long')">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                            OPEN LONG / BUY
                        </button>
                        <button type="button" class="py-2.5 rounded-lg font-bold text-xs flex items-center justify-center gap-1.5 border border-red-500/30 opacity-50 hover:opacity-100 btn-short" id="btn-side-short" onclick="selectFuturesSide('short')">
                            <i class="fa-solid fa-arrow-trend-down"></i>
                            OPEN SHORT / SELL
                        </button>
                    </div>

                    <!-- Leverage Slider & Pills -->
                    <div class="mb-4 bg-slate-900/60 p-3 rounded-xl border border-slate-800">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-slate-400 font-semibold">Leverage Multiplier:</span>
                            <span class="text-xs font-mono font-bold text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded border border-blue-500/20" id="leverage-display">10x</span>
                        </div>
                        <input type="range" min="1" max="100" value="10" step="1" id="leverage-slider" class="w-full h-1.5 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-blue-500" oninput="onLeverageChange(this.value)">
                        <div class="flex justify-between gap-1 mt-2">
                            <?php foreach ([1, 5, 10, 25, 50, 100] as $lev): ?>
                                <button type="button" class="lev-pill text-[10px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 <?= $lev === 10 ? 'border border-blue-500 text-blue-400' : '' ?>" onclick="setLeverage(<?= $lev ?>)">
                                    <?= $lev ?>x
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Margin Input -->
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                            <span>Margin (BDT)</span>
                            <span>Available: <span class="text-emerald-400 font-mono font-semibold" id="disp-avail-margin">৳ <?= number_format((float)$balances['trading_wallet']['available_margin'], 2) ?></span></span>
                        </div>
                        <div class="relative">
                            <input type="number" step="0.01" min="10" id="futures-margin-input" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono text-sm focus:border-blue-500 focus:outline-none" placeholder="Min ৳ 10.00" oninput="calculateFuturesMetrics()">
                            <span class="absolute right-3 top-2 text-xs font-semibold text-slate-400">BDT</span>
                        </div>
                        <div class="flex gap-1.5 mt-2">
                            <button type="button" class="flex-1 py-1 text-[10px] font-mono rounded bg-slate-800 hover:bg-slate-700 text-slate-300" onclick="setMarginPercent(0.25)">25%</button>
                            <button type="button" class="flex-1 py-1 text-[10px] font-mono rounded bg-slate-800 hover:bg-slate-700 text-slate-300" onclick="setMarginPercent(0.50)">50%</button>
                            <button type="button" class="flex-1 py-1 text-[10px] font-mono rounded bg-slate-800 hover:bg-slate-700 text-slate-300" onclick="setMarginPercent(0.75)">75%</button>
                            <button type="button" class="flex-1 py-1 text-[10px] font-mono rounded bg-slate-800 hover:bg-slate-700 text-slate-300" onclick="setMarginPercent(1.00)">100%</button>
                        </div>
                    </div>

                    <!-- SL / TP Collapsible Settings -->
                    <div class="mb-4 bg-slate-900/40 p-2.5 rounded-lg border border-slate-800/80">
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center justify-between">
                            <span>Take Profit & Stop Loss (Optional)</span>
                            <i class="fa-solid fa-shield-halved text-blue-400"></i>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-emerald-400 font-semibold mb-0.5">Take Profit (BDT)</label>
                                <input type="number" step="0.0001" id="futures-tp-input" class="w-full bg-slate-950 border border-slate-800 rounded px-2 py-1 text-xs text-white font-mono" placeholder="Target Price" oninput="calculateFuturesMetrics()">
                            </div>
                            <div>
                                <label class="block text-[10px] text-red-400 font-semibold mb-0.5">Stop Loss (BDT)</label>
                                <input type="number" step="0.0001" id="futures-sl-input" class="w-full bg-slate-950 border border-slate-800 rounded px-2 py-1 text-xs text-white font-mono" placeholder="Trigger Price" oninput="calculateFuturesMetrics()">
                            </div>
                        </div>
                    </div>

                    <!-- Live Calculation Breakdown -->
                    <div class="bg-slate-900/80 rounded-lg p-3 border border-slate-800 text-xs font-mono space-y-1.5 mb-4">
                        <div class="flex justify-between text-slate-400">
                            <span>Entry Mark Price:</span>
                            <span class="text-slate-200" id="calc-entry-price">৳ <?= number_format((float)($marketState['price'] ?? 2.0), 4) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Position Size:</span>
                            <span class="text-slate-200" id="calc-pos-size">0.0000 UC</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Est. Liquidation Price:</span>
                            <span class="text-red-400 font-bold" id="calc-liq-price">৳ 0.0000</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Trading & Risk Fee:</span>
                            <span class="text-amber-400" id="calc-fees">৳ 0.00</span>
                        </div>
                    </div>

                    <!-- Open Position Submit Button -->
                    <button type="button" id="btn-submit-futures" class="w-full py-3 rounded-lg font-bold text-sm btn-long flex items-center justify-center gap-2 shadow-lg" onclick="submitFuturesOrder()">
                        <i class="fa-solid fa-bolt"></i>
                        <span id="btn-submit-futures-text">OPEN 10x LONG POSITION</span>
                    </button>
                </div>

                <!-- SPOT TRADING FORM -->
                <div id="form-spot-container" class="hidden">
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <button type="button" class="py-2 rounded-lg font-bold text-xs bg-emerald-600 text-white" id="btn-spot-buy" onclick="selectSpotSide('buy')">BUY UC</button>
                        <button type="button" class="py-2 rounded-lg font-bold text-xs bg-slate-800 text-slate-400 hover:text-white" id="btn-spot-sell" onclick="selectSpotSide('sell')">SELL UC</button>
                    </div>

                    <div class="flex gap-2 mb-3">
                        <button type="button" class="px-3 py-1 text-xs font-semibold rounded bg-blue-600 text-white" id="btn-spot-market" onclick="selectSpotType('market')">Market</button>
                        <button type="button" class="px-3 py-1 text-xs font-semibold rounded bg-slate-800 text-slate-400 hover:text-white" id="btn-spot-limit" onclick="selectSpotType('limit')">Limit</button>
                    </div>

                    <div class="mb-3 hidden" id="spot-limit-price-group">
                        <label class="block text-xs text-slate-400 mb-1">Limit Price (BDT)</label>
                        <input type="number" step="0.0001" id="spot-limit-price" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono text-sm" placeholder="2.0000">
                    </div>

                    <div class="mb-3">
                        <div class="flex justify-between text-xs text-slate-400 mb-1">
                            <span>Amount (Trading UC)</span>
                            <span id="spot-balance-hint">Avail: ৳ <?= number_format((float)$balances['trading_wallet']['available_margin'], 2) ?></span>
                        </div>
                        <input type="number" step="0.1" min="1" id="spot-amount-input" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono text-sm" placeholder="Min 1.0 UC">
                    </div>

                    <button type="button" id="btn-submit-spot" class="w-full py-3 rounded-lg font-bold text-sm bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center gap-2 mt-4" onclick="submitSpotOrder()">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span>BUY TRADING UC</span>
                    </button>
                </div>
            </div>

            <!-- Wallet Margin Status Widget -->
            <div class="terminal-panel p-3 text-xs">
                <div class="flex items-center justify-between font-bold text-slate-300 pb-2 border-b border-slate-800 mb-2">
                    <span><i class="fa-solid fa-wallet text-emerald-400 mr-1"></i> Trading Wallet Overview</span>
                    <a href="/convert.php?tab=transfer" class="text-blue-400 hover:underline text-[11px]">Manage Funds</a>
                </div>
                <div class="grid grid-cols-2 gap-2 text-slate-400 font-mono">
                    <div>Margin Balance: <span class="text-white font-semibold" id="w-margin-bal">৳ <?= number_format((float)$balances['trading_wallet']['bdt_balance'], 2) ?></span></div>
                    <div>Locked Margin: <span class="text-amber-400 font-semibold" id="w-locked-margin">৳ <?= number_format((float)$balances['trading_wallet']['locked_margin'], 2) ?></span></div>
                    <div>Trading UC (Spot): <span class="text-purple-400 font-semibold" id="w-tuc-bal"><?= number_format((float)$balances['trading_wallet']['tuc_balance'], 4) ?> UC</span></div>
                    <div>Realized PnL: <span class="<?= (float)$balances['trading_wallet']['realized_pnl'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>" id="w-realized-pnl">৳ <?= number_format((float)$balances['trading_wallet']['realized_pnl'], 2) ?></span></div>
                </div>
            </div>

        </div>

    </div>

    <!-- Bottom Section: Open Positions, Orders & History -->
    <div class="p-3 max-w-[1920px] mx-auto w-full">
        <div class="terminal-panel">
            <!-- Tabs -->
            <div class="flex items-center border-b border-slate-800 overflow-x-auto text-xs font-semibold">
                <button class="px-4 py-3 tab-active flex items-center gap-2" id="pos-tab-open" onclick="switchBottomTab('open')">
                    <i class="fa-solid fa-chart-pie text-blue-400"></i>
                    Open Positions (<span id="count-open-pos">0</span>)
                </button>
                <button class="px-4 py-3 text-slate-400 hover:text-white flex items-center gap-2" id="pos-tab-orders" onclick="switchBottomTab('orders')">
                    <i class="fa-solid fa-list-check text-amber-400"></i>
                    Open Limit Orders (<span id="count-open-orders">0</span>)
                </button>
                <button class="px-4 py-3 text-slate-400 hover:text-white flex items-center gap-2" id="pos-tab-history" onclick="switchBottomTab('history')">
                    <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                    Closed Positions & History
                </button>
            </div>

            <!-- TAB 1: Open Positions Table -->
            <div id="tab-content-open-pos" class="p-3 overflow-x-auto">
                <table class="w-full text-left text-xs font-mono">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 pb-2">
                            <th class="pb-2">ID</th>
                            <th class="pb-2">Side / Lev</th>
                            <th class="pb-2">Size (UC)</th>
                            <th class="pb-2">Entry Price</th>
                            <th class="pb-2">Mark Price</th>
                            <th class="pb-2">Liq. Price</th>
                            <th class="pb-2">Margin</th>
                            <th class="pb-2">SL / TP</th>
                            <th class="pb-2">Unrealized PnL (ROI)</th>
                            <th class="pb-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="open-positions-tbody" class="divide-y divide-slate-800/60">
                        <tr>
                            <td colspan="10" class="text-center py-6 text-slate-500 font-sans">No open positions. Use the order panel to open 1x-100x leveraged positions.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 2: Open Orders Table -->
            <div id="tab-content-open-orders" class="p-3 overflow-x-auto hidden">
                <table class="w-full text-left text-xs font-mono">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 pb-2">
                            <th class="pb-2">Order ID</th>
                            <th class="pb-2">Type</th>
                            <th class="pb-2">Side</th>
                            <th class="pb-2">Limit Price</th>
                            <th class="pb-2">Amount (UC)</th>
                            <th class="pb-2">Locked Margin</th>
                            <th class="pb-2">Status</th>
                            <th class="pb-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="open-orders-tbody" class="divide-y divide-slate-800/60">
                        <tr>
                            <td colspan="8" class="text-center py-6 text-slate-500 font-sans">No open limit orders.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 3: History Table -->
            <div id="tab-content-history" class="p-3 overflow-x-auto hidden">
                <table class="w-full text-left text-xs font-mono">
                    <thead>
                        <tr class="text-slate-400 border-b border-slate-800 pb-2">
                            <th class="pb-2">ID</th>
                            <th class="pb-2">Side / Lev</th>
                            <th class="pb-2">Entry Price</th>
                            <th class="pb-2">Exit Price</th>
                            <th class="pb-2">Margin</th>
                            <th class="pb-2">Realized PnL</th>
                            <th class="pb-2">Reason</th>
                            <th class="pb-2">Date Closed</th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody" class="divide-y divide-slate-800/60">
                        <tr>
                            <td colspan="8" class="text-center py-6 text-slate-500 font-sans">No position history recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit SL/TP Modal -->
    <div id="modal-sltp" class="fixed inset-0 bg-black/80 backdrop-filter backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="bg-slate-900 border border-slate-700 rounded-xl p-5 max-w-sm w-full">
            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-blue-400"></i>
                Update Take Profit & Stop Loss
            </h3>
            <input type="hidden" id="modal-pos-id">
            <div class="mb-3">
                <label class="block text-xs text-emerald-400 font-semibold mb-1">Take Profit Price (BDT)</label>
                <input type="number" step="0.0001" id="modal-tp-val" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono text-sm">
            </div>
            <div class="mb-4">
                <label class="block text-xs text-red-400 font-semibold mb-1">Stop Loss Price (BDT)</label>
                <input type="number" step="0.0001" id="modal-sl-val" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono text-sm">
            </div>
            <div class="flex gap-2">
                <button type="button" class="flex-1 py-2 rounded-lg bg-slate-800 text-slate-300 font-semibold text-xs" onclick="closeSlTpModal()">Cancel</button>
                <button type="button" class="flex-1 py-2 rounded-lg bg-blue-600 text-white font-semibold text-xs" onclick="saveSlTpModal()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Core Terminal JS Engine -->
    <script>
        let currentPrice = <?= (float)($marketState['price'] ?? 2.0000) ?>;
        let selectedSide = 'long'; // 'long' | 'short'
        let currentLeverage = 10;
        let activeTimeframe = '1m';
        let tradeMode = 'futures'; // 'futures' | 'spot'
        let spotSide = 'buy'; // 'buy' | 'sell'
        let spotType = 'market'; // 'market' | 'limit'

        let chart = null;
        let candleSeries = null;
        let volumeSeries = null;

        // Initialize lightweight chart
        function initChart() {
            const container = document.getElementById('trading-chart-container');
            chart = LightweightCharts.createChart(container, {
                width: container.clientWidth,
                height: container.clientHeight || 420,
                layout: {
                    background: { color: '#0b1120' },
                    textColor: '#94a3b8',
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
                upColor: '#10b981',
                downColor: '#ef4444',
                borderDownColor: '#ef4444',
                borderUpColor: '#10b981',
                wickDownColor: '#ef4444',
                wickUpColor: '#10b981',
            });

            // Resize observer
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
                        document.getElementById('c-open').textContent = '৳ ' + last.open.toFixed(4);
                        document.getElementById('c-high').textContent = '৳ ' + last.high.toFixed(4);
                        document.getElementById('c-low').textContent = '৳ ' + last.low.toFixed(4);
                        document.getElementById('c-close').textContent = '৳ ' + last.close.toFixed(4);
                    }
                }
            } catch (e) {
                console.error("Failed to load chart data:", e);
            }
        }

        function changeTimeframe(tf) {
            activeTimeframe = tf;
            document.querySelectorAll('.timeframe-btn').forEach(btn => {
                const isActive = btn.getAttribute('data-tf') === tf;
                btn.className = `timeframe-btn px-2 py-0.5 text-xs rounded font-mono font-medium ${isActive ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800/60'}`;
            });
            loadChartData();
        }

        // Switch trade mode
        function switchTradeMode(mode) {
            tradeMode = mode;
            document.getElementById('mode-tab-futures').className = `flex-1 py-1.5 text-xs font-bold rounded-md transition-all ${mode === 'futures' ? 'text-white bg-blue-600' : 'text-slate-400 hover:text-white'}`;
            document.getElementById('mode-tab-spot').className = `flex-1 py-1.5 text-xs font-bold rounded-md transition-all ${mode === 'spot' ? 'text-white bg-blue-600' : 'text-slate-400 hover:text-white'}`;
            
            document.getElementById('form-futures-container').classList.toggle('hidden', mode !== 'futures');
            document.getElementById('form-spot-container').classList.toggle('hidden', mode !== 'spot');
        }

        // Side selector for futures
        function selectFuturesSide(side) {
            selectedSide = side;
            const longBtn = document.getElementById('btn-side-long');
            const shortBtn = document.getElementById('btn-side-short');
            const submitBtn = document.getElementById('btn-submit-futures');
            const submitText = document.getElementById('btn-submit-futures-text');

            if (side === 'long') {
                longBtn.classList.remove('opacity-50');
                shortBtn.classList.add('opacity-50');
                submitBtn.className = 'w-full py-3 rounded-lg font-bold text-sm btn-long flex items-center justify-center gap-2 shadow-lg';
                submitText.textContent = `OPEN ${currentLeverage}x LONG POSITION`;
            } else {
                shortBtn.classList.remove('opacity-50');
                longBtn.classList.add('opacity-50');
                submitBtn.className = 'w-full py-3 rounded-lg font-bold text-sm btn-short flex items-center justify-center gap-2 shadow-lg';
                submitText.textContent = `OPEN ${currentLeverage}x SHORT POSITION`;
            }
            calculateFuturesMetrics();
        }

        function onLeverageChange(val) {
            currentLeverage = parseInt(val);
            document.getElementById('leverage-display').textContent = currentLeverage + 'x';
            document.getElementById('btn-submit-futures-text').textContent = `OPEN ${currentLeverage}x ${selectedSide.toUpperCase()} POSITION`;
            
            document.querySelectorAll('.lev-pill').forEach(btn => {
                btn.className = `lev-pill text-[10px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 ${parseInt(btn.textContent) === currentLeverage ? 'border border-blue-500 text-blue-400' : ''}`;
            });
            calculateFuturesMetrics();
        }

        function setLeverage(val) {
            document.getElementById('leverage-slider').value = val;
            onLeverageChange(val);
        }

        function setMarginPercent(pct) {
            const avail = parseFloat(document.getElementById('disp-avail-margin').textContent.replace(/[^0-9.]/g, '')) || 0;
            const val = Math.max(10, (avail * pct)).toFixed(2);
            document.getElementById('futures-margin-input').value = val;
            calculateFuturesMetrics();
        }

        function calculateFuturesMetrics() {
            const margin = parseFloat(document.getElementById('futures-margin-input').value) || 0;
            const entryPrice = currentPrice;

            document.getElementById('calc-entry-price').textContent = '৳ ' + entryPrice.toFixed(4);

            if (margin <= 0 || entryPrice <= 0) {
                document.getElementById('calc-pos-size').textContent = '0.0000 UC';
                document.getElementById('calc-liq-price').textContent = '৳ 0.0000';
                document.getElementById('calc-fees').textContent = '৳ 0.00';
                return;
            }

            const notional = margin * currentLeverage;
            const posSize = notional / entryPrice;
            document.getElementById('calc-pos-size').textContent = posSize.toFixed(4) + ' UC';

            // Liquidation calc: MM = 0.5%
            const mmRate = 0.0050;
            let liqPrice = 0;
            if (selectedSide === 'long') {
                liqPrice = entryPrice * (1.0 - ((1.0 - mmRate) / currentLeverage));
                liqPrice = Math.max(0.01, liqPrice);
            } else {
                liqPrice = entryPrice * (1.0 + ((1.0 - mmRate) / currentLeverage));
            }
            document.getElementById('calc-liq-price').textContent = '৳ ' + liqPrice.toFixed(4);

            // Fees: 0.10% taker + 0.01% per 10x leverage
            const takerFee = notional * 0.0010;
            const levFee = margin * (currentLeverage / 10.0) * 0.0001;
            const totalFee = takerFee + levFee;
            document.getElementById('calc-fees').textContent = '৳ ' + totalFee.toFixed(2);
        }

        // Spot controls
        function selectSpotSide(side) {
            spotSide = side;
            document.getElementById('btn-spot-buy').className = `py-2 rounded-lg font-bold text-xs ${side === 'buy' ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'}`;
            document.getElementById('btn-spot-sell').className = `py-2 rounded-lg font-bold text-xs ${side === 'sell' ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'}`;
            document.getElementById('btn-submit-spot').className = `w-full py-3 rounded-lg font-bold text-sm ${side === 'buy' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-red-600 hover:bg-red-500'} text-white flex items-center justify-center gap-2 mt-4`;
            document.getElementById('btn-submit-spot').innerHTML = `<i class="fa-solid fa-cart-shopping"></i> <span>${side === 'buy' ? 'BUY TRADING UC' : 'SELL TRADING UC'}</span>`;
        }

        function selectSpotType(type) {
            spotType = type;
            document.getElementById('btn-spot-market').className = `px-3 py-1 text-xs font-semibold rounded ${type === 'market' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'}`;
            document.getElementById('btn-spot-limit').className = `px-3 py-1 text-xs font-semibold rounded ${type === 'limit' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'}`;
            document.getElementById('spot-limit-price-group').classList.toggle('hidden', type !== 'limit');
        }

        // Bottom tabs
        function switchBottomTab(tab) {
            ['open', 'orders', 'history'].forEach(t => {
                document.getElementById('pos-tab-' + t).classList.toggle('tab-active', t === tab);
                document.getElementById('tab-content-' + (t === 'open' ? 'open-pos' : (t === 'orders' ? 'open-orders' : 'history'))).classList.toggle('hidden', t !== tab);
            });
        }

        // Submit Futures Order
        async function submitFuturesOrder() {
            const margin = parseFloat(document.getElementById('futures-margin-input').value);
            if (!margin || margin < 10) {
                alert('Minimum margin required is ৳ 10.00 BDT.');
                return;
            }

            const tp = parseFloat(document.getElementById('futures-tp-input').value) || null;
            const sl = parseFloat(document.getElementById('futures-sl-input').value) || null;

            const fd = new FormData();
            fd.append('action', 'open_leverage');
            fd.append('side', selectedSide);
            fd.append('margin', margin);
            fd.append('leverage', currentLeverage);
            if (tp) fd.append('take_profit', tp);
            if (sl) fd.append('stop_loss', sl);

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    document.getElementById('futures-margin-input').value = '';
                    fetchMarketAndPositions();
                } else {
                    alert(data.error || 'Failed to open position.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        // Submit Spot Order
        async function submitSpotOrder() {
            const amount = parseFloat(document.getElementById('spot-amount-input').value);
            if (!amount || amount < 1) {
                alert('Minimum order is 1.0 Trading UC.');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'spot_order');
            fd.append('side', spotSide);
            fd.append('amount', amount);
            fd.append('order_type', spotType);
            if (spotType === 'limit') {
                const lp = parseFloat(document.getElementById('spot-limit-price').value);
                if (!lp || lp <= 0) {
                    alert('Valid Limit Price is required.');
                    return;
                }
                fd.append('limit_price', lp);
            }

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    document.getElementById('spot-amount-input').value = '';
                    fetchMarketAndPositions();
                } else {
                    alert(data.error || 'Failed to execute spot order.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        // Close Position
        async function closePosition(posId) {
            if (!confirm(`Are you sure you want to market close position #${posId}?`)) return;

            const fd = new FormData();
            fd.append('action', 'close_position');
            fd.append('position_id', posId);

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    fetchMarketAndPositions();
                } else {
                    alert(data.error || 'Failed to close position.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        // Cancel Order
        async function cancelOrder(orderId) {
            if (!confirm(`Cancel order #${orderId}?`)) return;

            const fd = new FormData();
            fd.append('action', 'cancel_order');
            fd.append('order_id', orderId);

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    fetchMarketAndPositions();
                } else {
                    alert(data.error || 'Failed to cancel order.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        // SL/TP Modal
        function openSlTpModal(posId, tp, sl) {
            document.getElementById('modal-pos-id').value = posId;
            document.getElementById('modal-tp-val').value = tp || '';
            document.getElementById('modal-sl-val').value = sl || '';
            document.getElementById('modal-sltp').classList.remove('hidden');
        }

        function closeSlTpModal() {
            document.getElementById('modal-sltp').classList.add('hidden');
        }

        async function saveSlTpModal() {
            const posId = document.getElementById('modal-pos-id').value;
            const tp = parseFloat(document.getElementById('modal-tp-val').value) || null;
            const sl = parseFloat(document.getElementById('modal-sl-val').value) || null;

            const fd = new FormData();
            fd.append('action', 'update_sl_tp');
            fd.append('position_id', posId);
            if (tp) fd.append('take_profit', tp);
            if (sl) fd.append('stop_loss', sl);

            try {
                const res = await fetch('/trade/api/order.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    closeSlTpModal();
                    fetchMarketAndPositions();
                } else {
                    alert(data.error || 'Failed to update SL/TP.');
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        // Main Market & Positions Poller
        async function fetchMarketAndPositions() {
            try {
                // 1. Fetch Market Data
                const mRes = await fetch('/trade/api/market_data.php');
                const mData = await mRes.json();
                if (mData.success && mData.market) {
                    const prevPrice = currentPrice;
                    currentPrice = parseFloat(mData.market.price);

                    const priceEl = document.getElementById('header-mark-price');
                    priceEl.textContent = '৳ ' + currentPrice.toFixed(4);
                    priceEl.className = `text-xl font-mono font-bold transition-colors duration-300 ${currentPrice >= prevPrice ? 'text-emerald-400' : 'text-red-400'}`;

                    document.getElementById('ob-mid-price').textContent = '৳ ' + currentPrice.toFixed(4);
                    document.getElementById('stat-24h-high').textContent = '৳ ' + parseFloat(mData.market.high_24h).toFixed(4);
                    document.getElementById('stat-24h-low').textContent = '৳ ' + parseFloat(mData.market.low_24h).toFixed(4);
                    
                    const chg = parseFloat(mData.market.change_24h);
                    const chgEl = document.getElementById('stat-24h-change');
                    chgEl.textContent = (chg >= 0 ? '+' : '') + chg.toFixed(2) + '%';
                    chgEl.className = `font-mono font-semibold ${chg >= 0 ? 'text-emerald-400' : 'text-red-400'}`;
                    
                    document.getElementById('stat-24h-vol').textContent = parseFloat(mData.market.volume_24h).toFixed(2) + ' UC';

                    // Update Orderbook
                    if (mData.orderbook) {
                        const asksHtml = mData.orderbook.asks.map(a => `
                            <div class="depth-row text-red-400">
                                <span class="font-bold">৳ ${a.price.toFixed(4)}</span>
                                <span class="text-right text-slate-300">${a.amount.toFixed(2)}</span>
                                <span class="text-right text-slate-400">৳ ${a.total.toFixed(2)}</span>
                                <div class="depth-bar-ask" style="width: ${Math.min(100, a.amount / 3)}%"></div>
                            </div>
                        `).join('');
                        document.getElementById('orderbook-asks').innerHTML = asksHtml;

                        const bidsHtml = mData.orderbook.bids.map(b => `
                            <div class="depth-row text-emerald-400">
                                <span class="font-bold">৳ ${b.price.toFixed(4)}</span>
                                <span class="text-right text-slate-300">${b.amount.toFixed(2)}</span>
                                <span class="text-right text-slate-400">৳ ${b.total.toFixed(2)}</span>
                                <div class="depth-bar-bid" style="width: ${Math.min(100, b.amount / 3)}%"></div>
                            </div>
                        `).join('');
                        document.getElementById('orderbook-bids').innerHTML = bidsHtml;

                        const spread = (mData.orderbook.asks[0]?.price || currentPrice) - (mData.orderbook.bids[0]?.price || currentPrice);
                        document.getElementById('ob-spread').textContent = Math.abs(spread).toFixed(4);
                    }

                    // Update Recent Trades
                    if (mData.recent_trades) {
                        document.getElementById('recent-trades-list').innerHTML = mData.recent_trades.map(t => `
                            <div class="grid grid-cols-3 py-0.5 border-b border-slate-900">
                                <span class="${t.side === 'buy' ? 'text-emerald-400' : 'text-red-400'}">৳ ${t.price.toFixed(4)}</span>
                                <span class="text-right text-slate-300">${t.amount.toFixed(2)}</span>
                                <span class="text-right text-slate-500 text-[10px]">${t.time}</span>
                            </div>
                        `).join('');
                    }

                    calculateFuturesMetrics();
                }

                // 2. Fetch User Positions & Balances
                const pRes = await fetch('/trade/api/positions_api.php');
                const pData = await pRes.json();
                if (pData.success) {
                    // Update Balances
                    if (pData.balances?.trading_wallet) {
                        const tw = pData.balances.trading_wallet;
                        document.getElementById('disp-avail-margin').textContent = '৳ ' + parseFloat(tw.available_margin).toFixed(2);
                        document.getElementById('w-margin-bal').textContent = '৳ ' + parseFloat(tw.bdt_balance).toFixed(2);
                        document.getElementById('w-locked-margin').textContent = '৳ ' + parseFloat(tw.locked_margin).toFixed(2);
                        document.getElementById('w-tuc-bal').textContent = parseFloat(tw.tuc_balance).toFixed(4) + ' UC';
                        document.getElementById('user-total-equity').textContent = '৳ ' + parseFloat(tw.trading_equity).toFixed(2);
                    }

                    // Render Open Positions
                    const openPos = pData.open_positions || [];
                    document.getElementById('count-open-pos').textContent = openPos.length;

                    if (openPos.length === 0) {
                        document.getElementById('open-positions-tbody').innerHTML = `
                            <tr><td colspan="10" class="text-center py-6 text-slate-500 font-sans">No open positions. Use the order panel to open 1x-100x leveraged positions.</td></tr>
                        `;
                    } else {
                        document.getElementById('open-positions-tbody').innerHTML = openPos.map(p => {
                            const isLong = p.side === 'long';
                            const pnlClass = p.unrealized_pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
                            return `
                                <tr class="hover:bg-slate-900/40">
                                    <td class="py-2.5 text-slate-500">#${p.id}</td>
                                    <td class="py-2.5">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold ${isLong ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}">
                                            ${p.leverage}x ${p.side.toUpperCase()}
                                        </span>
                                    </td>
                                    <td class="py-2.5 font-bold text-slate-200">${p.position_size.toFixed(4)} UC</td>
                                    <td class="py-2.5 text-slate-300">৳ ${p.entry_price.toFixed(4)}</td>
                                    <td class="py-2.5 text-slate-200">৳ ${p.mark_price.toFixed(4)}</td>
                                    <td class="py-2.5 text-red-400 font-bold">৳ ${p.liquidation_price.toFixed(4)}</td>
                                    <td class="py-2.5 text-slate-300">৳ ${p.margin.toFixed(2)}</td>
                                    <td class="py-2.5 text-[10px]">
                                        <div>TP: <span class="text-emerald-400">${p.take_profit ? '৳ ' + p.take_profit.toFixed(4) : 'None'}</span></div>
                                        <div>SL: <span class="text-red-400">${p.stop_loss ? '৳ ' + p.stop_loss.toFixed(4) : 'None'}</span></div>
                                    </td>
                                    <td class="py-2.5 font-bold ${pnlClass}">
                                        ${p.unrealized_pnl >= 0 ? '+' : ''}৳ ${p.unrealized_pnl.toFixed(2)} (${p.roi_pct.toFixed(2)}%)
                                    </td>
                                    <td class="py-2.5 text-right space-x-1">
                                        <button class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-blue-400 rounded text-[10px]" onclick="openSlTpModal(${p.id}, ${p.take_profit || 0}, ${p.stop_loss || 0})">SL/TP</button>
                                        <button class="px-2 py-1 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded text-[10px] border border-red-500/30" onclick="closePosition(${p.id})">Close</button>
                                    </td>
                                </tr>
                            `;
                        }).join('');
                    }

                    // Render Open Limit Orders
                    const openOrders = pData.open_orders || [];
                    document.getElementById('count-open-orders').textContent = openOrders.length;
                    if (openOrders.length === 0) {
                        document.getElementById('open-orders-tbody').innerHTML = `
                            <tr><td colspan="8" class="text-center py-6 text-slate-500 font-sans">No open limit orders.</td></tr>
                        `;
                    } else {
                        document.getElementById('open-orders-tbody').innerHTML = openOrders.map(o => `
                            <tr class="hover:bg-slate-900/40">
                                <td class="py-2.5 text-slate-500">#${o.id}</td>
                                <td class="py-2.5 uppercase text-slate-300">${o.order_type}</td>
                                <td class="py-2.5 font-bold ${o.side === 'buy' ? 'text-emerald-400' : 'text-red-400'}">${o.side.toUpperCase()}</td>
                                <td class="py-2.5 text-slate-200">৳ ${parseFloat(o.price).toFixed(4)}</td>
                                <td class="py-2.5 text-slate-300">${parseFloat(o.amount).toFixed(4)} UC</td>
                                <td class="py-2.5 text-slate-400">৳ ${parseFloat(o.margin).toFixed(2)}</td>
                                <td class="py-2.5"><span class="px-1.5 py-0.5 rounded text-[10px] bg-amber-500/10 text-amber-400">OPEN</span></td>
                                <td class="py-2.5 text-right">
                                    <button class="px-2 py-1 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded text-[10px]" onclick="cancelOrder(${o.id})">Cancel</button>
                                </td>
                            </tr>
                        `).join('');
                    }

                    // Render History
                    const hist = pData.closed_positions || [];
                    if (hist.length === 0) {
                        document.getElementById('history-tbody').innerHTML = `
                            <tr><td colspan="8" class="text-center py-6 text-slate-500 font-sans">No position history recorded yet.</td></tr>
                        `;
                    } else {
                        document.getElementById('history-tbody').innerHTML = hist.map(h => {
                            const pnlClass = h.pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
                            return `
                                <tr class="hover:bg-slate-900/40">
                                    <td class="py-2.5 text-slate-500">#${h.id}</td>
                                    <td class="py-2.5 font-bold ${h.side === 'long' ? 'text-emerald-400' : 'text-red-400'}">${h.leverage}x ${h.side.toUpperCase()}</td>
                                    <td class="py-2.5 text-slate-300">৳ ${h.entry_price.toFixed(4)}</td>
                                    <td class="py-2.5 text-slate-300">৳ ${h.exit_price.toFixed(4)}</td>
                                    <td class="py-2.5 text-slate-400">৳ ${h.margin.toFixed(2)}</td>
                                    <td class="py-2.5 font-bold ${pnlClass}">${h.pnl >= 0 ? '+' : ''}৳ ${h.pnl.toFixed(2)} (${h.roi_pct.toFixed(2)}%)</td>
                                    <td class="py-2.5 text-slate-400 text-[10px] uppercase">${h.close_reason || h.status}</td>
                                    <td class="py-2.5 text-slate-500 text-[10px]">${h.closed_at || h.created_at}</td>
                                </tr>
                            `;
                        }).join('');
                    }
                }

            } catch (err) {
                console.error("fetchMarketAndPositions Error:", err);
            }
        }

        // Initialize on DOM load
        window.addEventListener('DOMContentLoaded', () => {
            initChart();
            fetchMarketAndPositions();

            // Set high frequency poll for smooth ticks
            setInterval(fetchMarketAndPositions, 2000);
            setInterval(loadChartData, 10000);
        });
    </script>
</body>
</html>
