<?php
/**
 * PHP 内置服务器路由（本地测试 / 无伪静态的服务器可用 php -S）：
 *   php -S 127.0.0.1:8080 router.php
 *
 * 生产环境用 Apache+Nginx 时此文件可忽略，由 .htaccess 处理。
 */
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// 静态资源：交给内置服务器直接返回
if ($uri !== '/' && $uri !== '' && is_file(__DIR__ . $uri)) {
    return false;
}

// /x-青/ 或 /x-青 → x.php?h=青
if (preg_match('#^/x-([^/]+)/?$#', $uri, $m)) {
    $_GET['h'] = rawurldecode($m[1]);
    require __DIR__ . '/x.php';
    return true;
}

// /about → about.php
if (preg_match('#^/about/?$#', $uri)) {
    require __DIR__ . '/about.php';
    return true;
}

// /sitemap.xml → sitemap.php（动态生成）
if ($uri === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

// 其余走首页
require __DIR__ . '/index.php';
return true;
