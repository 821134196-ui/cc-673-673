<?php

/**
 * 开发服务器路由脚本（供 `php -S 127.0.0.1:8000 -t public server_dev.php` 使用）。
 * 静态文件直接返回，其余请求交给 public/index.php（Laravel）。
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__.'/public'.$uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

require __DIR__.'/public/index.php';
