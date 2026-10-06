<?php

/**
 * PHP 内置开发服务器路由（Laravel 10 骨架不再自带 server.php）。
 * 静态文件直接返回，其余请求交给 public/index.php。
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$publicPath = __DIR__ . '/public';
if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false;
}

require_once $publicPath . '/index.php';
