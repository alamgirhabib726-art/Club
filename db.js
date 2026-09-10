const { DatabaseSync } = require('node:sqlite');
const path = require('node:path');
const fs = require('node:fs');

const DB_PATH = process.env.DATABASE_PATH || path.join(__dirname, 'database.sqlite');
const db = new DatabaseSync(DB_PATH);

// Configure WAL mode for fast concurrent operations
db.exec("PRAGMA journal_mode = WAL;");
db.exec("PRAGMA foreign_keys = ON;");

// Migration / Schema Bootstrap
function bootstrap() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      phone TEXT UNIQUE NOT NULL,
      email TEXT UNIQUE,
      password TEXT NOT NULL,
      role TEXT NOT NULL DEFAULT 'user',
      status TEXT NOT NULL DEFAULT 'active',
      apply_status TEXT NOT NULL DEFAULT 'approved',
      coins REAL NOT NULL DEFAULT 0.0000,
      balance REAL NOT NULL DEFAULT 0.0000,
      purchase_balance REAL NOT NULL DEFAULT 0.0000,
      photo TEXT,
      admin_note TEXT,
      coin_cycle_start TEXT,
      last_coin_cut TEXT,
      last_seen TEXT,
      created_at TEXT DEFAULT (datetime('now')),
      updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS admin_balance_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      admin_id INTEGER NOT NULL,
      user_id INTEGER NOT NULL,
      amount REAL NOT NULL DEFAULT 0.0000,
      action TEXT NOT NULL DEFAULT 'add',
      note TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS chat_messages (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      sender_id INTEGER NOT NULL,
      receiver_id INTEGER NOT NULL,
      message TEXT,
      image TEXT,
      seen INTEGER NOT NULL DEFAULT 0,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS coin_history (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      amount REAL NOT NULL DEFAULT 0.0000,
      type TEXT NOT NULL,
      source TEXT,
      reference TEXT,
      change REAL,
      source_name TEXT,
      source_number TEXT,
      source_user_id INTEGER,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS coupons (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      code TEXT UNIQUE NOT NULL,
      amount REAL NOT NULL DEFAULT 0.00,
      type TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'active',
      used_by INTEGER,
      used_at TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS donations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      amount REAL NOT NULL DEFAULT 0.0000,
      method TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS earn_buttons (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      link TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'active',
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS event_participants (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      event_id INTEGER NOT NULL,
      user_id INTEGER NOT NULL,
      coins REAL NOT NULL DEFAULT 0.00,
      joined_at TEXT DEFAULT (datetime('now')),
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS events (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      description TEXT,
      coin_cost REAL NOT NULL DEFAULT 0.00,
      status TEXT NOT NULL DEFAULT 'active',
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS game_control (
      user_id INTEGER PRIMARY KEY,
      last_bet REAL NOT NULL DEFAULT 0.0000,
      plays INTEGER NOT NULL DEFAULT 0,
      last_play TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS headtail_bets (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      choice TEXT NOT NULL,
      result TEXT NOT NULL,
      bet_amount REAL NOT NULL DEFAULT 0.0000,
      profit REAL NOT NULL DEFAULT 0.0000,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS login_history (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      ip_address TEXT,
      user_agent TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER,
      action TEXT NOT NULL,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS market_price (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      symbol TEXT UNIQUE NOT NULL DEFAULT 'UC',
      price REAL NOT NULL DEFAULT 100.0000,
      updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS market_state (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      symbol TEXT UNIQUE NOT NULL DEFAULT 'UC',
      price REAL NOT NULL DEFAULT 100.0000,
      updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS market_ticks (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      symbol TEXT NOT NULL DEFAULT 'UC',
      price REAL NOT NULL,
      mode TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS messages (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      sender_id INTEGER,
      receiver_id INTEGER,
      message TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS notices (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      message TEXT NOT NULL,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS notifications (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      title TEXT NOT NULL,
      message TEXT NOT NULL,
      type TEXT NOT NULL DEFAULT 'info',
      is_read INTEGER NOT NULL DEFAULT 0,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS payments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      type TEXT NOT NULL,
      amount REAL NOT NULL DEFAULT 0.0000,
      method TEXT,
      plan TEXT,
      product_id INTEGER,
      proof TEXT,
      source TEXT,
      status TEXT NOT NULL DEFAULT 'pending',
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS positions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      side TEXT NOT NULL,
      symbol TEXT NOT NULL DEFAULT 'UC',
      margin REAL NOT NULL DEFAULT 0.0000,
      leverage REAL NOT NULL DEFAULT 1.00,
      size REAL NOT NULL DEFAULT 0.0000,
      entry_price REAL NOT NULL DEFAULT 0.0000,
      exit_price REAL,
      close_price REAL,
      sl REAL,
      stop_loss REAL,
      liq_price REAL,
      pnl REAL,
      close_reason TEXT,
      status TEXT NOT NULL DEFAULT 'open',
      closed_at TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS products (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      title TEXT NOT NULL,
      slug TEXT UNIQUE,
      type TEXT,
      price REAL NOT NULL DEFAULT 0.00,
      discount REAL NOT NULL DEFAULT 0.00,
      delivery_time TEXT NOT NULL DEFAULT 'Instant',
      active INTEGER NOT NULL DEFAULT 1,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS settings (
      k TEXT PRIMARY KEY,
      v TEXT,
      id INTEGER DEFAULT 1,
      maintenance INTEGER NOT NULL DEFAULT 0,
      updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS system_ledger (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      type TEXT NOT NULL,
      amount REAL NOT NULL DEFAULT 0.0000,
      source TEXT NOT NULL,
      reference TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS system_settings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT UNIQUE NOT NULL,
      value TEXT NOT NULL,
      created_at TEXT DEFAULT (datetime('now')),
      updated_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS trade_history (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      symbol TEXT NOT NULL DEFAULT 'UC',
      side TEXT NOT NULL,
      pnl REAL NOT NULL DEFAULT 0.0000,
      reason TEXT,
      closed_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS trade_market (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      price REAL NOT NULL,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS trade_positions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      amount REAL NOT NULL DEFAULT 0.0000,
      margin REAL NOT NULL DEFAULT 0.0000,
      leverage REAL NOT NULL DEFAULT 1.00,
      direction TEXT NOT NULL,
      entry_price REAL NOT NULL DEFAULT 0.0000,
      exit_price REAL,
      position_size REAL NOT NULL DEFAULT 0.0000,
      fee REAL NOT NULL DEFAULT 0.0000,
      liquidation_price REAL,
      pnl REAL,
      status TEXT NOT NULL DEFAULT 'open',
      closed_at TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS trade_prices (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      symbol TEXT NOT NULL DEFAULT 'UC',
      price REAL NOT NULL,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS trades (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      side TEXT NOT NULL,
      leverage REAL NOT NULL DEFAULT 1.00,
      margin REAL NOT NULL DEFAULT 0.0000,
      stop_loss REAL,
      status TEXT NOT NULL DEFAULT 'open',
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS uc_liquidity (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      min_balance REAL NOT NULL DEFAULT 50.00,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS uc_market (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      price REAL NOT NULL,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS uc_positions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      side TEXT NOT NULL,
      margin REAL NOT NULL DEFAULT 0.0000,
      leverage REAL NOT NULL DEFAULT 1.00,
      size REAL,
      entry_price REAL NOT NULL DEFAULT 0.0000,
      close_price REAL,
      exit_price REAL,
      liquidation_price REAL,
      stop_loss REAL,
      pnl REAL,
      close_reason TEXT,
      status TEXT NOT NULL DEFAULT 'open',
      closed_at TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );
  `);

  try {
    db.exec("ALTER TABLE coin_history ADD COLUMN source TEXT");
  } catch (e) {
    // Column already exists
  }

  // Seed default users if empty
  const userCount = db.prepare("SELECT COUNT(*) as count FROM users").get().count;
  if (userCount === 0) {
    // Passwords from database.sql
    const insertUser = db.prepare(`
      INSERT INTO users (id, name, phone, email, password, role, status, apply_status, coins, balance, purchase_balance, coin_cycle_start)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    `);
    
    // System treasury & liquidity
    insertUser.run(1, 'System Treasury', '0000000000', 'system@privateclub.local', '$2y$10$lqQLw25dPWDNZYTG6hg4d.V3zECgAe1mEFiYXuOrn9EtwJL4tvjeO', 'system', 'active', 'approved', 1000000, 1000000, 0);
    insertUser.run(2, 'Liquidity Pool', '0000000001', 'liquidity@privateclub.local', '$2y$10$lqQLw25dPWDNZYTG6hg4d.V3zECgAe1mEFiYXuOrn9EtwJL4tvjeO', 'liquidity', 'active', 'approved', 500000, 500000, 0);
    
    // Admin Angkur (phone: 01788674353)
    insertUser.run(3, 'Admin Angkur', '01788674353', 'angkur490@gmail.com', '$2y$10$lmWNPqoLqhZqzNXqYrGvcOVJTgXmlV.hY2zrkVUe5lydcTA6d9f7S', 'admin', 'active', 'approved', 50000, 50000, 0);
    
    // Main Admin (phone: 01700000000)
    insertUser.run(4, 'Main Admin', '01700000000', 'admin@privateclub.local', '$2y$10$lmWNPqoLqhZqzNXqYrGvcOVJTgXmlV.hY2zrkVUe5lydcTA6d9f7S', 'admin', 'active', 'approved', 50000, 50000, 0);
    
    // Sub Admin (phone: 01711111111)
    insertUser.run(5, 'Senior Sub Admin', '01711111111', 'subadmin@privateclub.local', '$2y$10$V/sqvMeV5TUawteFha8aMeGucworTmEIarXegvTH96ufcec4uuS6q', 'sub_admin', 'active', 'approved', 25000, 25000, 0);
    
    // Demo Member (phone: 01722222222)
    insertUser.run(6, 'Demo Member', '01722222222', 'user@privateclub.local', '$2y$10$0HgJEPYK0xO517rQdAu32OvxIHXeYBECJT3QS47FosiWU035888OG', 'user', 'active', 'approved', 1500, 1500, 0);
  }

  // Seed default products if empty
  const prodCount = db.prepare("SELECT COUNT(*) as count FROM products").get().count;
  if (prodCount === 0) {
    const insertProd = db.prepare(`
      INSERT INTO products (id, name, title, slug, type, price, discount, delivery_time, active)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    `);
    insertProd.run(1, '⚡ Power Click', '⚡ Power Click', 'power-click', 'power', 30.00, 0.00, 'Instant');
    insertProd.run(2, '🚀 Turbo Power Click', '🚀 Turbo Power Click', 'turbo-click', 'turbo', 10.00, 0.00, 'Instant');
    insertProd.run(3, '🔥 Raw Click', '🔥 Raw Click', 'raw-click', 'raw', 10.00, 0.00, 'Instant');
    insertProd.run(4, '🤝 Joint Click', '🤝 Joint Click', 'joint-click', 'joint', 5.00, 0.00, 'Instant');
    insertProd.run(5, '📄 Paper Click', '📄 Paper Click', 'paper-click', 'paper', 0.50, 0.00, 'Instant');
    insertProd.run(6, 'Manta Large Click', 'Manta Large Click', 'manta-large-click', 'manta_large', 70.00, 0.00, 'Instant');
    insertProd.run(7, 'Manta Click', 'Manta Click', 'manta-click', 'manta', 35.00, 0.00, 'Instant');
    insertProd.run(8, '💎 Premium VIP Plan', '💎 Premium VIP Plan', 'premium-vip', 'premium', 300.00, 0.00, 'Instant');
  }

  // Seed default settings
  const insertSetting = db.prepare(`
    INSERT OR IGNORE INTO settings (k, v, id, maintenance) VALUES (?, ?, 1, 0)
  `);
  insertSetting.run('headtail_percent', '80');
  insertSetting.run('maintenance', '0');
  insertSetting.run('premium_price', '300');

  // Seed default system_settings
  const insertSysSetting = db.prepare(`
    INSERT OR IGNORE INTO system_settings (name, value) VALUES (?, ?)
  `);
  insertSysSetting.run('liquidity_floor', '5000');
  insertSysSetting.run('premium_price', '300');
  insertSysSetting.run('headtail_percent', '80');

  // Seed default coupons
  const insertCoupon = db.prepare(`
    INSERT OR IGNORE INTO coupons (id, code, amount, type, status) VALUES (?, ?, ?, ?, 'active')
  `);
  insertCoupon.run(1, 'UNM-REG-VIP888', 100.00, 'apply');
  insertCoupon.run(2, 'UNM-DEP-GOLD99', 500.00, 'deposit');

  // Seed default earn buttons
  const insertEarn = db.prepare(`
    INSERT OR IGNORE INTO earn_buttons (id, title, link, status) VALUES (?, ?, ?, 'active')
  `);
  insertEarn.run(1, 'Watch Sponsor Video', 'https://youtube.com');
  insertEarn.run(2, 'Join Telegram Community', 'https://t.me');
  insertEarn.run(3, 'Visit Partner Channel', 'https://google.com');

  // Seed default notice
  const insertNotice = db.prepare(`
    INSERT OR IGNORE INTO notices (id, message) VALUES (1, 'Welcome to Unmoor Club! All systems are fully operational.')
  `);
  insertNotice.run();

  // Seed market price
  db.exec(`
    INSERT OR IGNORE INTO market_price (id, symbol, price) VALUES (1, 'UC', 100.0000);
    INSERT OR IGNORE INTO market_state (id, symbol, price) VALUES (1, 'UC', 100.0000);
    INSERT OR IGNORE INTO trade_market (id, price) VALUES (1, 100.0000);
    INSERT OR IGNORE INTO trade_prices (id, symbol, price) VALUES (1, 'UC', 100.0000);
    INSERT OR IGNORE INTO uc_liquidity (id, min_balance) VALUES (1, 50.00);
    INSERT OR IGNORE INTO uc_market (id, price) VALUES (1, 100.0000);
  `);
}

bootstrap();

// Database Query Helpers
module.exports = {
  db,
  get(sql, params = []) {
    return db.prepare(sql).get(...params);
  },
  all(sql, params = []) {
    return db.prepare(sql).all(...params);
  },
  run(sql, params = []) {
    return db.prepare(sql).run(...params);
  },
  exec(sql) {
    return db.exec(sql);
  },
  transaction(fn) {
    return (...args) => {
      db.exec("BEGIN IMMEDIATE;");
      try {
        const result = fn(...args);
        db.exec("COMMIT;");
        return result;
      } catch (err) {
        db.exec("ROLLBACK;");
        throw err;
      }
    };
  }
};
