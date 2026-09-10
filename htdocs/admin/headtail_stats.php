<?php
/**
 * UNMOOR CLUB - ADMIN HEAD & TAIL GAME P&L ANALYTICS
 */

require_once __DIR__ . "/guard.php";

/* ================= GET SYSTEM ID ================= */
$systemId = (int)$db->query("
    SELECT id FROM users WHERE role='system' LIMIT 1
")->fetchColumn();

$systemProfit = 0;
$systemLoss = 0;
$net = 0;
$rows = [];

if ($systemId) {
    /* SYSTEM PROFIT = system gains (game_win) */
    $profitStmt = $db->prepare("
        SELECT COALESCE(SUM(amount),0)
        FROM coin_history
        WHERE user_id = ?
          AND (type = 'game_win' OR source = 'GAME_WIN')
    ");
    $profitStmt->execute([$systemId]);
    $systemProfit = (float)$profitStmt->fetchColumn();

    /* SYSTEM LOSS = system payouts (game_loss) */
    $lossStmt = $db->prepare("
        SELECT COALESCE(SUM(ABS(amount)),0)
        FROM coin_history
        WHERE user_id = ?
          AND (type = 'game_loss' OR source = 'GAME_LOSS')
    ");
    $lossStmt->execute([$systemId]);
    $systemLoss = (float)$lossStmt->fetchColumn();

    $net = $systemProfit - $systemLoss;

    /* FETCH SYSTEM GAME HISTORY */
    $stmt = $db->prepare("
        SELECT ch.amount, ch.type, ch.source, ch.created_at
        FROM coin_history ch
        WHERE ch.user_id = ?
          AND (ch.type IN ('game_win','game_loss') OR ch.source LIKE '%GAME%')
        ORDER BY ch.id DESC
        LIMIT 100
    ");
    $stmt->execute([$systemId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Head/Tail P&L';
$activeNav = 'headtail_stats.php';
$pageSubtitle = 'House profitability and wagering analytics for the Head & Tail mini-game.';

require_once __DIR__ . "/layout_top.php";
?>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">House Gross Revenue</span>
            <div class="admin-stat-icon" style="color: #22c55e;">📈</div>
        </div>
        <div class="admin-stat-value">🪙 <?= number_format($systemProfit, 2) ?></div>
        <div class="admin-stat-subtext">
            <span>Player wager losses collected</span>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">House Payouts</span>
            <div class="admin-stat-icon" style="color: #ef4444;">📉</div>
        </div>
        <div class="admin-stat-value">🪙 <?= number_format($systemLoss, 2) ?></div>
        <div class="admin-stat-subtext">
            <span>Winnings paid to players</span>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-header">
            <span class="admin-stat-title">Net House Profit</span>
            <div class="admin-stat-icon" style="color: <?= $net >= 0 ? '#facc15' : '#ef4444' ?>;">🎲</div>
        </div>
        <div class="admin-stat-value" style="color: <?= $net >= 0 ? '#22c55e' : '#ef4444' ?>;">
            <?= $net >= 0 ? '+' : '' ?>🪙 <?= number_format($net, 2) ?>
        </div>
        <div class="admin-stat-subtext">
            <span>Overall game margin</span>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">🎲 Recent Wagering Logs</h2>
    </div>

    <?php if (empty($rows)): ?>
        <p style="color: var(--admin-text-muted); text-align: center; padding: 32px 0;">No game wager history found.</p>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Outcome / Type</th>
                        <th>Amount Impact</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <?php if ($r['amount'] >= 0): ?>
                                    <span class="admin-badge admin-badge-success">HOUSE WIN (+<?= number_format($r['amount'], 2) ?>)</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-danger">HOUSE PAYOUT (<?= number_format($r['amount'], 2) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: <?= $r['amount'] >= 0 ? '#22c55e' : '#ef4444' ?>;">
                                    <?= $r['amount'] >= 0 ? '+' : '' ?>🪙 <?= number_format($r['amount'], 2) ?> UC
                                </strong>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--admin-text-muted);">
                                    <?= date("d M Y • h:i A", strtotime($r['created_at'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/layout_bottom.php"; ?>
