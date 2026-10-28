<?php
// 共用函式：資料庫連線、JSON 回應、HMAC 權杖認證（W6 PHP API Layer / W8 PDO / W14 Token）
$cfgFile = __DIR__ . '/config.local.php';
if (!file_exists($cfgFile)) { http_response_code(500); header('Content-Type: application/json'); die(json_encode(['error' => '尚未執行 scripts/setup.sh'])); }
$CFG = require $cfgFile;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');                       // W1：CORS 標頭放行 App / 網頁來源
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

function out($data, int $code = 200): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function fail(string $msg, int $code = 400): void { out(['error' => $msg], $code); }

function db(): PDO {
    global $CFG;
    static $pdo = null;
    if (!$pdo) {
        try {
            $pdo = new PDO("mysql:host={$CFG['host']};port=" . ($CFG['port'] ?? 3306) . ";dbname={$CFG['name']};charset=utf8mb4", $CFG['user'], $CFG['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) { fail('資料庫連線失敗', 500); }   // 不把內部訊息回給前端
    }
    return $pdo;
}

function body(): array {
    $j = json_decode(file_get_contents('php://input') ?: '[]', true);
    return is_array($j) ? $j : [];
}

// 無狀態權杖：base64url(payload).HMAC-SHA256
function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function makeToken(array $user): string {
    global $CFG;
    $p = b64u(json_encode(['uid' => (int)$user['user_id'], 'exp' => time() + 7 * 86400]));
    return $p . '.' . b64u(hash_hmac('sha256', $p, $CFG['secret'], true));
}
function authUser(bool $required = true): ?array {
    global $CFG;
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $h, $m)) { if ($required) fail('請先登入', 401); return null; }
    $parts = explode('.', $m[1]);
    if (count($parts) !== 2) fail('權杖無效', 401);
    $good = b64u(hash_hmac('sha256', $parts[0], $CFG['secret'], true));
    if (!hash_equals($good, $parts[1])) fail('權杖無效', 401);
    $pl = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    if (!$pl || $pl['exp'] < time()) fail('登入已過期，請重新登入', 401);
    $st = db()->prepare('SELECT user_id, email, full_name, role FROM Users WHERE user_id = ?');
    $st->execute([$pl['uid']]);
    $u = $st->fetch();
    if (!$u) fail('帳號不存在', 401);
    return $u;
}
function requireRole(array $user, array $roles): void {
    if (!in_array($user['role'], $roles, true)) fail('權限不足', 403);
}
function sessionOf(array $u): array {
    return ['user_id' => (int)$u['user_id'], 'email' => $u['email'], 'full_name' => $u['full_name'], 'role' => $u['role'], 'token' => makeToken($u)];
}
