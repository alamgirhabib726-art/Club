<?php
/* ==================================================
   DATABASE CONNECTION
================================================== */

/* ==================================================
   DATABASE CONFIGURATION & AUTO-DISCOVERY (RAILWAY / CLOUD COMPATIBLE)
================================================== */

$dbUrl = getenv('MYSQL_URL') ?: (getenv('DATABASE_URL') ?: '');
$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_NAME = 'unmoor_club';
$DB_USER = 'root';
$DB_PASS = '';

if (!empty($dbUrl) && str_starts_with($dbUrl, 'mysql://')) {
    $parsedUrl = parse_url($dbUrl);
    $DB_HOST = $parsedUrl['host'] ?? '127.0.0.1';
    $DB_PORT = (string)($parsedUrl['port'] ?? '3306');
    $DB_USER = $parsedUrl['user'] ?? 'root';
    $DB_PASS = $parsedUrl['pass'] ?? '';
    $DB_NAME = ltrim($parsedUrl['path'] ?? 'unmoor_club', '/');
} else {
    $DB_HOST = getenv('MYSQLHOST') ?: (getenv('MYSQL_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1'));
    $DB_PORT = getenv('MYSQLPORT') ?: (getenv('MYSQL_PORT') ?: (getenv('DB_PORT') ?: '3306'));
    $DB_NAME = getenv('MYSQLDATABASE') ?: (getenv('MYSQL_DATABASE') ?: (getenv('DB_NAME') ?: 'unmoor_club'));
    $DB_USER = getenv('MYSQLUSER') ?: (getenv('MYSQL_USER') ?: (getenv('DB_USER') ?: 'root'));
    $DB_PASS = getenv('MYSQLPASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: (getenv('DB_PASS') ?: ''));
}

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];
if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    $pdoOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
}
if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
    $pdoOptions[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
}

$db = null;

