<?php
require_once __DIR__ . "/../../db.php";
require_once __DIR__ . "/../engine/market_engine.php";
header("Content-Type: application/json");

$out = [
    "db" => false,
    "status" => "error"
];

try {
    $out["db"] = true;
    $state = MarketEngine::getMarketState($db);
    $ticks = (int)$db->query("SELECT COUNT(*) FROM market_ticks")->fetchColumn();
    $out["market_state"] = $state;
    $out["total_ticks"] = $ticks;
    $out["status"] = "live";
} catch (Exception $e) {
    $out["error"] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT);
