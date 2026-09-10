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
