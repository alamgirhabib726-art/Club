<?php
/**
 * UNMOOR CLUB - CURRENCY CONVERSION & WALLET MANAGER
 * 1 Club UC = 10 BDT (1.345% Liquidity Pool Fee)
 * Main BDT <-> Trading BDT Allocations
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

$activeTab = $_GET['tab'] ?? 'convert';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Currency Conversion & Wallet — Unmoor Club</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .conv-card {
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            backdrop-filter: blur(12px);
            padding: 24px;
            margin-bottom: 24px;
        }
        .balance-pill {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 10px 18px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: #3b82f6;
            color: #ffffff;
        }
        .quote-box {
            background: rgba(30, 41, 59, 0.7);
            border-radius: 10px;
            padding: 14px;
            font-size: 0.9rem;
            margin-top: 14px;
            border-left: 3px solid #3b82f6;
        }
        .badge-fixed {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .badge-market {
            background: rgba(168, 85, 247, 0.15);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <?php render_unified_nav('dashboard'); ?>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-3">
                    <i class="fa-solid fa-arrows-rotate text-blue-500"></i>
                    Currency Conversion & Wallets
                </h1>
                <p class="text-sm text-slate-400 mt-1">Convert Club UC to BDT at fixed rate (1 Club UC = 10 BDT) or manage Trading margin.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="/trade/" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold text-sm px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-lg shadow-blue-500/20">
                    <i class="fa-solid fa-chart-line"></i>
                    Open Trading Terminal
                </a>
            </div>
        </div>

        <!-- Asset Distinction Notice -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4 mb-6 text-xs text-slate-300 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-blue-500/10 text-blue-400 rounded-lg text-lg"><i class="fa-solid fa-coins"></i></div>
                <div>
                    <div class="font-bold text-white flex items-center gap-2">
                        1. Club UC (Standard Club Currency)
                        <span class="badge-fixed">Fixed 1 UC = 10 BDT</span>
                    </div>
                    <p class="text-slate-400 mt-1">Used for normal club activities, purchases, mini games, and tasks. Direct conversion to BDT carries a transparent 1.345% fee routed to the Liquidity Pool.</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="p-2 bg-purple-500/10 text-purple-400 rounded-lg text-lg"><i class="fa-solid fa-arrow-trend-up"></i></div>
                <div>
                    <div class="font-bold text-white flex items-center gap-2">
                        2. Trading UC (tUC Exchange Asset)
                        <span class="badge-market">Market Traded UC/BDT</span>
                    </div>
                    <p class="text-slate-400 mt-1">Traded on the live order book exchange and futures market. Market price fluctuates based on supply, demand, and liquidity.</p>
                </div>
            </div>
        </div>

        <!-- Wallet Snapshot Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="balance-pill">
                <div>
                    <div class="text-xs text-slate-400 font-medium">Club UC Balance</div>
                    <div class="text-xl font-bold text-amber-400 mt-1">🪙 <span id="club-uc-val"><?= number_format($balances['club_wallet']['club_uc'], 4) ?></span> UC</div>
                    <div class="text-xs text-slate-500 mt-0.5">≈ ৳ <?= number_format($balances['club_wallet']['approx_bdt_value'], 2) ?> BDT</div>
                </div>
                <div class="text-2xl text-amber-500/30"><i class="fa-solid fa-coins"></i></div>
            </div>

            <div class="balance-pill">
                <div>
                    <div class="text-xs text-slate-400 font-medium">Main BDT Balance</div>
                    <div class="text-xl font-bold text-emerald-400 mt-1">৳ <span id="main-bdt-val"><?= number_format($balances['main_wallet']['bdt_balance'], 2) ?></span></div>
                    <div class="text-xs text-slate-500 mt-0.5">Available for transfer / withdrawal</div>
                </div>
                <div class="text-2xl text-emerald-500/30"><i class="fa-solid fa-wallet"></i></div>
            </div>

            <div class="balance-pill">
                <div>
                    <div class="text-xs text-slate-400 font-medium">Trading BDT Wallet</div>
                    <div class="text-xl font-bold text-blue-400 mt-1">৳ <span id="trading-bdt-val"><?= number_format($balances['trading_wallet']['bdt_balance'], 2) ?></span></div>
                    <div class="text-xs text-slate-500 mt-0.5">Avail. Margin: ৳ <?= number_format($balances['trading_wallet']['available_margin'], 2) ?></div>
                </div>
                <div class="text-2xl text-blue-500/30"><i class="fa-solid fa-chart-pie"></i></div>
            </div>
        </div>

        <!-- Tab Controls -->
        <div class="flex items-center gap-2 mb-4 bg-slate-900/60 p-1.5 rounded-xl border border-slate-800 w-fit">
            <button class="tab-btn active" id="tab-btn-uc2bdt" onclick="switchTab('uc2bdt')">
                <i class="fa-solid fa-coins text-amber-400 mr-1.5"></i> Club UC ➔ BDT
            </button>
            <button class="tab-btn" id="tab-btn-bdt2uc" onclick="switchTab('bdt2uc')">
                <i class="fa-solid fa-bangladeshi-taka-sign text-emerald-400 mr-1.5"></i> BDT ➔ Club UC
            </button>
            <button class="tab-btn" id="tab-btn-transfer" onclick="switchTab('transfer')">
                <i class="fa-solid fa-right-left text-blue-400 mr-1.5"></i> Main ⇄ Trading BDT
            </button>
        </div>

        <!-- Section 1: Club UC -> BDT -->
        <div id="tab-content-uc2bdt" class="conv-card">
            <h2 class="text-lg font-bold text-white mb-1">Convert Club UC to Main BDT</h2>
            <p class="text-xs text-slate-400 mb-4">Fixed rate: <span class="text-emerald-400 font-semibold">1 Club UC = 10 BDT</span>. Deducts 1.345% liquidity pool fee.</p>

            <form id="form-uc2bdt" onsubmit="handleConvert(event, 'club_uc_to_bdt')">
                <div class="mb-4">
                    <label class="block text-xs text-slate-400 font-semibold mb-1">Club UC Amount to Convert</label>
                    <div class="relative">
                        <input type="number" step="0.0001" min="0.1" id="input-uc2bdt" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-3 text-white text-base focus:border-blue-500 focus:outline-none" placeholder="0.0000" oninput="updateQuote('club_uc_to_bdt')" required>
                        <button type="button" class="absolute right-3 top-3 text-xs bg-slate-800 hover:bg-slate-700 text-blue-400 font-semibold px-2 py-1 rounded" onclick="setMaxUc()">MAX</button>
                    </div>
                </div>

                <div id="quote-uc2bdt" class="quote-box hidden">
                    <div class="flex justify-between text-slate-400 mb-1">
                        <span>Gross BDT:</span>
                        <span id="q-gross-bdt" class="text-slate-200 font-medium">৳ 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-400 mb-1">
                        <span>Liquidity Pool Fee (1.345%):</span>
                        <span id="q-fee-bdt" class="text-amber-400 font-medium">৳ 0.00</span>
                    </div>
                    <div class="flex justify-between text-white font-bold pt-2 border-t border-slate-700/50">
                        <span>Net BDT Received:</span>
                        <span id="q-net-bdt" class="text-emerald-400 text-base">৳ 0.00</span>
                    </div>
                </div>

                <button type="submit" id="btn-submit-uc2bdt" class="w-full mt-5 bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-lg flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    Convert to BDT
                </button>
            </form>
        </div>

        <!-- Section 2: BDT -> Club UC -->
        <div id="tab-content-bdt2uc" class="conv-card hidden">
            <h2 class="text-lg font-bold text-white mb-1">Convert Main BDT to Club UC</h2>
            <p class="text-xs text-slate-400 mb-4">Fixed rate: <span class="text-emerald-400 font-semibold">10 BDT = 1 Club UC</span>. Deducts 1.345% liquidity pool fee.</p>

            <form id="form-bdt2uc" onsubmit="handleConvert(event, 'bdt_to_club_uc')">
                <div class="mb-4">
                    <label class="block text-xs text-slate-400 font-semibold mb-1">BDT Amount to Convert</label>
                    <div class="relative">
                        <input type="number" step="0.01" min="1" id="input-bdt2uc" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-3 text-white text-base focus:border-blue-500 focus:outline-none" placeholder="0.00" oninput="updateQuote('bdt_to_club_uc')" required>
                        <button type="button" class="absolute right-3 top-3 text-xs bg-slate-800 hover:bg-slate-700 text-blue-400 font-semibold px-2 py-1 rounded" onclick="setMaxBdt()">MAX</button>
                    </div>
                </div>

                <div id="quote-bdt2uc" class="quote-box hidden">
                    <div class="flex justify-between text-slate-400 mb-1">
                        <span>Gross Club UC:</span>
                        <span id="q-gross-uc" class="text-slate-200 font-medium">0.0000 UC</span>
                    </div>
                    <div class="flex justify-between text-slate-400 mb-1">
                        <span>Liquidity Pool Fee (1.345%):</span>
                        <span id="q-fee-uc" class="text-amber-400 font-medium">৳ 0.00</span>
                    </div>
                    <div class="flex justify-between text-white font-bold pt-2 border-t border-slate-700/50">
                        <span>Net Club UC Received:</span>
                        <span id="q-net-uc" class="text-emerald-400 text-base">0.0000 UC</span>
                    </div>
                </div>

                <button type="submit" id="btn-submit-bdt2uc" class="w-full mt-5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 rounded-lg flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    Convert to Club UC
                </button>
            </form>
        </div>

        <!-- Section 3: Main BDT <-> Trading BDT -->
        <div id="tab-content-transfer" class="conv-card hidden">
            <h2 class="text-lg font-bold text-white mb-1">Transfer BDT Between Wallets</h2>
            <p class="text-xs text-slate-400 mb-4">Allocate funds into your Trading Margin Wallet or withdraw profits back to Main Balance (0% fee).</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Main -> Trading -->
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800">
                    <h3 class="font-semibold text-sm text-blue-400 mb-1"><i class="fa-solid fa-arrow-down mr-1"></i> Deposit to Trading Wallet</h3>
                    <p class="text-xs text-slate-400 mb-3">Moves BDT from Main Balance to Trading Margin Wallet.</p>
                    <form onsubmit="handleTransfer(event, 'main_to_trading')">
                        <input type="number" step="0.01" min="1" id="transfer-main-in" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm mb-3 focus:border-blue-500 focus:outline-none" placeholder="BDT Amount" required>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs py-2.5 rounded-lg">Deposit to Trading</button>
                    </form>
                </div>

                <!-- Trading -> Main -->
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800">
                    <h3 class="font-semibold text-sm text-emerald-400 mb-1"><i class="fa-solid fa-arrow-up mr-1"></i> Withdraw to Main Balance</h3>
                    <p class="text-xs text-slate-400 mb-3">Moves unlocked available margin back to Main Balance.</p>
                    <form onsubmit="handleTransfer(event, 'trading_to_main')">
                        <input type="number" step="0.01" min="1" id="transfer-trading-out" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm mb-3 focus:border-emerald-500 focus:outline-none" placeholder="BDT Amount" required>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs py-2.5 rounded-lg">Withdraw to Main</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Conversion History Table -->
        <div class="conv-card">
            <h3 class="text-base font-bold text-white mb-3 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                Recent Conversion History
            </h3>
            <?php if (empty($conversions)): ?>
                <div class="text-center py-6 text-xs text-slate-500">No conversions recorded yet.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-800">
                                <th class="pb-2">ID</th>
                                <th class="pb-2">Conversion</th>
                                <th class="pb-2">Gross Amount</th>
                                <th class="pb-2">Pool Fee (1.345%)</th>
                                <th class="pb-2">Net Received</th>
                                <th class="pb-2">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($conversions as $c): ?>
                                <tr>
                                    <td class="py-2.5 text-slate-500">#<?= $c['id'] ?></td>
                                    <td class="py-2.5 font-semibold text-slate-200">
                                        <?= $c['from_asset'] === 'Club_UC' ? '🪙 Club UC ➔ ৳ BDT' : '৳ BDT ➔ 🪙 Club UC' ?>
                                    </td>
                                    <td class="py-2.5 text-slate-300">
                                        <?= $c['from_asset'] === 'Club_UC' ? number_format($c['from_amount'], 4) . ' UC' : '৳ ' . number_format($c['from_amount'], 2) ?>
                                    </td>
                                    <td class="py-2.5 text-amber-400">৳ <?= number_format($c['fee_amount'], 2) ?></td>
                                    <td class="py-2.5 text-emerald-400 font-bold">
                                        <?= $c['to_asset'] === 'BDT' ? '৳ ' . number_format($c['to_amount_net'], 2) : number_format($c['to_amount_net'], 4) . ' UC' ?>
                                    </td>
                                    <td class="py-2.5 text-slate-500"><?= date('M d, H:i', strtotime($c['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const userClubUc = <?= (float)$balances['club_wallet']['club_uc'] ?>;
        const userMainBdt = <?= (float)$balances['main_wallet']['bdt_balance'] ?>;
        const userTradingBdt = <?= (float)$balances['trading_wallet']['bdt_balance'] ?>;
        const userAvailMargin = <?= (float)$balances['trading_wallet']['available_margin'] ?>;

        function switchTab(tab) {
            ['uc2bdt', 'bdt2uc', 'transfer'].forEach(t => {
                document.getElementById('tab-btn-' + t).classList.toggle('active', t === tab);
                document.getElementById('tab-content-' + t).classList.toggle('hidden', t !== tab);
            });
        }

        function setMaxUc() {
            document.getElementById('input-uc2bdt').value = userClubUc;
            updateQuote('club_uc_to_bdt');
        }

        function setMaxBdt() {
            document.getElementById('input-bdt2uc').value = userMainBdt;
            updateQuote('bdt_to_club_uc');
        }

        async function updateQuote(type) {
            if (type === 'club_uc_to_bdt') {
                const amt = parseFloat(document.getElementById('input-uc2bdt').value) || 0;
                const box = document.getElementById('quote-uc2bdt');
                if (amt <= 0) {
                    box.classList.add('hidden');
                    return;
                }
                const gross = amt * 10.0;
                const fee = gross * 0.01345;
                const net = gross - fee;

                document.getElementById('q-gross-bdt').textContent = '৳ ' + gross.toFixed(2);
                document.getElementById('q-fee-bdt').textContent = '৳ ' + fee.toFixed(2);
                document.getElementById('q-net-bdt').textContent = '৳ ' + net.toFixed(2);
                box.classList.remove('hidden');
            } else {
                const amt = parseFloat(document.getElementById('input-bdt2uc').value) || 0;
                const box = document.getElementById('quote-bdt2uc');
                if (amt <= 0) {
                    box.classList.add('hidden');
                    return;
                }
                const grossUc = amt / 10.0;
                const feeBdt = amt * 0.01345;
                const feeUc = grossUc * 0.01345;
                const netUc = grossUc - feeUc;

                document.getElementById('q-gross-uc').textContent = grossUc.toFixed(4) + ' UC';
                document.getElementById('q-fee-uc').textContent = '৳ ' + feeBdt.toFixed(2) + ' (' + feeUc.toFixed(4) + ' UC)';
                document.getElementById('q-net-uc').textContent = netUc.toFixed(4) + ' UC';
                box.classList.remove('hidden');
            }
        }

        async function handleConvert(e, action) {
            e.preventDefault();
            const amtInput = (action === 'club_uc_to_bdt') ? document.getElementById('input-uc2bdt') : document.getElementById('input-bdt2uc');
            const amt = parseFloat(amtInput.value);

            if (!amt || amt <= 0) {
                alert('Please enter a valid conversion amount.');
                return;
            }

            const formData = new FormData();
            formData.append('action', action);
            formData.append('amount', amt);

            try {
                const res = await fetch('/trade/api/convert.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.error || 'Conversion failed');
                }
            } catch (err) {
                alert('Connection error: ' + err.message);
            }
        }

        async function handleTransfer(e, direction) {
            e.preventDefault();
            const input = (direction === 'main_to_trading') ? document.getElementById('transfer-main-in') : document.getElementById('transfer-trading-out');
            const amt = parseFloat(input.value);

            if (!amt || amt <= 0) {
                alert('Please enter a valid transfer amount.');
                return;
            }

            const formData = new FormData();
            formData.append('action', direction);
            formData.append('amount', amt);

            try {
                const res = await fetch('/trade/api/convert.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.error || 'Transfer failed');
                }
            } catch (err) {
                alert('Connection error: ' + err.message);
            }
        }
    </script>
</body>
</html>
