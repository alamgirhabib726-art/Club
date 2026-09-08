<?php
require_once "guard.php";
require_once "../db.php";

if ($_SERVER['REQUEST_METHOD']==='POST') {
    foreach($_POST as $k=>$v){
        $db->prepare("
          INSERT INTO settings (k,v) VALUES (?,?)
          ON DUPLICATE KEY UPDATE v=VALUES(v)
        ")->execute([$k,$v]);
    }
}

$settings = $db->query("SELECT k, v FROM settings")
->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!doctype html><html><body>
<h3>⚙️ Settings</h3>
<form method="post">
<input name="premium_price" value="<?=$settings['premium_price'] ?? 300?>">
<input name="headtail_percent" value="<?=$settings['headtail_percent'] ?? 80?>">
<button>Save</button>
</form>
</body></html>