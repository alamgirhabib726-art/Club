<?php
session_start();
require_once __DIR__ . "/../../db.php";

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
.pair{font-weight:900;font-size:18px}
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
</style>
</head>

<body>
<div class="wrapper">

<!-- HEADER -->
<div class="header">
    <div>
        <div class="pair">UC / BDT</div>
        <div class="balance">
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

<!-- TRADE PANEL (UI ONLY) -->
<div class="panel">
    <div class="row">
        <input type="number" placeholder="Amount (BDT)" min="10">
        <select>
            <option>5x</option>
            <option selected>10x</option>
            <option>20x</option>
            <option>50x</option>
            <option>100x</option>
        </select>
    </div>

    <button class="buy" onclick="trade()">📈 BUY</button>
    <button class="sell" onclick="trade()">📉 SELL</button>

    <div class="note">Fee & liquidation will appear after order</div>
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
        });
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
        });
}

/* ================= PLACEHOLDER ================= */
function trade(){
    alert("Order engine will be connected next");
}

loadStatus();
loadMarket();
setInterval(loadStatus,5000);
setInterval(loadMarket,3000);
</script>

</body>
</html>