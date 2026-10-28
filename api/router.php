<?php
// php -S 用的路由：存在的靜態檔（打包版的網頁）直接交給內建伺服器，其餘請求交給 API。
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if (!str_ends_with($path, '.php') && ($path === '/' ? is_file($_SERVER['DOCUMENT_ROOT'] . '/index.html') : is_file($file))) {
    return false;
}
require __DIR__ . '/index.php';
