<?php
/**
 * UNMOOR CLUB - ADMIN CSV DATA EXPORTER
 */

require_once __DIR__ . "/guard.php";

$download = $_GET['download'] ?? '';

if ($download !== '') {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=unmoor_{$download}_" . date('Y-m-d') . ".csv");
    header("Pragma: no-cache");
    header("Expires: 0");

    $out = fopen("php://output", "w");
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel compatibility

    if ($download === 'users') {
        fputcsv($out, ['ID', 'Name', 'Phone', 'Email', 'Role', 'Status', 'Apply Status', 'Coins', 'Balance', 'Joined At']);
        $stmt = $db->query("SELECT id, name, phone, email, role, status, apply_status, coins, balance, created_at FROM users ORDER BY id DESC");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    } elseif ($download === 'payments') {
        fputcsv($out, ['ID', 'User ID', 'Type', 'Amount', 'Method', 'Source', 'Status', 'Created At']);
        $stmt = $db->query("SELECT id, user_id, type, amount, method, source, status, created_at FROM payments ORDER BY id DESC");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    } elseif ($download === 'coupons') {
        fputcsv($out, ['ID', 'Code', 'Amount', 'Type', 'Status', 'Created At', 'Used By', 'Used At']);
        $stmt = $db->query("SELECT id, code, amount, type, status, created_at, used_by, used_at FROM coupons ORDER BY id DESC");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    } elseif ($download === 'events') {
        fputcsv($out, ['ID', 'Title', 'Description', 'Coin Cost', 'Status', 'Created At']);
        $stmt = $db->query("SELECT id, title, description, coin_cost, status, created_at FROM events ORDER BY id DESC");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    } elseif ($download === 'ledger') {
        fputcsv($out, ['ID', 'User ID', 'Amount', 'Type', 'Source', 'Created At']);
        $stmt = $db->query("SELECT id, user_id, amount, type, source, created_at FROM coin_history ORDER BY id DESC");
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    }

    fclose($out);
    exit;
}

$pageTitle = 'Export Data CSV';
$activeNav = 'export_csv.php';
$pageSubtitle = 'Export verified system tables and audit logs into Excel/CSV spreadsheets.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">📤 Downloadable Database Datasets</h2>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
        
        <div style="background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-md); padding: 18px;">
            <div style="font-size: 24px; margin-bottom: 8px;">👥</div>
            <h3 style="font-size: 16px; color: #ffffff; margin-bottom: 6px;">Members Directory</h3>
            <p style="font-size: 12.5px; color: var(--admin-text-muted); margin-bottom: 16px;">Export all registered users, balances, phone numbers, and account statuses.</p>
            <a href="export_csv.php?download=users" class="admin-btn admin-btn-primary admin-btn-sm">
                📥 Download Users.csv
            </a>
        </div>

        <div style="background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-md); padding: 18px;">
            <div style="font-size: 24px; margin-bottom: 8px;">💳</div>
            <h3 style="font-size: 16px; color: #ffffff; margin-bottom: 6px;">Payments Ledger</h3>
            <p style="font-size: 12.5px; color: var(--admin-text-muted); margin-bottom: 16px;">Export all deposit, apply, donation, and purchase payment records.</p>
            <a href="export_csv.php?download=payments" class="admin-btn admin-btn-primary admin-btn-sm">
                📥 Download Payments.csv
            </a>
        </div>

        <div style="background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-md); padding: 18px;">
            <div style="font-size: 24px; margin-bottom: 8px;">🎟️</div>
            <h3 style="font-size: 16px; color: #ffffff; margin-bottom: 6px;">Prepaid Coupons</h3>
            <p style="font-size: 12.5px; color: var(--admin-text-muted); margin-bottom: 16px;">Export all generated vouchers, redemption statuses, and user timestamps.</p>
            <a href="export_csv.php?download=coupons" class="admin-btn admin-btn-primary admin-btn-sm">
                📥 Download Coupons.csv
            </a>
        </div>

        <div style="background: var(--admin-panel-alt); border: 1px solid var(--admin-border); border-radius: var(--admin-radius-md); padding: 18px;">
            <div style="font-size: 24px; margin-bottom: 8px;">📊</div>
            <h3 style="font-size: 16px; color: #ffffff; margin-bottom: 6px;">Full Coin Ledger</h3>
            <p style="font-size: 12.5px; color: var(--admin-text-muted); margin-bottom: 16px;">Complete audit log of coin movements, game wagers, and transfers.</p>
            <a href="export_csv.php?download=ledger" class="admin-btn admin-btn-primary admin-btn-sm">
                📥 Download Ledger.csv
            </a>
        </div>

    </div>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
