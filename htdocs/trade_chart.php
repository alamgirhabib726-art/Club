<?php
session_start();
require_once __DIR__ . "/db.php";

/* LOGIN REQUIRED */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* FETCH CURRENT PRICE */
$price = $db->query("
    SELECT price FROM trade_market
    ORDER BY id DESC LIMIT 1
")->fetchColumn();

$price = $price ?: 1.0000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>UC Market • Live Chart</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
body{
    margin:0;
    background:#020617;
    color:#e5e7eb;
    font-family:system-ui;
}
.wrap{
    max-width:600px;
    margin:auto;
    padding:16px;
}
.card{
    background:#121826;
    border-radius:22px;
    padding:18px;
    box-shadow:0 25px 60px rgba(0,0,0,.6);
}
h2{text-align:center;margin:0 0 12px;font-weight:900}

.price{
    text-align:center;
    font-size:28px;
    font-weight:900;
    margin-bottom:10px;
}
canvas{
    width:100%;
    height:260px;
    background:#020617;
    border-radius:14px;
}
.note{
    text-align:center;
    margin-top:10px;
    color:#9ca3af;
    font-size:12px;
}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<h2>📈 UC / BDT</h2>

<div class="price" id="price">
    <?= number_format($price,6) ?>
</div>

<canvas id="chart"></canvas>

<div class="note">
    Live simulated market • UC Coin
</div>

</div>
</div>

<script>
const canvas = document.getElementById("chart");
const ctx = canvas.getContext("2d");

canvas.width  = canvas.offsetWidth;
canvas.height = canvas.offsetHeight;

let prices = [];
let lastPrice = <?= (float)$price ?>;

function draw(){
    ctx.clearRect(0,0,canvas.width,canvas.height);

    if(prices.length < 2) return;

    let max = Math.max(...prices);
    let min = Math.min(...prices);

    ctx.beginPath();
    ctx.strokeStyle = "#22c55e";
    ctx.lineWidth = 2;

    prices.forEach((p,i)=>{
        let x = (i/(prices.length-1)) * canvas.width;
        let y = canvas.height - ((p-min)/(max-min+0.00001)) * canvas.height;
        i === 0 ? ctx.moveTo(x,y) : ctx.lineTo(x,y);
    });

    ctx.stroke();
}

async function tick(){
    try{
        const r = await fetch("trade_price_feed.php");
        const j = await r.json();

        lastPrice = parseFloat(j.price);
        document.getElementById("price").innerText = lastPrice.toFixed(6);

        prices.push(lastPrice);
        if(prices.length > 80) prices.shift();

        draw();
    }catch(e){}
}

setInterval(tick, 1000);
</script>

</body>
</html>
