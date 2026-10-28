<?php
require __DIR__ . '/lib.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$path = preg_replace('#^api/#', '', $path);
$route = "$method $path";

switch ($route) {
    case 'GET health':
        out(['ok' => true, 'time' => date('c')]);

    // ---------- 認證 ----------
    case 'POST register': {
        $b = body();
        $email = strtolower(trim($b['email'] ?? ''));
        $pw = (string)($b['password'] ?? '');
        $name = trim($b['fullName'] ?? '');
        // 前端驗證不可信：後端必須再驗一次（W1 / W2 課後思辨）
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) fail('Email 格式不正確');
        if (strlen($pw) < 6 || strlen($pw) > 64) fail('密碼長度需為 6–64 碼');
        if ($name === '' || mb_strlen($name) > 50) fail('姓名需為 1–50 字');
        $st = db()->prepare('SELECT 1 FROM Users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) fail('此 Email 已被註冊', 409);
        db()->prepare('INSERT INTO Users (email, password_hash, full_name) VALUES (?,?,?)')
            ->execute([$email, password_hash($pw, PASSWORD_BCRYPT), $name]);   // 一律 customer，角色不由前端決定
        $id = (int)db()->lastInsertId();
        out(sessionOf(['user_id' => $id, 'email' => $email, 'full_name' => $name, 'role' => 'customer']), 201);
    }
    case 'POST login': {
        $b = body();
        $st = db()->prepare('SELECT * FROM Users WHERE email = ?');
        $st->execute([strtolower(trim($b['email'] ?? ''))]);
        $u = $st->fetch();
        if (!$u || !password_verify((string)($b['password'] ?? ''), $u['password_hash'])) fail('帳號或密碼錯誤', 401);
        out(sessionOf($u));
    }
    case 'GET me':
        out(authUser());

    // ---------- 商品 ----------
    case 'GET categories':
        out(db()->query('SELECT category_id, category_name, description FROM Categories ORDER BY category_id')->fetchAll());

    case 'GET products': {
        // W3/W4：篩選交給資料庫（WHERE / LIKE），分頁用 LIMIT + OFFSET = (頁碼-1) × 每頁筆數
        $q = trim($_GET['q'] ?? '');
        $cat = (int)($_GET['category_id'] ?? 0);
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $where = []; $args = [];
        if ($q !== '') { $where[] = 'name LIKE ?'; $args[] = '%' . $q . '%'; }
        if ($cat > 0) { $where[] = 'category_id = ?'; $args[] = $cat; }
        $sql = 'SELECT product_id, category_id, name, description, price, stock_quantity, image_url FROM Products'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY product_id LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit);
        $st = db()->prepare($sql);
        $st->execute($args);
        out($st->fetchAll());
    }
    case 'POST products': {
        $u = authUser();
        requireRole($u, ['admin', 'manager']);
        $b = body();
        $name = trim($b['name'] ?? '');
        $price = $b['price'] ?? null;
        $stock = $b['stock_quantity'] ?? null;
        $catId = (int)($b['category_id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 100) fail('商品名稱需為 1–100 字');
        if (!is_numeric($price) || $price < 0 || !preg_match('/^\d+(\.\d{1,2})?$/', (string)$price)) fail('價格格式錯誤');
        if (!is_numeric($stock) || (int)$stock < 0 || (string)(int)$stock !== (string)$stock) fail('庫存必須是非負整數');
        $chk = db()->prepare('SELECT 1 FROM Categories WHERE category_id = ?');
        $chk->execute([$catId]);
        if (!$chk->fetch()) fail('分類不存在');
        db()->prepare('INSERT INTO Products (category_id, name, description, price, stock_quantity, image_url) VALUES (?,?,?,?,?,?)')
            ->execute([$catId, $name, (string)($b['description'] ?? ''), $price, (int)$stock, substr((string)($b['image_url'] ?? ''), 0, 255)]);
        out(['product_id' => (int)db()->lastInsertId()], 201);
    }

    // ---------- 購物車 ----------
    case 'GET cart':
        out(cartOf(authUser()['user_id']));

    case 'POST cart/sync': {
        // W5：恢復連線後，把本機購物車批次寫回雲端。本機持有最新狀態，因此以「整批取代」處理，
        // 在同一個交易內完成：要嘛全成功、要嘛全回滾（W4）。
        $u = authUser();
        $items = body()['items'] ?? [];
        if (!is_array($items) || count($items) > 200) fail('items 格式錯誤');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM Cart WHERE user_id = ?')->execute([$u['user_id']]);
            $ins = $pdo->prepare('INSERT INTO Cart (user_id, product_id, quantity) SELECT ?, product_id, ? FROM Products WHERE product_id = ?
                                  ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)');
            foreach ($items as $it) {
                $pid = (int)($it['product_id'] ?? 0); $qty = (int)($it['quantity'] ?? 0);
                if ($pid > 0 && $qty > 0) $ins->execute([$u['user_id'], min($qty, 999), $pid]);
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); fail('同步失敗', 500); }
        out(cartOf($u['user_id']));
    }
    case 'POST cart/clear':
        db()->prepare('DELETE FROM Cart WHERE user_id = ?')->execute([authUser()['user_id']]);
        out([]);

    default:
        fail('找不到此 API', 404);
}

function cartOf(int $uid): array {
    $st = db()->prepare('SELECT c.product_id, c.quantity, c.updated_at AS added_at FROM Cart c WHERE c.user_id = ? ORDER BY c.cart_id');
    $st->execute([$uid]);
    return array_map(fn($r) => ['product_id' => (int)$r['product_id'], 'quantity' => (int)$r['quantity'], 'added_at' => $r['added_at'], 'synced' => true], $st->fetchAll());
}
