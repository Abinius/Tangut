<?php
/**
 * PHP 内置服务器路由（本地测试 / 无伪静态的服务器可用 php -S）：
 *   php -S 127.0.0.1:8080 router.php
 *
 * 生产环境用 Apache+Nginx 时此文件可忽略，由 .htaccess 处理。
 */
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// 敏感文件拦截：php -S 会直接返回磁盘上的文件（含 .env），本地开发亦先拒绝
if (preg_match('#(^|/)\.env($|\.)#', $uri)) {
    http_response_code(403);
    return true;
}

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

// /c-0257/ 或 /c-0257 → c.php?c=0257（字头详情页）
if (preg_match('#^/c-([^/]+)/?$#', $uri, $m)) {
    $_GET['c'] = rawurldecode($m[1]);
    require __DIR__ . '/c.php';
    return true;
}

// /about → about.php
if (preg_match('#^/about/?$#', $uri)) {
    require __DIR__ . '/about.php';
    return true;
}

// /radical/ → radical.php（部首检字）
if (preg_match('#^/radical/([^/]+)/([^/]+)/?$#', $uri, $m)) {
    $_GET['system'] = rawurldecode($m[1]);
    $_GET['r'] = rawurldecode($m[2]);
    require __DIR__ . '/radical.php';
    return true;
}
if (preg_match('#^/radical/([^/]+)/?$#', $uri, $m)) {
    $_GET['system'] = rawurldecode($m[1]);
    require __DIR__ . '/radical.php';
    return true;
}
if ($uri === '/radical' || $uri === '/radical/') {
    require __DIR__ . '/radical.php';
    return true;
}

// /stroke/ → stroke.php（笔画检字）
if (preg_match('#^/stroke/([^/]+)/?$#', $uri, $m)) {
    $_GET['n'] = rawurldecode($m[1]);
    require __DIR__ . '/stroke.php';
    return true;
}
if ($uri === '/stroke' || $uri === '/stroke/') {
    require __DIR__ . '/stroke.php';
    return true;
}

// /sitemap.xml → sitemap.php（动态生成）
if ($uri === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

// /api/v1/* → api.php（只读 JSON，path 交给 api.php 分发；/api/v1 亦匹配）
if (preg_match('#^/api/v1/?(.*)$#', $uri, $m)) {
    $_GET['path'] = $m[1];
    require __DIR__ . '/api.php';
    return true;
}

// 其余走首页
require __DIR__ . '/index.php';
return true;
