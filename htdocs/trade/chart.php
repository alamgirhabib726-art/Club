<?php
session_start();
require_once __DIR__ . "/../db.php";

/* ===== LOGIN GUARD ===== */
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

/* ===== FETCH USER COINS ===== */
$stmt = $db->prepare("SELECT coins FROM users WHERE id=? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$userCoins = (float)$stmt->fetchColumn();

/* UC → BDT */
$UC_RATE = 1;
$userBalanceBDT = $userCoins * $UC_RATE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>UC / BDT Trading</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Lightweight Charts -->
<script src="https://unpkg.com/lightweight-charts/dist/lightweight-charts.standalone.production.js"></script>

<style>
body{
    margin:0;
    background:#020617;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrapper{
    max-width:480px;
    margin:auto;
    padding:14px;
}
.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
}
.pair{font-weight:900;font-size:18px;display:flex;align-items:center;gap:8px;}
.pair a{color:#9ca3af;text-decoration:none;font-size:16px;}
.balance{font-size:12px;color:#9ca3af}
.price{font-weight:900;color:#22c55e}

#marketStatus{
    display:none;
    margin-bottom:8px;
    padding:8px 12px;
    border-radius:12px;
    font-size:13px;
    font-weight:800;
}

.chart-box{
    background:#0b1220;
    border-radius:18px;
    padding:10px;
}
#chart{height:280px}

.panel{
    margin-top:14px;
    background:#121826;
    border-radius:18px;
    padding:14px;
}
.row{
    display:flex;
    gap:10px;
    margin-bottom:10px;
}
.row input,.row select{
    flex:1;
    padding:12px;
    border-radius:12px;
    border:1px solid #1f2937;
    background:#020617;
    color:#fff;
    font-size:14px;
}
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:16px;
    font-weight:900;
    cursor:pointer;
}
.buy{
    background:linear-gradient(135deg,#22c55e,#16a34a);
    color:#022c22;
    margin-bottom:8px;
}
.sell{
    background:linear-gradient(135deg,#ef4444,#dc2626);
    color:#fff;
}
.note{
    margin-top:8px;
    font-size:12px;
    color:#9ca3af;
    text-align:center;
}
.feedback{
    margin-top:10px;
    padding:10px;
    border-radius:12px;
    font-size:13px;
    font-weight:700;
    text-align:center;
    display:none;
}
.positions-box{
    margin-top:14px;
    background:#121826;
    border-radius:18px;
    padding:14px;
}
.positions-title{
    font-size:14px;
    font-weight:800;
    margin-bottom:10px;
    color:#e5e7eb;
}
.pos-item{
    background:#0b1220;
    border-radius:12px;
    padding:12px;
    margin-bottom:8px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-left:4px solid #22c55e;
}
.pos-item.short{
    border-left-color:#ef4444;
}
.pos-info{
    font-size:12px;
    line-height:1.5;
}
.pos-pnl{
    font-weight:800;
    font-size:14px;
}
.pos-close-btn{
    width:auto;
    padding:6px 14px;
    background:#ef4444;
    color:#fff;
    border-radius:8px;
    font-size:12px;
    font-weight:800;
    margin-top:4px;
}
</style>
</head>

<body>
<div class="wrapper">

<!-- HEADER -->
<div class="header">
    <div>
        <div class="pair"><a href="/dashboard.php">←</a> UC / BDT</div>
        <div class="balance" id="userBalanceText">
            Balance: ৳ <?= number_format($userBalanceBDT,2) ?>
        </div>
    </div>
    <div class="price" id="livePrice">৳ --</div>
</div>

<!-- MARKET STATUS -->
<div id="marketStatus"></div>

<!-- CHART -->
<div class="chart-box">
    <div id="chart"></div>
</div>

<!-- TRADE PANEL -->
<div class="panel">
    <div class="row">
        <input type="number" id="tradeAmount" placeholder="Amount (BDT / Coins)" min="10" value="50">
        <select id="tradeLeverage">
            <option value="5">5x</option>
            <option value="10" selected>10x</option>
            <option value="20">20x</option>
            <option value="50">50x</option>
            <option value="100">100x</option>
        </select>
    </div>

    <button class="buy" onclick="executeTrade('long')">📈 BUY (LONG)</button>
    <button class="sell" onclick="executeTrade('short')">📉 SELL (SHORT)</button>

    <div id="tradeFeedback" class="feedback"></div>
    <div class="note">Fee & liquidation will appear after order</div>
</div>

<!-- OPEN POSITIONS -->
<div class="positions-box" id="positionsBox" style="display:none;">
    <div class="positions-title">📊 Open Positions</div>
    <div id="positionsList"></div>
</div>

</div>

<script>
/* ================= CHART INIT ================= */
const chart = LightweightCharts.createChart(
    document.getElementById('chart'),
    {
        layout:{background:{color:'#0b1220'},textColor:'#e5e7eb'},
        grid:{
            vertLines:{color:'#1f2937'},
            horzLines:{color:'#1f2937'}
        },
        rightPriceScale:{borderColor:'#1f2937'},
        timeScale:{borderColor:'#1f2937',timeVisible:true}
    }
);

const series = chart.addCandlestickSeries({
    upColor:'#22c55e',
    downColor:'#ef4444',
    borderUpColor:'#22c55e',
    borderDownColor:'#ef4444',
    wickUpColor:'#22c55e',
    wickDownColor:'#ef4444'
});

/* ================= MARKET STATUS ================= */
function loadStatus(){
    fetch('/trade/api/debug_market.php')
        .then(r=>r.json())
        .then(d=>{
            const box=document.getElementById('marketStatus');
            box.style.display='block';

            if(!d || !d.status){
                box.style.background='#7c2d12';
                box.style.color='#fff';
                box.innerText='❌ Market error';
                return;
            }
            if(d.status==='empty'){
                box.style.background='#78350f';
                box.style.color='#fde68a';
                box.innerText='🟡 Market not started';
            }
            else if(d.status==='warming'){
                box.style.background='#1e3a8a';
                box.style.color='#bfdbfe';
                box.innerText='🔵 Market warming up';
            }
            else{
                box.style.background='#064e3b';
                box.style.color='#a7f3d0';
                box.innerText='🟢 Live market';
            }
        }).catch(()=>{});
}

/* ================= LOAD CHART ================= */
function loadMarket(){
    fetch('/trade/api/chart_data.php')
        .then(r=>r.json())
        .then(data=>{
            if(!data || data.length===0) return;
            series.setData(data);
            const last=data[data.length-1];
            document.getElementById('livePrice').innerText =
                "৳ "+last.close.toFixed(2);
        }).catch(()=>{});
}

/* ================= LOAD POSITIONS ================= */
function loadPositions(){
    fetch('/trade/api/positions_api.php')
        .then(r=>r.json())
        .then(data=>{
            const box = document.getElementById('positionsBox');
            const list = document.getElementById('positionsList');
            if(!data || data.length === 0){
                box.style.display = 'none';
                list.innerHTML = '';
                return;
            }
            box.style.display = 'block';
            let html = '';
            data.forEach(p => {
                const pnlClass = p.pnl >= 0 ? '#22c55e' : '#ef4444';
                const sideColor = p.side === 'long' ? '🟢 LONG' : '🔴 SHORT';
                html += `
                <div class="pos-item ${p.side}">
                    <div class="pos-info">
                        <div><b>${sideColor}</b> ${p.leverage}x | Entry: ৳${p.entry}</div>
                        <div style="color:#9ca3af;">Size: ${p.size} | Stop: ৳${p.stop}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="pos-pnl" style="color:${pnlClass}">
                            ${p.pnl >= 0 ? '+' : ''}${p.pnl} (${p.pnl_pct}%)
                        </div>
                        <button class="pos-close-btn" onclick="closeTrade(${p.id})">Close</button>
                    </div>
                </div>`;
            });
            list.innerHTML = html;
        }).catch(()=>{});
}

/* ================= EXECUTE TRADE ================= */
function executeTrade(side){
    const amt = parseFloat(document.getElementById('tradeAmount').value) || 0;
    const lev = parseInt(document.getElementById('tradeLeverage').value) || 10;
    const fb = document.getElementById('tradeFeedback');

    if(amt < 10){
        fb.style.display = 'block';
        fb.style.background = '#7f1d1d';
        fb.style.color = '#fecaca';
        fb.innerText = '⚠️ Minimum margin is 10 BDT / Coins';
        return;
    }

    const formData = new FormData();
    formData.append('side', side);
    formData.append('margin', amt);
    formData.append('leverage', lev);

    fetch('/trade/api/open_position.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text())
    .then(txt => {
        fb.style.display = 'block';
        if(txt.includes('INSUFFICIENT BALANCE')){
            fb.style.background = '#7f1d1d';
            fb.style.color = '#fecaca';
            fb.innerText = '❌ Insufficient coin balance!';
        } else if(txt.includes('INVALID') || txt.includes('ERROR')){
            fb.style.background = '#7f1d1d';
            fb.style.color = '#fecaca';
            fb.innerText = '❌ ' + txt;
        } else {
            fb.style.background = '#064e3b';
            fb.style.color = '#a7f3d0';
            fb.innerText = '✅ Position opened successfully!';
            loadPositions();
            setTimeout(() => fb.style.display = 'none', 4000);
        }
    }).catch(e => {
        fb.style.display = 'block';
        fb.style.background = '#7f1d1d';
        fb.style.color = '#fecaca';
        fb.innerText = '❌ Request failed';
    });
}

/* ================= CLOSE TRADE ================= */
function closeTrade(pid){
    if(!confirm('Close this position?')) return;
    const formData = new FormData();
    formData.append('position_id', pid);

    fetch('/trade/api/close_position.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text())
    .then(txt => {
        loadPositions();
        const fb = document.getElementById('tradeFeedback');
        fb.style.display = 'block';
        fb.style.background = '#064e3b';
        fb.style.color = '#a7f3d0';
        fb.innerText = '✅ Position closed';
        setTimeout(() => fb.style.display = 'none', 3000);
    }).catch(()=>{});
}

loadStatus();
loadMarket();
loadPositions();
setInterval(loadStatus, 5000);
setInterval(loadMarket, 3000);
setInterval(loadPositions, 2000);
</script>

</body>
</html>