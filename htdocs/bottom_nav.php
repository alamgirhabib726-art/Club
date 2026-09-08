<?php
// REQUIRED: $currentPage = 'home' | 'earn' | 'buy' | 'headtail' | 'account';
?>

<div class="bottom-nav">
    <div class="nav-wrap">

        <a href="dashboard.php"
           class="<?= $currentPage === 'home' ? 'active' : '' ?>">
            <div class="icon">🏠</div>
            <span>Home</span>
        </a>

        <a href="earn.php"
           class="<?= $currentPage === 'earn' ? 'active' : '' ?>">
            <div class="icon">💰</div>
            <span>Earn</span>
        </a>

        <!-- CENTER BUY -->
        <a href="purchase.php"
           class="center <?= $currentPage === 'buy' ? 'active' : '' ?>">
            <div class="icon">🛒</div>
            <span>Buy</span>
        </a>

        <a href="headtail.php"
           class="<?= $currentPage === 'headtail' ? 'active' : '' ?>">
            <div class="icon">🎲</div>
            <span>Head/Tail</span>
        </a>

        <a href="account.php"
           class="<?= $currentPage === 'account' ? 'active' : '' ?>">
            <div class="icon">👤</div>
            <span>Account</span>
        </a>

    </div>
</div>
