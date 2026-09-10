<?php
require_once __DIR__ . "/guard.php";
require_once __DIR__ . "/../db.php";

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = ($driver === 'sqlite')
        ? "INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v"
        : "INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)";
    
    $stmt = $db->prepare($sql);
    foreach ($_POST as $k => $v) {
        if ($k === 'submit') continue;
        $stmt->execute([$k, trim((string)$v)]);
    }
    $msg = "Settings updated successfully!";
}

$settings = [];
try {
    $settings = $db->query("SELECT k, v FROM settings WHERE k IS NOT NULL")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings • Admin</title>
<link rel="stylesheet" href="../assets/admin.css">
<style>
.wrap{max-width:700px;margin:30px auto;padding:16px}
.card{background:#020617;border:1px solid #1f2937;border-radius:18px;padding:28px;box-shadow:0 20px 40px rgba(0,0,0,.6)}
h2{margin:0 0 20px;color:#f8fafc;font-size:22px;display:flex;align-items:center;gap:10px}
.form-group{margin-bottom:20px}
label{display:block;margin-bottom:8px;font-size:14px;color:#9ca3af;font-weight:500}
input{width:100%;box-sizing:border-box;padding:12px 14px;background:#0b0f19;border:1px solid #1f2937;border-radius:10px;color:#f8fafc;font-size:15px;outline:none}
input:focus{border-color:#38bdf8}
.btn-save{width:100%;padding:14px;background:#38bdf8;color:#020617;border:none;border-radius:12px;font-weight:600;font-size:16px;cursor:pointer;margin-top:10px;transition:background .2s}
.btn-save:hover{background:#0284c7;color:#fff}
.alert-success{background:rgba(34,197,94,0.15);border:1px solid #22c55e;color:#86efac;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:14px}
.back{display:inline-block;margin-top:20px;color:#38bdf8;text-decoration:none;font-weight:500}
.back:hover{text-decoration:underline}
.help-text{font-size:12px;color:#64748b;margin-top:6px}
</style>
</head>
<body>

<div class="wrap">
  <div class="card">
    <h2>⚙️ System Settings</h2>

    <?php if ($msg): ?>
      <div class="alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="premium_price">💎 Premium VIP Price (৳)</label>
        <input type="number" step="any" id="premium_price" name="premium_price" value="<?= htmlspecialchars($settings['premium_price'] ?? '300') ?>" required>
        <div class="help-text">Base cost for users upgrading to Premium VIP status.</div>
      </div>

      <div class="form-group">
        <label for="headtail_percent">🪙 Head & Tail Payout Return (%)</label>
        <input type="number" step="any" id="headtail_percent" name="headtail_percent" value="<?= htmlspecialchars($settings['headtail_percent'] ?? '80') ?>" required>
        <div class="help-text">Win percentage multiplier for coin flip earnings.</div>
      </div>

      <button type="submit" class="btn-save">Save Settings</button>
    </form>

    <a class="back" href="dashboard.php">← Back to Admin</a>
  </div>
</div>

</body>
</html>

