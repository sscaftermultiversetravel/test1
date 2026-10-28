<?php
// 首次安裝（供打包版 Windows 使用）：建庫建表、建立低權限帳號、寫入設定檔、建立管理員。
// 用法：php install.php --port=3307 --rootpw=XXXX [--out=ADMIN_LOGIN.txt]
$o = getopt('', ['host::', 'port::', 'rootpw::', 'out::']);
$host = $o['host'] ?? '127.0.0.1'; $port = (int)($o['port'] ?? 3306); $rootpw = $o['rootpw'] ?? '';
$root = dirname(__DIR__);
$cfgFile = __DIR__ . '/config.local.php';

$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', $rootpw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if (file_exists($cfgFile)) { $cfg = require $cfgFile; }
else {
    $cfg = ['host' => $host, 'port' => $port, 'name' => 'shop_db', 'user' => 'shop_app',
            'pass' => bin2hex(random_bytes(16)), 'secret' => bin2hex(random_bytes(32))];
}

$exists = $pdo->query("SHOW DATABASES LIKE 'shop_db'")->fetch();
if (!$exists) $pdo->exec(file_get_contents($root . '/database/schema.sql'));    // 原始位元組讀入，避免編碼問題

// W3：API 只用低權限帳號，不用 root
$pw = $pdo->quote($cfg['pass']);
foreach (['localhost', '127.0.0.1'] as $h) {
    $pdo->exec("CREATE USER IF NOT EXISTS 'shop_app'@'$h' IDENTIFIED BY $pw");
    $pdo->exec("ALTER USER 'shop_app'@'$h' IDENTIFIED BY $pw");
    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON shop_db.* TO 'shop_app'@'$h'");
}
$pdo->exec('FLUSH PRIVILEGES');

$pdo->exec('USE shop_db');
if ((int)$pdo->query('SELECT COUNT(*) FROM Categories')->fetchColumn() === 0) $pdo->exec(file_get_contents($root . '/database/seed.sql'));

file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfg, true) . ";\n");

// 管理員：只在還沒有任何 admin 時建立，密碼隨機產生並寫進檔案
if ((int)$pdo->query("SELECT COUNT(*) FROM Users WHERE role='admin'")->fetchColumn() === 0) {
    $apw = substr(strtr(base64_encode(random_bytes(12)), '+/=', 'abc'), 0, 12);
    $pdo->prepare('INSERT INTO Users (email, password_hash, full_name, role) VALUES (?,?,?,"admin")')
        ->execute(['admin@shop.test', password_hash($apw, PASSWORD_BCRYPT), '系統管理員']);
    $out = $o['out'] ?? ($root . '/ADMIN_LOGIN.txt');
    file_put_contents($out, "管理員帳號：admin@shop.test\n管理員密碼：$apw\n（登入後請妥善保管此檔，或刪除它）\n");
    echo "已建立管理員，資訊寫入 $out\n";
}
echo "安裝完成\n";
