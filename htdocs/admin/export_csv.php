<?php
require_once "guard.php";
require_once "../db.php";

$type = $_GET['type'] ?? 'users';

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename={$type}.csv");

$out = fopen("php://output", "w");

/* ================= USERS ================= */
if ($type === 'users') {

    fputcsv($out, ['ID','Name','Phone','Status','Balance']);

    $stmt = $db->query("
        SELECT id, name, phone, status, balance
        FROM users
        ORDER BY name ASC
    ");

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
}

/* ================= PAYMENTS ================= */
elseif ($type === 'payments') {

    fputcsv($out, ['ID','User ID','Type','Amount','Status','Created']);

    $stmt = $db->query("
        SELECT id, user_id, type, amount, status, created_at
        FROM payments
        ORDER BY type ASC
    ");

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
}

/* ================= DONATIONS ================= */
elseif ($type === 'donations') {

    fputcsv($out, ['ID','User ID','Amount','Method','Created']);

    $stmt = $db->query("
        SELECT id, user_id, amount, method, created_at
        FROM donations
        ORDER BY user_id ASC
    ");

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
}

/* ================= EVENTS ================= */
elseif ($type === 'events') {

    fputcsv($out, ['ID','Title','Description','Coin Cost','Created']);

    $stmt = $db->query("
        SELECT id, title, description, coin_cost, created_at
        FROM events
        ORDER BY title ASC
    ");

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
}

/* ================= EVENT PARTICIPANTS ================= */
elseif ($type === 'event_participants') {

    fputcsv($out, ['ID','Event ID','User ID','Coins','Joined']);

    $stmt = $db->query("
        SELECT id, event_id, user_id, coins, created_at
        FROM event_participants
        ORDER BY user_id ASC
    ");

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
}

/* ================= COIN HISTORY ================= */
elseif ($type === 'coin_history') {

    fputcsv($out, ['ID','User ID','Amount','Type','Reference','Created']);

    $stmt = $db->query("
        SELECT id, user_id, amount, type, reference, created_at
        FROM coin_history
        ORDER BY type ASC
    ");

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
}

fclose($out);
exit;
