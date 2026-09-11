<?php
/**
 * UNMOOR CLUB - SHARED VIEW COMPONENTS & HELPERS
 */

if (!function_exists('render_page_header')) {
    function render_page_header($title, $subtitleOrBack = '', $backUrl = '') {
        $subtitle = '';
        $effectiveBack = '';

        if (!empty($backUrl)) {
            $subtitle = (string)$subtitleOrBack;
            $effectiveBack = (string)$backUrl;
        } elseif (!empty($subtitleOrBack)) {
            // Check if 2nd parameter is a URL/path
            if (str_starts_with($subtitleOrBack, '/') || str_contains($subtitleOrBack, '.php') || str_starts_with($subtitleOrBack, 'http')) {
                $effectiveBack = (string)$subtitleOrBack;
            } else {
                $subtitle = (string)$subtitleOrBack;
            }
        }
        ?>
        <div class="page-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
            <?php if (!empty($effectiveBack)): ?>
                <a href="<?= htmlspecialchars($effectiveBack) ?>" class="back-btn" title="Go Back" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.1); color: #ffffff; text-decoration: none; font-size: 16px; transition: all 0.2s;">
                    ←
                </a>
            <?php endif; ?>
            <div class="page-header-info" style="flex: 1;">
                <h1 class="page-header-title" style="font-size: 20px; font-weight: 800; color: #ffffff; margin: 0; line-height: 1.2;"><?= htmlspecialchars($title) ?></h1>
                <?php if (!empty($subtitle)): ?>
                    <div class="page-header-subtitle" style="font-size: 12px; color: var(--text-muted, #94a3b8); margin-top: 3px;"><?= htmlspecialchars($subtitle) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return '';
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

if (!function_exists('render_unified_nav')) {
    function render_unified_nav($activeTab = '') {
        $user = null;
        if (isset($_SESSION['user_id'])) {
            global $db;
            if ($db) {
                $uStmt = $db->prepare("SELECT name, phone, coins, balance FROM users WHERE id = ?");
                $uStmt->execute([(int)$_SESSION['user_id']]);
                $user = $uStmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        ?>
        <header style="background: rgba(15, 23, 42, 0.92); border-bottom: 1px solid rgba(255, 255, 255, 0.08); backdrop-filter: blur(12px); position: sticky; top: 0; z-index: 100;">
            <div style="max-width: 1200px; margin: 0 auto; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 16px;">
                <a href="/dashboard.php" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, #eab308, #d97706); display: flex; align-items: center; justify-content: center; font-weight: 900; color: #0f172a; font-size: 16px;">
                        U
                    </div>
                    <span style="font-weight: 900; font-size: 16px; letter-spacing: 0.5px; color: #ffffff;">
                        UNMOOR<span style="color: #eab308;">CLUB</span>
                    </span>
                </a>

                <nav style="display: flex; align-items: center; gap: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <a href="/dashboard.php" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; transition: all 0.2s; color: <?= $activeTab === 'dashboard' ? '#ffffff' : '#94a3b8' ?>; background: <?= $activeTab === 'dashboard' ? 'rgba(255, 255, 255, 0.08)' : 'transparent' ?>;">
                        Dashboard
                    </a>
                    <a href="/trade/" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; transition: all 0.2s; color: <?= $activeTab === 'trade' ? '#60a5fa' : '#94a3b8' ?>; background: <?= $activeTab === 'trade' ? 'rgba(59, 130, 246, 0.15)' : 'transparent' ?>;">
                        📈 Pro Trading
                    </a>
                    <a href="/convert.php" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; transition: all 0.2s; color: <?= $activeTab === 'convert' ? '#34d399' : '#94a3b8' ?>; background: <?= $activeTab === 'convert' ? 'rgba(16, 185, 129, 0.15)' : 'transparent' ?>;">
                        🔄 Convert (1:10)
                    </a>
                    <a href="/deposit.php" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; transition: all 0.2s; color: <?= $activeTab === 'deposit' ? '#ffffff' : '#94a3b8' ?>; background: <?= $activeTab === 'deposit' ? 'rgba(255, 255, 255, 0.08)' : 'transparent' ?>;">
                        💳 Deposit
                    </a>
                    <a href="/history.php" style="text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; transition: all 0.2s; color: <?= $activeTab === 'history' ? '#ffffff' : '#94a3b8' ?>; background: <?= $activeTab === 'history' ? 'rgba(255, 255, 255, 0.08)' : 'transparent' ?>;">
                        📜 History
                    </a>
                </nav>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php if ($user): ?>
                        <div style="display: flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); padding: 4px 10px; border-radius: 20px;">
                            <span style="font-size: 11.5px; font-weight: 800; color: #eab308;">🪙 <?= number_format((float)$user['coins'], 2) ?> UC</span>
                            <span style="color: rgba(255, 255, 255, 0.2);">|</span>
                            <span style="font-size: 11.5px; font-weight: 800; color: #10b981;">৳ <?= number_format((float)$user['balance'], 2) ?></span>
                        </div>
                        <a href="/account/" style="text-decoration: none; width: 32px; height: 32px; border-radius: 50%; background: #334155; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 13px; font-weight: 800;" title="My Account">
                            👤
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="btn btn-sm btn-primary" style="text-decoration: none; padding: 6px 14px; font-size: 13px;">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
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

if (!function_exists('render_unmoor_logo_svg')) {
    function render_unmoor_logo_svg($maxWidth = '260px', $height = 'auto') {
        ?>
        <div class="unmoor-code-logo" style="display: inline-flex; align-items: center; justify-content: center; width: 100%; max-width: <?= htmlspecialchars($maxWidth) ?>; height: <?= htmlspecialchars($height) ?>; margin: 0 auto;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 110" style="width: 100%; height: 100%; display: block;" fill="none">
              <defs>
                <filter id="orbGlowInline" x="-30%" y="-30%" width="160%" height="160%">
                  <feGaussianBlur stdDeviation="4" result="blur" />
                  <feComposite in="SourceGraphic" in2="blur" operator="over" />
                </filter>
                <radialGradient id="orbBgGradInline" cx="40%" cy="45%" r="60%">
                  <stop offset="0%" stop-color="#fffbeb" />
                  <stop offset="18%" stop-color="#fde047" />
                  <stop offset="42%" stop-color="#ec4899" />
                  <stop offset="70%" stop-color="#7c3aed" />
                  <stop offset="90%" stop-color="#2e1065" />
                  <stop offset="100%" stop-color="#0f172a" />
                </radialGradient>
                <linearGradient id="goldRimInline" x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stop-color="#fef08a" />
                  <stop offset="30%" stop-color="#d97706" />
                  <stop offset="60%" stop-color="#fde047" />
                  <stop offset="85%" stop-color="#92400e" />
                  <stop offset="100%" stop-color="#fbbf24" />
                </linearGradient>
                <linearGradient id="starGoldInline" x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stop-color="#ffffff" />
                  <stop offset="40%" stop-color="#fef08a" />
                  <stop offset="100%" stop-color="#eab308" />
                </linearGradient>
                <linearGradient id="bannerBgInline" x1="0%" y1="0%" x2="0%" y2="100%">
                  <stop offset="0%" stop-color="#3b0764" />
                  <stop offset="60%" stop-color="#2e1065" />
                  <stop offset="100%" stop-color="#1e1b4b" />
                </linearGradient>
                <linearGradient id="textGradInline" x1="0%" y1="0%" x2="0%" y2="100%">
                  <stop offset="0%" stop-color="#ffffff" />
                  <stop offset="65%" stop-color="#ffffff" />
                  <stop offset="100%" stop-color="#fef3c7" />
                </linearGradient>
              </defs>
              <g>
                <path d="M 52 20 L 380 20 Q 405 20 405 52 Q 405 84 380 84 L 95 84 Q 75 90 52 86 Z" fill="url(#bannerBgInline)" stroke="url(#goldRimInline)" stroke-width="3" stroke-linejoin="round" />
                <path d="M 68 25 L 376 25 Q 396 25 396 52 Q 396 79 376 79 L 98 79" fill="none" stroke="#4c1d95" stroke-width="1.5" opacity="0.8" />
                <g transform="translate(108, 64)" font-family="'Cinzel', 'Georgia', serif" font-weight="900" font-size="44" letter-spacing="3">
                  <text x="0" y="2" fill="#1e1b4b" stroke="#1e1b4b" stroke-width="7" stroke-linejoin="round">UNMOOR</text>
                  <text x="0" y="1" fill="#78350f" stroke="url(#goldRimInline)" stroke-width="3" stroke-linejoin="round">UNMOOR</text>
                  <text x="0" y="0" fill="url(#textGradInline)">UNMOOR</text>
                </g>
                <circle cx="56" cy="52" r="46" fill="#a855f7" opacity="0.25" filter="url(#orbGlowInline)" />
                <circle cx="56" cy="52" r="43" fill="none" stroke="url(#goldRimInline)" stroke-width="4.5" />
                <circle cx="56" cy="52" r="40" fill="none" stroke="#581c87" stroke-width="1.5" />
                <circle cx="56" cy="52" r="39" fill="url(#orbBgGradInline)" />
                <path d="M 28 35 A 36 36 0 0 1 82 27 A 36 36 0 0 0 35 48 Z" fill="#ffffff" opacity="0.45" />
                <g transform="translate(56, 52)">
                  <circle cx="0" cy="0" r="14" fill="#ffffff" opacity="0.85" filter="url(#orbGlowInline)" />
                  <polygon points="0,-18 3,-4 18,0 4,3 0,18 -3,4 -18,0 -4,-3" fill="url(#starGoldInline)" opacity="0.95" />
                  <polygon points="0,-36 4,-6 0,-1 -4,-6" fill="url(#starGoldInline)" stroke="#92400e" stroke-width="0.5" />
                  <polygon points="0,36 4,6 0,1 -4,6" fill="url(#starGoldInline)" stroke="#92400e" stroke-width="0.5" />
                  <polygon points="-36,0 -6,-4 -1,0 -6,4" fill="url(#starGoldInline)" stroke="#92400e" stroke-width="0.5" />
                  <polygon points="36,0 6,-4 1,0 6,4" fill="url(#starGoldInline)" stroke="#92400e" stroke-width="0.5" />
                  <circle cx="0" cy="0" r="4.5" fill="#ffffff" stroke="#f59e0b" stroke-width="1.5" />
                  <circle cx="0" cy="0" r="2" fill="#fffbeb" />
                </g>
              </g>
            </svg>
        </div>
        <?php
    }
}