// Helper to auto-import schema and seed rows if database is empty
function bootstrapDatabaseIfEmpty($pdo) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $chk = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        } else {
            $chk = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
        }

        if (!$chk) {
            $sqlFile = __DIR__ . '/../database.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                if ($driver === 'mysql') {
                    try {
                        $pdo->exec($sqlContent);
                    } catch (Throwable $ex) {
                        $queries = preg_split('/;\s*[\r\n]+/', $sqlContent);
                        foreach ($queries as $q) {
                            $q = trim($q);
                            if (!empty($q) && !str_starts_with($q, '/*') && !str_starts_with($q, '--')) {
                                try {
                                    $pdo->exec($q);
                                } catch (Throwable $t) {}
                            }
                        }
                    }
                }
            }
        }

        // Schema integrity checks & migrations
        try {
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE users ADD COLUMN locked_coins decimal(18,4) NOT NULL DEFAULT 0.0000");
            } else {
                $pdo->exec("ALTER TABLE users ADD COLUMN locked_coins REAL DEFAULT 0");
            }
        } catch (Throwable $t) {
            // Column already exists
        }

        // Fix missing source column in coin_history (prevents "no such column: source" error on deposit/approval)
        try {
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE coin_history ADD COLUMN source VARCHAR(191) DEFAULT NULL");
            } else {
                $pdo->exec("ALTER TABLE coin_history ADD COLUMN source TEXT DEFAULT NULL");
            }
        } catch (Throwable $t) {
            // Column already exists
        }

        try {
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE coin_history ADD COLUMN source_name VARCHAR(191) DEFAULT NULL");
                $pdo->exec("ALTER TABLE coin_history ADD COLUMN source_number VARCHAR(64) DEFAULT NULL");
                $pdo->exec("ALTER TABLE coin_history ADD COLUMN source_user_id INT DEFAULT NULL");
            } else {
                $pdo->exec("ALTER TABLE coin_history ADD COLUMN source_name TEXT DEFAULT NULL");
                $pdo->exec("ALTER TABLE coin_history ADD COLUMN source_number TEXT DEFAULT NULL");
                $pdo->exec("ALTER TABLE coin_history ADD COLUMN source_user_id INTEGER DEFAULT NULL");
            }
        } catch (Throwable $t) {}

        try {
            if ($driver === 'sqlite') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS headtail_bets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    choice TEXT NOT NULL,
                    result TEXT NOT NULL,
                    bet_amount REAL NOT NULL,
                    profit REAL NOT NULL,
                    created_at TEXT DEFAULT (datetime('now'))
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS system_ledger (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    type TEXT NOT NULL,
                    amount REAL NOT NULL,
                    source TEXT,
                    reference TEXT,
                    created_at TEXT DEFAULT (datetime('now'))
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sender_id INTEGER NOT NULL,
                    receiver_id INTEGER NOT NULL,
                    message TEXT,
                    image TEXT,
                    seen INTEGER DEFAULT 0,
                    created_at TEXT DEFAULT (datetime('now'))
                )");

                // TRADING SYSTEM TABLES (SQLite)
                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_wallets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER UNIQUE NOT NULL,
                    bdt_balance REAL NOT NULL DEFAULT 0.0000,
                    tuc_balance REAL NOT NULL DEFAULT 0.0000,
                    locked_margin REAL NOT NULL DEFAULT 0.0000,
                    realized_pnl REAL NOT NULL DEFAULT 0.0000,
                    total_trading_fees REAL NOT NULL DEFAULT 0.0000,
                    total_conversion_fees REAL NOT NULL DEFAULT 0.0000,
                    created_at TEXT DEFAULT (datetime('now')),
                    updated_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_orders (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    symbol TEXT NOT NULL DEFAULT 'UC/BDT',
                    side TEXT NOT NULL,
                    order_type TEXT NOT NULL DEFAULT 'market',
                    price REAL NOT NULL,
                    amount REAL NOT NULL,
                    filled_amount REAL NOT NULL DEFAULT 0.0000,
                    margin REAL NOT NULL DEFAULT 0.0000,
                    leverage INTEGER NOT NULL DEFAULT 1,
                    stop_loss REAL,
                    take_profit REAL,
                    status TEXT NOT NULL DEFAULT 'open',
                    created_at TEXT DEFAULT (datetime('now')),
                    updated_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_positions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    symbol TEXT NOT NULL DEFAULT 'UC/BDT',
                    side TEXT NOT NULL,
                    entry_price REAL NOT NULL,
                    current_price REAL NOT NULL,
                    exit_price REAL,
                    position_size REAL NOT NULL,
                    margin REAL NOT NULL,
                    leverage INTEGER NOT NULL DEFAULT 1,
                    liquidation_price REAL NOT NULL,
                    stop_loss REAL,
                    take_profit REAL,
                    entry_fee REAL NOT NULL DEFAULT 0.0000,
                    exit_fee REAL NOT NULL DEFAULT 0.0000,
                    pnl REAL NOT NULL DEFAULT 0.0000,
                    roi REAL NOT NULL DEFAULT 0.0000,
                    status TEXT NOT NULL DEFAULT 'open',
                    close_reason TEXT,
                    created_at TEXT DEFAULT (datetime('now')),
                    closed_at TEXT,
                    updated_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    type TEXT NOT NULL,
                    amount REAL NOT NULL,
                    asset TEXT NOT NULL,
                    reference TEXT,
                    balance_before REAL NOT NULL DEFAULT 0.0000,
                    balance_after REAL NOT NULL DEFAULT 0.0000,
                    created_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_fees (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    position_id INTEGER,
                    order_id INTEGER,
                    conversion_id INTEGER,
                    fee_type TEXT NOT NULL,
                    amount REAL NOT NULL,
                    asset TEXT NOT NULL DEFAULT 'BDT',
                    rate REAL NOT NULL,
                    created_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_ledger (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_type TEXT NOT NULL,
                    user_id INTEGER,
                    debit REAL NOT NULL DEFAULT 0.0000,
                    credit REAL NOT NULL DEFAULT 0.0000,
                    asset TEXT NOT NULL,
                    balance_after REAL NOT NULL DEFAULT 0.0000,
                    description TEXT,
                    reference TEXT,
                    created_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS market_state (
                    symbol TEXT PRIMARY KEY,
                    price REAL NOT NULL DEFAULT 2.0000,
                    bid REAL NOT NULL DEFAULT 1.9950,
                    ask REAL NOT NULL DEFAULT 2.0050,
                    open_24h REAL NOT NULL DEFAULT 2.0000,
                    high_24h REAL NOT NULL DEFAULT 2.0000,
                    low_24h REAL NOT NULL DEFAULT 2.0000,
                    change_24h REAL NOT NULL DEFAULT 0.0000,
                    volume_24h REAL NOT NULL DEFAULT 0.0000,
                    volume_bdt_24h REAL NOT NULL DEFAULT 0.0000,
                    status TEXT NOT NULL DEFAULT 'active',
                    updated_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS market_ticks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    symbol TEXT NOT NULL DEFAULT 'UC',
                    price REAL NOT NULL,
                    volume REAL NOT NULL DEFAULT 1.0000,
                    side TEXT NOT NULL DEFAULT 'buy',
                    created_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS market_candles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    symbol TEXT NOT NULL DEFAULT 'UC',
                    resolution TEXT NOT NULL DEFAULT '1m',
                    open REAL NOT NULL,
                    high REAL NOT NULL,
                    low REAL NOT NULL,
                    close REAL NOT NULL,
                    volume REAL NOT NULL DEFAULT 0.0000,
                    timestamp INTEGER NOT NULL,
                    created_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS liquidity_pool (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    bdt_reserve REAL NOT NULL DEFAULT 500000.0000,
                    tuc_reserve REAL NOT NULL DEFAULT 250000.0000,
                    total_fee_revenue_bdt REAL NOT NULL DEFAULT 0.0000,
                    conversion_fee_revenue_bdt REAL NOT NULL DEFAULT 0.0000,
                    realized_pnl_bdt REAL NOT NULL DEFAULT 0.0000,
                    status TEXT NOT NULL DEFAULT 'active',
                    min_reserve_bdt REAL NOT NULL DEFAULT 5000.0000,
                    max_open_interest_bdt REAL NOT NULL DEFAULT 500000.0000,
                    updated_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS liquidity_ledger (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    type TEXT NOT NULL,
                    amount REAL NOT NULL,
                    asset TEXT NOT NULL,
                    balance_before REAL NOT NULL DEFAULT 0.0000,
                    balance_after REAL NOT NULL DEFAULT 0.0000,
                    source TEXT,
                    reference TEXT,
                    created_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS conversions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    from_asset TEXT NOT NULL,
                    to_asset TEXT NOT NULL,
                    from_amount REAL NOT NULL,
                    to_amount_gross REAL NOT NULL,
                    fee_rate REAL NOT NULL DEFAULT 0.01345,
                    fee_amount REAL NOT NULL,
                    to_amount_net REAL NOT NULL,
                    rate REAL NOT NULL,
                    status TEXT NOT NULL DEFAULT 'completed',
                    created_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS liquidation_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    position_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    symbol TEXT NOT NULL DEFAULT 'UC/BDT',
                    side TEXT NOT NULL,
                    entry_price REAL NOT NULL,
                    mark_price REAL NOT NULL,
                    liquidation_price REAL NOT NULL,
                    margin REAL NOT NULL,
                    loss_amount REAL NOT NULL,
                    fee_amount REAL NOT NULL DEFAULT 0.0000,
                    settled_at TEXT DEFAULT (datetime('now'))
                )");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_settings (
                    setting_key TEXT PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    description TEXT,
                    updated_at TEXT DEFAULT (datetime('now'))
                )");

                // SAFE SQLITE SCHEMA MIGRATION / SYNC
                $sqliteColumns = [
                    'market_ticks' => [
                        'volume' => 'REAL DEFAULT 0.0000',
                        'side' => "TEXT DEFAULT 'buy'",
                        'mode' => "TEXT DEFAULT 'live'"
                    ],
                    'market_state' => [
                        'bid' => 'REAL DEFAULT 1.9950',
                        'ask' => 'REAL DEFAULT 2.0050',
                        'open_24h' => 'REAL DEFAULT 2.0000',
                        'high_24h' => 'REAL DEFAULT 2.0000',
                        'low_24h' => 'REAL DEFAULT 2.0000',
                        'change_24h' => 'REAL DEFAULT 0.0000',
                        'volume_24h' => 'REAL DEFAULT 0.0000',
                        'volume_bdt_24h' => 'REAL DEFAULT 0.0000',
                        'status' => "TEXT DEFAULT 'active'"
                    ],
                    'trading_positions' => [
                        'close_reason' => 'TEXT',
                        'closed_at' => 'TEXT',
                        'entry_fee' => 'REAL DEFAULT 0.0000',
                        'exit_fee' => 'REAL DEFAULT 0.0000',
                        'pnl' => 'REAL DEFAULT 0.0000',
                        'roi' => 'REAL DEFAULT 0.0000',
                        'status' => "TEXT DEFAULT 'open'",
                        'stop_loss' => 'REAL',
                        'take_profit' => 'REAL',
                        'liquidation_price' => 'REAL DEFAULT 0.0000',
                        'updated_at' => "TEXT DEFAULT (datetime('now'))"
                    ],
                    'trading_orders' => [
                        'filled_amount' => 'REAL DEFAULT 0.0000',
                        'margin' => 'REAL DEFAULT 0.0000',
                        'leverage' => 'INTEGER DEFAULT 1',
                        'stop_loss' => 'REAL',
                        'take_profit' => 'REAL',
                        'status' => "TEXT DEFAULT 'open'",
                        'updated_at' => "TEXT DEFAULT (datetime('now'))"
                    ],
                    'trading_wallets' => [
                        'bdt_balance' => 'REAL DEFAULT 0.0000',
                        'tuc_balance' => 'REAL DEFAULT 0.0000',
                        'locked_margin' => 'REAL DEFAULT 0.0000',
                        'realized_pnl' => 'REAL DEFAULT 0.0000',
                        'total_trading_fees' => 'REAL DEFAULT 0.0000',
                        'total_conversion_fees' => 'REAL DEFAULT 0.0000'
                    ]
                ];

                foreach ($sqliteColumns as $tbl => $cols) {
                    try {
                        $existing = $pdo->query("PRAGMA table_info($tbl)")->fetchAll(PDO::FETCH_ASSOC);
                        $colNames = array_map(function($c) { return strtolower($c['name']); }, $existing);
                        foreach ($cols as $colName => $colDef) {
                            if (!in_array(strtolower($colName), $colNames, true)) {
                                $pdo->exec("ALTER TABLE $tbl ADD COLUMN $colName $colDef");
                            }
                        }
                    } catch (Throwable $e) {}
                }
            } else {
                $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sender_id INT NOT NULL,
                    receiver_id INT NOT NULL,
                    message TEXT,
                    image VARCHAR(255),
                    seen TINYINT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (sender_id),
                    INDEX (receiver_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // TRADING SYSTEM TABLES (MySQL)
                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_wallets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNIQUE NOT NULL,
                    bdt_balance DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    tuc_balance DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    locked_margin DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    realized_pnl DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    total_trading_fees DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    total_conversion_fees DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    symbol VARCHAR(32) NOT NULL DEFAULT 'UC/BDT',
                    side VARCHAR(16) NOT NULL,
                    order_type VARCHAR(32) NOT NULL DEFAULT 'market',
                    price DECIMAL(18,4) NOT NULL,
                    amount DECIMAL(18,4) NOT NULL,
                    filled_amount DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    margin DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    leverage INT NOT NULL DEFAULT 1,
                    stop_loss DECIMAL(18,4) DEFAULT NULL,
                    take_profit DECIMAL(18,4) DEFAULT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT 'open',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (status),
                    INDEX (symbol)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_positions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    symbol VARCHAR(32) NOT NULL DEFAULT 'UC/BDT',
                    side VARCHAR(16) NOT NULL,
                    entry_price DECIMAL(18,4) NOT NULL,
                    current_price DECIMAL(18,4) NOT NULL,
                    exit_price DECIMAL(18,4) DEFAULT NULL,
                    position_size DECIMAL(18,4) NOT NULL,
                    margin DECIMAL(18,4) NOT NULL,
                    leverage INT NOT NULL DEFAULT 1,
                    liquidation_price DECIMAL(18,4) NOT NULL,
                    stop_loss DECIMAL(18,4) DEFAULT NULL,
                    take_profit DECIMAL(18,4) DEFAULT NULL,
                    entry_fee DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    exit_fee DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    pnl DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    roi DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    status VARCHAR(32) NOT NULL DEFAULT 'open',
                    close_reason TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    closed_at DATETIME DEFAULT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (status),
                    INDEX (symbol)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_transactions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    type VARCHAR(64) NOT NULL,
                    amount DECIMAL(18,4) NOT NULL,
                    asset VARCHAR(32) NOT NULL,
                    reference VARCHAR(255) DEFAULT NULL,
                    balance_before DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    balance_after DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_fees (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    position_id INT DEFAULT NULL,
                    order_id INT DEFAULT NULL,
                    conversion_id INT DEFAULT NULL,
                    fee_type VARCHAR(64) NOT NULL,
                    amount DECIMAL(18,4) NOT NULL,
                    asset VARCHAR(32) NOT NULL DEFAULT 'BDT',
                    rate DECIMAL(10,6) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (fee_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_ledger (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    account_type VARCHAR(64) NOT NULL,
                    user_id INT DEFAULT NULL,
                    debit DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    credit DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    asset VARCHAR(32) NOT NULL,
                    balance_after DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    description TEXT DEFAULT NULL,
                    reference VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (account_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS market_state (
                    symbol VARCHAR(32) PRIMARY KEY,
                    price DECIMAL(18,4) NOT NULL DEFAULT 2.0000,
                    bid DECIMAL(18,4) NOT NULL DEFAULT 1.9950,
                    ask DECIMAL(18,4) NOT NULL DEFAULT 2.0050,
                    open_24h DECIMAL(18,4) NOT NULL DEFAULT 2.0000,
                    high_24h DECIMAL(18,4) NOT NULL DEFAULT 2.0000,
                    low_24h DECIMAL(18,4) NOT NULL DEFAULT 2.0000,
                    change_24h DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
                    volume_24h DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    volume_bdt_24h DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    status VARCHAR(32) NOT NULL DEFAULT 'active',
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS market_ticks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    symbol VARCHAR(32) NOT NULL DEFAULT 'UC',
                    price DECIMAL(18,4) NOT NULL,
                    volume DECIMAL(18,4) NOT NULL DEFAULT 1.0000,
                    side VARCHAR(16) NOT NULL DEFAULT 'buy',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (symbol),
                    INDEX (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS market_candles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    symbol VARCHAR(32) NOT NULL DEFAULT 'UC',
                    resolution VARCHAR(16) NOT NULL DEFAULT '1m',
                    open DECIMAL(18,4) NOT NULL,
                    high DECIMAL(18,4) NOT NULL,
                    low DECIMAL(18,4) NOT NULL,
                    close DECIMAL(18,4) NOT NULL,
                    volume DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    timestamp BIGINT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (symbol, resolution, timestamp)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS liquidity_pool (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    bdt_reserve DECIMAL(18,4) NOT NULL DEFAULT 500000.0000,
                    tuc_reserve DECIMAL(18,4) NOT NULL DEFAULT 250000.0000,
                    total_fee_revenue_bdt DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    conversion_fee_revenue_bdt DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    realized_pnl_bdt DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    status VARCHAR(32) NOT NULL DEFAULT 'active',
                    min_reserve_bdt DECIMAL(18,4) NOT NULL DEFAULT 5000.0000,
                    max_open_interest_bdt DECIMAL(18,4) NOT NULL DEFAULT 500000.0000,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS liquidity_ledger (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type VARCHAR(64) NOT NULL,
                    amount DECIMAL(18,4) NOT NULL,
                    asset VARCHAR(32) NOT NULL,
                    balance_before DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    balance_after DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    source VARCHAR(128) DEFAULT NULL,
                    reference VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS conversions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    from_asset VARCHAR(32) NOT NULL,
                    to_asset VARCHAR(32) NOT NULL,
                    from_amount DECIMAL(18,4) NOT NULL,
                    to_amount_gross DECIMAL(18,4) NOT NULL,
                    fee_rate DECIMAL(8,6) NOT NULL DEFAULT 0.01345,
                    fee_amount DECIMAL(18,4) NOT NULL,
                    to_amount_net DECIMAL(18,4) NOT NULL,
                    rate DECIMAL(18,4) NOT NULL,
                    status VARCHAR(32) NOT NULL DEFAULT 'completed',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS liquidation_events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    position_id INT NOT NULL,
                    user_id INT NOT NULL,
                    symbol VARCHAR(32) NOT NULL DEFAULT 'UC/BDT',
                    side VARCHAR(16) NOT NULL,
                    entry_price DECIMAL(18,4) NOT NULL,
                    mark_price DECIMAL(18,4) NOT NULL,
                    liquidation_price DECIMAL(18,4) NOT NULL,
                    margin DECIMAL(18,4) NOT NULL,
                    loss_amount DECIMAL(18,4) NOT NULL,
                    fee_amount DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                    settled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (position_id),
                    INDEX (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS trading_settings (
                    setting_key VARCHAR(64) PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    description TEXT DEFAULT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }

            // Seed Market State Initial Listing Price = 2.00 BDT
            $hasMarket = $pdo->query("SELECT symbol FROM market_state WHERE symbol = 'UC' LIMIT 1")->fetch();
            if (!$hasMarket) {
                $pdo->exec("INSERT INTO market_state (symbol, price, bid, ask, open_24h, high_24h, low_24h, change_24h, volume_24h, volume_bdt_24h, status)
                    VALUES ('UC', 2.0000, 1.9950, 2.0050, 2.0000, 2.0000, 2.0000, 0.0000, 0.0000, 0.0000, 'active')");
            }

            // Seed Liquidity Pool
            $hasPool = $pdo->query("SELECT id FROM liquidity_pool LIMIT 1")->fetch();
            if (!$hasPool) {
                $pdo->exec("INSERT INTO liquidity_pool (id, bdt_reserve, tuc_reserve, total_fee_revenue_bdt, conversion_fee_revenue_bdt, realized_pnl_bdt, status, min_reserve_bdt, max_open_interest_bdt)
                    VALUES (1, 500000.0000, 250000.0000, 0.0000, 0.0000, 0.0000, 'active', 5000.0000, 500000.0000)");
            }

            // Seed Trading Settings
            $defaultSettings = [
                'initial_price' => ['2.0000', 'Initial listing market price in BDT per Trading UC'],
                'maker_fee_rate' => ['0.0005', 'Maker order fee rate (0.05%)'],
                'taker_fee_rate' => ['0.0010', 'Taker order fee rate (0.10%)'],
                'conversion_fee_rate' => ['0.01345', 'Club UC <-> BDT conversion fee (1.345%)'],
                'leverage_fee_rate_per_10x' => ['0.0001', 'Leverage risk fee per 10x leverage (0.01%)'],
                'liquidation_fee_rate' => ['0.0100', 'Liquidation penalty fee rate (1.00%)'],
                'maintenance_margin_rate' => ['0.0050', 'Maintenance margin threshold rate (0.50%)'],
                'min_margin_bdt' => ['10.00', 'Minimum margin in BDT to open leveraged position'],
                'max_leverage' => ['100', 'Maximum allowed leverage multiplier'],
                'min_order_tuc' => ['1.0', 'Minimum order amount in Trading UC'],
                'trading_enabled' => ['1', 'Global trading engine switch (1=enabled, 0=disabled)'],
                'market_status' => ['active', 'Market status: active | halted | circuit_breaker'],
                'volatility_factor' => ['0.0035', 'Deterministic price volatility coefficient'],
                'tick_interval_sec' => ['2', 'Target seconds between automated market ticks']
            ];

            foreach ($defaultSettings as $k => $v) {
                $chk = $pdo->prepare("SELECT setting_key FROM trading_settings WHERE setting_key = ?");
                $chk->execute([$k]);
                if (!$chk->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO trading_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
                    $ins->execute([$k, $v[0], $v[1]]);
                }
            }
        } catch (Throwable $t) {}

    } catch (Throwable $t) {
        // Continue if check fails
    }
}

/* ==================================================
   COIN BALANCE & ORDER TRANSACTION HELPERS
================================================== */

function get_user_balances(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT coins, COALESCE(locked_coins, 0) AS locked_coins, role, status, apply_status, name, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['coins' => 0.0, 'locked_coins' => 0.0, 'total_coins' => 0.0, 'name' => '', 'phone' => ''];
    }
    $avail = (float)$row['coins'];
    $locked = (float)$row['locked_coins'];
    return [
        'coins' => $avail,
        'locked_coins' => $locked,
        'total_coins' => $avail + $locked,
        'name' => $row['name'] ?? '',
        'phone' => $row['phone'] ?? '',
        'role' => $row['role'] ?? 'user',
        'status' => $row['status'] ?? 'active',
        'apply_status' => $row['apply_status'] ?? 'approved'
    ];
}

function lock_user_order(PDO $pdo, int $userId, float $amount, int $productId, string $source = ''): array {
    if ($amount <= 0) {
        return ['success' => false, 'error' => 'Invalid order amount.'];
    }

    $pdo->beginTransaction();
    try {
        // Check and lock user balance
        $stmt = $pdo->prepare("SELECT coins, locked_coins FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'User account not found.'];
        }

        $avail = (float)$u['coins'];
        if ($avail < $amount) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Insufficient available balance. You have 🪙 ' . number_format($avail, 2) . ' available.'];
        }

        // Atomically move from available to locked
        $upd = $pdo->prepare("UPDATE users SET coins = coins - ?, locked_coins = COALESCE(locked_coins, 0) + ? WHERE id = ? AND coins >= ?");
        $upd->execute([$amount, $amount, $userId, $amount]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Balance deduction race condition detected. Please retry.'];
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        // Insert pending payment/order
        $ins = $pdo->prepare("
            INSERT INTO payments (user_id, type, amount, status, source, product_id, created_at)
            VALUES (?, 'purchase', ?, 'pending', ?, ?, $nowExpr)
        ");
        $ins->execute([$userId, $amount, $source, $productId]);
        $orderId = (int)$pdo->lastInsertId();

        // Write ledger records
        $pdo->prepare("
            INSERT INTO coin_history (user_id, amount, type, reference, created_at)
            VALUES (?, 0, 'order_locked', ?, $nowExpr)
        ")->execute([$userId, "Coins locked for Order #$orderId (🪙 " . number_format($amount, 2) . ")"]);

        $pdo->prepare("
            INSERT INTO system_ledger (type, amount, source, reference, created_at)
            VALUES ('order_locked', ?, 'STORE', ?, $nowExpr)
        ")->execute([$amount, "Order #$orderId placed by User #$userId"]);

        $pdo->commit();

        $newBal = get_user_balances($pdo, $userId);
        return [
            'success' => true,
            'order_id' => $orderId,
            'locked_amount' => $amount,
            'balances' => $newBal
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => 'Order placement failed: ' . $e->getMessage()];
    }
}

function approve_locked_order(PDO $pdo, int $orderId, ?int $adminId = null): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, user_id, amount, status, type FROM payments WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($order['status'] !== 'pending') {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Order is not pending (status: ' . $order['status'] . ').'];
        }

        $userId = (int)$order['user_id'];
        $amount = (float)$order['amount'];

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        // Idempotently update status
        $updOrder = $pdo->prepare("UPDATE payments SET status = 'approved' WHERE id = ? AND status = 'pending'");
        $updOrder->execute([$orderId]);

        if ($updOrder->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Order already processed by another request.'];
        }

        // Deduct permanently from buyer's locked_coins
        $pdo->prepare("
            UPDATE users
            SET locked_coins = CASE WHEN locked_coins >= ? THEN locked_coins - ? ELSE 0 END
            WHERE id = ?
        ")->execute([$amount, $amount, $userId]);

        // Credit to Club Fund (System Treasury account)
        $systemId = $pdo->query("SELECT id FROM users WHERE role = 'system' LIMIT 1")->fetchColumn();
        if ($systemId) {
            $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$amount, (int)$systemId]);
            $pdo->prepare("
                INSERT INTO coin_history (user_id, amount, type, reference, source_user_id, created_at)
                VALUES (?, ?, 'purchase_revenue', ?, ?, $nowExpr)
            ")->execute([(int)$systemId, $amount, "Product Sale Revenue from Order #$orderId", $userId]);
        }

        // Insert coin history for buyer
        $pdo->prepare("
            INSERT INTO coin_history (user_id, amount, type, reference, created_at)
            VALUES (?, 0, 'purchase_completed', ?, $nowExpr)
        ")->execute([$userId, "Order #$orderId approved and completed (Coins transferred to Club Fund)"]);

        // Insert system ledger
        $pdo->prepare("
            INSERT INTO system_ledger (type, amount, source, reference, created_at)
            VALUES ('purchase_approved', ?, 'STORE', ?, $nowExpr)
        ")->execute([$amount, "Order #$orderId approved for User #$userId (Funded Club Reserve)"]);

        $pdo->commit();
        return ['success' => true, 'order_id' => $orderId, 'user_id' => $userId];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => 'Approval failed: ' . $e->getMessage()];
    }
}

function reject_locked_order(PDO $pdo, int $orderId, ?int $adminId = null): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, user_id, amount, status, type FROM payments WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($order['status'] !== 'pending') {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Order is not pending (status: ' . $order['status'] . ').'];
        }

        $userId = (int)$order['user_id'];
        $amount = (float)$order['amount'];

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

        // Idempotently update status
        $updOrder = $pdo->prepare("UPDATE payments SET status = 'rejected' WHERE id = ? AND status = 'pending'");
        $updOrder->execute([$orderId]);

        if ($updOrder->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Order already processed by another request.'];
        }

        // Release locked coins back to available coins
        $pdo->prepare("
            UPDATE users
            SET coins = coins + ?,
                locked_coins = CASE WHEN locked_coins >= ? THEN locked_coins - ? ELSE 0 END
            WHERE id = ?
        ")->execute([$amount, $amount, $amount, $userId]);

        // Insert coin history
        $pdo->prepare("
            INSERT INTO coin_history (user_id, amount, type, reference, created_at)
            VALUES (?, ?, 'purchase_rejected', ?, $nowExpr)
        ")->execute([$userId, $amount, "Order #$orderId rejected — 🪙 " . number_format($amount, 2) . " released back to Available Balance"]);

        // Insert system ledger
        $pdo->prepare("
            INSERT INTO system_ledger (type, amount, source, reference, created_at)
            VALUES ('purchase_rejected', ?, 'STORE', ?, $nowExpr)
        ")->execute([-$amount, "Order #$orderId rejected, coins released for User #$userId"]);

        $pdo->commit();
        return ['success' => true, 'order_id' => $orderId, 'user_id' => $userId];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => 'Rejection failed: ' . $e->getMessage()];
    }
}


$isExternalMySQL = !empty(getenv('MYSQL_URL')) || !empty(getenv('DATABASE_URL')) || !empty(getenv('MYSQLHOST')) || !empty(getenv('DB_HOST'));
$isProduction = $isExternalMySQL || (getenv('RAILWAY_ENVIRONMENT') !== false) || (getenv('APP_ENV') === 'production');

try {
    if ($isExternalMySQL) {
        $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
        $db = new PDO($dsn, $DB_USER, $DB_PASS, $pdoOptions);
        bootstrapDatabaseIfEmpty($db);
    } elseif (getenv('DB_DRIVER') === 'mysql') {
        $db = new PDO("mysql:host=127.0.0.1;dbname=$DB_NAME;charset=utf8mb4", "root", "", $pdoOptions);
        bootstrapDatabaseIfEmpty($db);
    } else {
        // Single local development source of truth
        $sqlitePath = __DIR__ . '/../database.sqlite';
        $db = new PDO("sqlite:" . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $db->exec("PRAGMA foreign_keys = ON;");
        bootstrapDatabaseIfEmpty($db);
    }

    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->sqliteCreateFunction('NOW', function() {
            return date('Y-m-d H:i:s');
        }, 0);
        $db->sqliteCreateFunction('CURDATE', function() {
            return date('Y-m-d');
        }, 0);
        $db->sqliteCreateFunction('UNIX_TIMESTAMP', function($val = null) {
            if ($val === null) return time();
            return is_numeric($val) ? (int)$val : (strtotime((string)$val) ?: time());
        }, -1);
        $db->sqliteCreateFunction('IF', function($cond, $trueVal, $falseVal) {
            return $cond ? $trueVal : $falseVal;
        }, 3);
        $db->sqliteCreateFunction('CONCAT', function(...$args) {
            return implode('', $args);
        }, -1);
        $db->sqliteCreateFunction('FIND_IN_SET', function($str, $strList) {
            $arr = explode(',', (string)$strList);
            $idx = array_search((string)$str, $arr, true);
            return $idx === false ? 0 : $idx + 1;
        }, 2);
    }
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    if ($isProduction) {
        die("<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Database Error • Unmoor Club</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#020617;color:#e5e7eb;font-family:system-ui;text-align:center;padding:16px}.card{max-width:440px;padding:32px;background:#0f172a;border:1px solid #1f2937;border-radius:18px}h2{color:#ef4444;margin-top:0}p{color:#9ca3af;font-size:14px;line-height:1.6}</style></head><body><div class='card'><h2>Database Service Unavailable</h2><p>The configured database service could not be reached. Please check the database server status, network connectivity, and deployment environment variables.</p></div></body></html>");
    } else {
        die("<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Database Error</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#020617;color:#e5e7eb;font-family:system-ui;text-align:center;padding:16px}.card{max-width:440px;padding:32px;background:#0f172a;border:1px solid #1f2937;border-radius:18px}h2{color:#ef4444;margin-top:0}p{color:#9ca3af;font-size:14px;line-height:1.6}</style></head><body><div class='card'><h2>Database Initialization Error</h2><p>Unable to connect to local database engine. Check server logs for details.</p></div></body></html>");
    }
}

/* ==================================================
   SESSION
================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==================================================
   MAINTENANCE MODE (GLOBAL)
================================================== */

$settings = $db
    ->query("SELECT maintenance, updated_at FROM settings WHERE id = 1")
    ->fetch();

$maintenance = (int)($settings['maintenance'] ?? 0);
$updatedAt   = $settings['updated_at'] ?? null;

$currentPath = $_SERVER['REQUEST_URI'] ?? '';

/* Detect role */
$role = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
}

/* Maintenance rules */
if ($maintenance === 1) {

    $isAdminPath =
        (strpos($currentPath, "/admin") === 0) ||
        (strpos($currentPath, "admin_login.php") !== false);

    if ($role !== 'admin' && !$isAdminPath) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Maintenance</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body{
                    margin:0;height:100vh;
                    display:flex;align-items:center;justify-content:center;
                    background:#020617;color:#e5e7eb;font-family:system-ui;
                }
                .card{
                    max-width:420px;padding:32px;
                    border-radius:22px;
                    background:#020617;
                    border:1px solid #1f2937;
                    text-align:center;
                }
                .badge{
                    background:#7f1d1d;color:#fecaca;
                    padding:8px 16px;border-radius:999px;
                    font-weight:800;font-size:13px;
                }
                h1{margin:16px 0 8px}
                p{color:#9ca3af}
            </style>
        </head>
        <body>
            <div class="card">
                <div class="badge">🔒 MAINTENANCE</div>
                <h1>System Upgrade</h1>
                <p>অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন</p>
                <?php if ($updatedAt): ?>
                    <small><?= date("d M Y, h:i A", strtotime($updatedAt)) ?></small>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/* ==================================================
   DAILY COIN CUT & GLOBAL DEBT CONTROL ENGINE
================================================== */

function process_user_daily_coin_cut(PDO $pdo, array $user): array {
    if (empty($user['id']) || ($user['role'] ?? '') !== 'user' || empty($user['coin_cycle_start'])) {
        return $user;
    }

    $now = time();
    $cycleStart = strtotime($user['coin_cycle_start']);
    $lastCutTs = !empty($user['last_coin_cut']) ? strtotime($user['last_coin_cut']) : $cycleStart;

    $daysPassed  = (int)floor(($now - $cycleStart) / 86400);
    $actualCuts  = (int)floor(($lastCutTs - $cycleStart) / 86400);
    $pendingCuts = $daysPassed - $actualCuts;

    if ($pendingCuts > 0) {
        $currentCoins = (float)$user['coins'];
        $realCut = max(0.0, min($currentCoins, (float)$pendingCuts));
        $newCutDays = $actualCuts + $pendingCuts;

        $pdo->beginTransaction();
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $cutDate = date('Y-m-d H:i:s', strtotime($user['coin_cycle_start'] . " + " . (int)$newCutDays . " days"));

            $pdo->prepare("
                UPDATE users
                SET coins = coins - ?,
                    last_coin_cut = ?
                WHERE id = ?
            ")->execute([
                $pendingCuts,
                $cutDate,
                $user['id']
            ]);

            if ($realCut > 0) {
                $systemId = (int)$pdo->query("SELECT id FROM users WHERE role='system' LIMIT 1")->fetchColumn();
                if ($systemId) {
                    $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$realCut, $systemId]);
                    $pdo->prepare("
                        INSERT INTO coin_history (user_id, amount, type, reference, source_user_id, source_name, source_number, created_at)
                        VALUES (?, ?, 'credit', 'User Daily Coin Cut', ?, ?, ?, " . ($driver === 'sqlite' ? "datetime('now')" : "NOW()") . ")
                    ")->execute([
                        $systemId,
                        $realCut,
                        $user['id'],
                        $user['name'] ?? '',
                        $user['phone'] ?? ''
                    ]);

                    $pdo->prepare("
                        INSERT INTO system_ledger (type, amount, source, reference, created_at)
                        VALUES ('coin_cut', ?, 'auto_cycle', ?, " . ($driver === 'sqlite' ? "datetime('now')" : "NOW()") . ")
                    ")->execute([
                        $realCut,
                        'User ID: ' . $user['id']
                    ]);
                }
            }

            $pdo->prepare("
                INSERT INTO coin_history (user_id, amount, type, reference, source_user_id, source_name, source_number, created_at)
                VALUES (?, ?, 'debit', 'Daily Coin Cycle Fee', ?, ?, ?, " . ($driver === 'sqlite' ? "datetime('now')" : "NOW()") . ")
            ")->execute([
                $user['id'],
                -$pendingCuts,
                $user['id'],
                $user['name'] ?? '',
                $user['phone'] ?? ''
            ]);

            $pdo->commit();
            $user['coins'] -= $pendingCuts;
            $user['last_coin_cut'] = $cutDate;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("GLOBAL AUTO CUT FAILED: " . $e->getMessage());
        }
    }

    return $user;
}

if (!empty($_SESSION['user_id'])) {

    $stmt = $db->prepare("
        SELECT id, name, phone, coins, role, status, apply_status, coin_cycle_start, last_coin_cut
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userState = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userState && $userState['role'] === 'user') {

        // Auto process daily coin deduction globally
        $userState = process_user_daily_coin_cut($db, $userState);

        $coins = (float)$userState['coins'];
        $isDebt = ($coins < 0);

        /*
          ALLOWED EVEN IF DEBT:
          - dashboard.php
          - deposit.php
          - history.php
          - account.php
          - loan.php
          - logout.php
          - avatar.php
        */

        $allowedPages = [
            'dashboard.php',
            'deposit.php',
            'history.php',
            'account.php',
            'loan.php',
            'logout.php',
            'avatar.php'
        ];

        $currentFile = basename(parse_url($currentPath, PHP_URL_PATH));

        if ($isDebt && !in_array($currentFile, $allowedPages, true)) {
            header("Location: loan.php");
            exit;
        }

        /* Make available globally */
        define('IS_DEBT_USER', $isDebt);
        define('USER_COINS', $coins);
    }
}