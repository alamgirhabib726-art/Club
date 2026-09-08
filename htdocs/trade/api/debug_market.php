<?php
require_once __DIR__ . "/../../db.php";
header("Content-Type: application/json");

$out = [
    "db" => false,
    "rows" => 0,
    "last_price" => null,
    "status" => "error"
];

try {
    $out["db"] = true;

    $rows = (int)$db->query("SELECT COUNT(*) FROM uc_market")->fetchColumn();
    $out["rows"] = $rows;

    if ($rows === 0) {
        $out["status"] = "empty";
    } elseif ($rows === 1) {
        $out["status"] = "warming";
    } else {
        $out["status"] = "live";
    }

    $last = $db->query("
        SELECT price, created_at 
        FROM uc_market 
        ORDER BY id DESC 
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($last) {
        $out["last_price"] = $last;
    }

} catch (Exception $e) {
    $out["error"] = $e->getMessage();
}

echo json_encode($out);
