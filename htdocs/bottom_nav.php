<?php
/**
 * UNMOOR CLUB - SHARED BOTTOM NAVIGATION
 * Variables: $currentPage = 'home' | 'buy' | 'chat' | 'headtail' | 'account'
 */

if (!isset($currentPage)) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, 'dashboard.php') !== false) {
        $currentPage = 'home';
    } elseif (strpos($script, 'purchase.php') !== false || strpos($script, 'buy.php') !== false || strpos($script, 'purchase_pay.php') !== false) {
        $currentPage = 'buy';
    } elseif (strpos($script, '/chat/') !== false) {
        $currentPage = 'chat';
    } elseif (strpos($script, 'headtail.php') !== false) {
        $currentPage = 'headtail';
    } elseif (strpos($script, '/account/') !== false || strpos($script, 'account.php') !== false) {
        $currentPage = 'account';
    } else {
        $currentPage = '';
    }
}
?>

<div class="bottom-nav">
    <div class="nav-wrap">

        <!-- 1. HOME -->
        <a href="/dashboard.php" class="nav-btn <?= $currentPage === 'home' ? 'active' : '' ?>">
            <div class="icon">🏠</div>
            <span>Home</span>
        </a>

        <!-- 2. BUY -->
        <a href="/purchase.php" class="nav-btn <?= $currentPage === 'buy' ? 'active' : '' ?>">
            <div class="icon">🛒</div>
            <span>Buy</span>
        </a>

        <!-- 3. CHAT (CENTERPIECE) -->
        <a href="/chat/indexx.php" class="nav-btn center <?= $currentPage === 'chat' ? 'active' : '' ?>">
            <div class="icon">🗨️</div>
            <span>Chat</span>
        </a>

        <!-- 4. HEAD/TAIL -->
        <a href="/headtail.php" class="nav-btn <?= $currentPage === 'headtail' ? 'active' : '' ?>">
            <div class="icon">🎲</div>
            <span>Head/Tail</span>
        </a>

        <!-- 5. ACCOUNT (MUST BE /account/) -->
        <a href="/account/" class="nav-btn <?= $currentPage === 'account' ? 'active' : '' ?>">
            <div class="icon">👤</div>
            <span>Account</span>
        </a>

    </div>
</div>
