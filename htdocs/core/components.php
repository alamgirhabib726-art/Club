<?php
/**
 * UNMOOR CLUB - SHARED VIEW COMPONENTS & HELPERS
 */

if (!function_exists('render_page_header')) {
    function render_page_header($title, $subtitle = '', $backUrl = '') {
        ?>
        <div class="page-header">
            <?php if (!empty($backUrl)): ?>
                <a href="<?= htmlspecialchars($backUrl) ?>" class="back-btn" title="Go Back">
                    ←
                </a>
            <?php endif; ?>
            <div class="page-header-info">
                <h1 class="page-header-title"><?= htmlspecialchars($title) ?></h1>
                <?php if (!empty($subtitle)): ?>
                    <div class="page-header-subtitle"><?= htmlspecialchars($subtitle) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('render_alert')) {
    function render_alert($msg, $type = 'success') {
        if (empty($msg)) return;
        $class = 'alert-success';
        $icon = '✅';
        if ($type === 'error' || $type === 'danger') {
            $class = 'alert-danger';
            $icon = '❌';
        } elseif ($type === 'warning') {
            $class = 'alert-warning';
            $icon = '⚠️';
        } elseif ($type === 'info') {
            $class = 'alert-info';
            $icon = 'ℹ️';
        }
        ?>
        <div class="alert <?= $class ?>">
            <span><?= $icon ?></span>
            <div><?= htmlspecialchars($msg) ?></div>
        </div>
        <?php
    }
}

if (!function_exists('render_empty_state')) {
    function render_empty_state($title, $desc = '', $icon = '📭', $actionUrl = '', $actionLabel = '') {
        ?>
        <div class="empty-state">
            <span class="empty-state-icon"><?= $icon ?></span>
            <div class="empty-state-title"><?= htmlspecialchars($title) ?></div>
            <?php if (!empty($desc)): ?>
                <div class="empty-state-desc"><?= htmlspecialchars($desc) ?></div>
            <?php endif; ?>
            <?php if (!empty($actionUrl) && !empty($actionLabel)): ?>
                <div style="margin-top: 14px;">
                    <a href="<?= htmlspecialchars($actionUrl) ?>" class="btn btn-sm btn-secondary">
                        <?= htmlspecialchars($actionLabel) ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('render_badge')) {
    function render_badge($status) {
        $statusLower = strtolower((string)$status);
        $badgeClass = 'badge-pending';
        if (in_array($statusLower, ['active', 'approved', 'success', 'completed', 'paid'], true)) {
            $badgeClass = 'badge-success';
        } elseif (in_array($statusLower, ['rejected', 'banned', 'failed', 'cancelled', 'deleted'], true)) {
            $badgeClass = 'badge-danger';
        } elseif (in_array($statusLower, ['vip', 'premium', 'admin'], true)) {
            $badgeClass = 'badge-vip';
        }
        ?>
        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(strtoupper($status)) ?></span>
        <?php
    }
}

if (!function_exists('render_balance_card')) {
    function render_balance_card($balances, $showActions = false) {
        $avail = (float)($balances['coins'] ?? 0);
        $locked = (float)($balances['locked_coins'] ?? 0);
        $total = $avail + $locked;
        ?>
        <div class="card balance-card-wrap" style="padding: 16px; margin-bottom: 16px; background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(250, 204, 21, 0.25); border-radius: var(--radius-lg); position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <div>
                    <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px;">
                        Available Balance
                    </div>
                    <div style="font-size: 24px; font-weight: 900; color: #ffffff; margin-top: 2px; display: flex; align-items: center; gap: 6px;">
                        <span style="color: var(--accent-gold);">🪙</span>
                        <span><?= number_format($avail, 2) ?></span>
                        <span style="font-size: 12px; color: var(--accent-gold); font-weight: 700; background: rgba(250,204,21,0.12); padding: 2px 8px; border-radius: 999px;">UC</span>
                    </div>
                </div>

                <div style="text-align: right;">
                    <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px;">
                        Total Balance
                    </div>
                    <div style="font-size: 15px; font-weight: 800; color: var(--text-dim); margin-top: 4px;">
                        🪙 <?= number_format($total, 2) ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px; padding-top: 10px; border-top: 1px dashed rgba(255, 255, 255, 0.08); font-size: 12px;">
                <div style="flex: 1; display: flex; align-items: center; gap: 6px; color: var(--text-muted);">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #22c55e;"></span>
                    <span>Ready to Spend: <strong style="color: #ffffff;"><?= number_format($avail, 2) ?></strong></span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px; color: <?= $locked > 0 ? '#f59e0b' : 'var(--text-muted)' ?>;">
                    <span>🔒 Locked:</span>
                    <strong style="color: <?= $locked > 0 ? '#fbbf24' : '#94a3b8' ?>;"><?= number_format($locked, 2) ?></strong>
                </div>
            </div>

            <?php if ($showActions): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px;">
                    <a href="deposit.php" class="btn btn-gold btn-sm" style="text-align: center; text-decoration: none; padding: 10px;">
                        💳 Add Deposit
                    </a>
                    <a href="transfer.php" class="btn btn-secondary btn-sm" style="text-align: center; text-decoration: none; padding: 10px;">
                        ↗️ Transfer
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('render_support_widget')) {
    function render_support_widget() {
        ?>
        <div class="support-wrap">
            <input type="checkbox" id="supportToggle">
            <div class="support-menu">
                <a href="https://wa.me/8801788674353" target="_blank" rel="noopener">📲 WhatsApp Support</a>
                <a href="https://t.me/Angkur_TouFik" target="_blank" rel="noopener">✈️ Telegram Support</a>
            </div>
            <label for="supportToggle" class="support-main-btn" title="Contact Support">💬</label>
        </div>
        <?php
    }
}
