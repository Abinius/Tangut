<?php
declare(strict_types=1);

/**
 * 统一配置：读取站点根目录 .env（KEY=VALUE，# 注释，支持引号）。
 * 修改 .env 后刷新页面即全站生效，无需重新构建。
 */
function tangut_load_env(string $file): array
{
    $out = [];
    if (!is_file($file)) {
        return $out;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) >= 2 && (($v[0] === '"' && $v[-1] === '"') || ($v[0] === "'" && $v[-1] === "'"))) {
            $v = substr($v, 1, -1);
        }
        $out[trim($k)] = $v;
    }
    return $out;
}

$ENV = tangut_load_env(dirname(__DIR__) . '/.env');

function cfg(string $key, string $default = ''): string
{
    global $ENV;
    return $ENV[$key] ?? $default;
}

/** 友链：从 .env FRIEND_LINKS 解析「站名|URL, 站名2|URL2」，返回 [['name'=>..,'url'=>..], ..]；空/非法项跳过 */
function friend_links(): array
{
    $raw = cfg('FRIEND_LINKS', '');
    if ($raw === '') {
        return [];
    }
    $out = [];
    foreach (explode(',', $raw) as $pair) {
        $parts = explode('|', $pair, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $name = trim($parts[0]);
        $url = trim($parts[1]);
        if ($name === '' || $url === '' || !preg_match('#^https?://#i', $url)) {
            continue;
        }
        $out[] = ['name' => $name, 'url' => $url];
    }
    return $out;
}

/** HTML 转义（接受数值：JSON 数组键如卷号/韵类会解析为 int，转字符串兜底） */
function h(string|int|float|null $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 基础限流：按 IP 窗口计数（APCu 优先，回退文件计数）。超限返回 true，调用方应返回 429。 */
function rate_limited(): bool
{
    $limit = (int) cfg('RATE_LIMIT_PER_MIN', '60');
    if ($limit <= 0) {
        return false;
    }
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $window = 60;
    $now = time();

    if (function_exists('apcu_fetch')) {
        $key = 'tangut_rl:' . $ip;
        $start = (int) apcu_fetch($key . ':t', $ok);
        if (!$ok || $now - $start >= $window) {
            apcu_store($key . ':t', $now, $window);
            apcu_store($key, 1, $window);
            return false;
        }
        $n = (int) apcu_inc($key);
        if ($n === false) {
            apcu_store($key, 1, $window);
            $n = 1;
        }
        return $n > $limit;
    }

    $dir = rtrim(sys_get_temp_dir(), '/\\') . '/tangut-ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return false;
    }
    $f = $dir . '/' . md5($ip);
    $fh = @fopen($f, 'c+');
    if ($fh === false) {
        return false;
    }
    flock($fh, LOCK_EX);
    $d = json_decode((string) stream_get_contents($fh), true);
    $n = is_array($d) ? (int) ($d['n'] ?? 0) : 0;
    $ts = is_array($d) ? (int) ($d['t'] ?? 0) : 0;
    if ($now - $ts >= $window) {
        $n = 0;
        $ts = $now;
    }
    $n++;
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode(['n' => $n, 't' => $ts]));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $n > $limit;
}

define('SITE_URL', rtrim(cfg('SITE_URL', 'https://voue.cn'), '/'));
define('BRAND', cfg('BRAND', 'TANGUT'));
define('BRAND_TAGLINE', cfg('BRAND_TAGLINE', '蕃汉合时掌中珠 · Tangut Dictionary'));
define('SITE_NAME', cfg('SITE_NAME', 'Tangut Dictionary'));
define('SITE_TITLE', cfg('SITE_TITLE', SITE_NAME . ' — 汉字→西夏文查询'));
define('SITE_DESCRIPTION', cfg('SITE_DESCRIPTION', '免费公益工具：输入姓名、姓氏或任意汉字，查看西夏文候选、拟音与义项依据。'));
define('OG_IMAGE', cfg('OG_IMAGE', '/og-image.png'));
define('OG_TYPE', cfg('OG_TYPE', 'website'));
define('TWITTER_CARD', cfg('TWITTER_CARD', 'summary_large_image'));
define('TANGUT_CORE_FONT', cfg('TANGUT_CORE_FONT', '/fonts/tangut_core-be5a06e05246.woff2'));
define('TANGUT_REST_FONT', cfg('TANGUT_REST_FONT', '/fonts/tangut_rest-cba4d927d1e4.woff2'));
define('TANGUT_FIX_FONT', cfg('TANGUT_FIX_FONT', '/fonts/tangut_fix-bc6408ee5bc4.woff2'));
define('TANGUT_IDSOPS_FONT', cfg('TANGUT_IDSOPS_FONT', '/fonts/tangut_idsops-8b8d5c156180.woff2'));
define('ICP_BEIAN', cfg('ICP_BEIAN', ''));
define('ICP_URL', cfg('ICP_URL', 'https://beian.miit.gov.cn/'));
define('GONGAN_BEIAN', cfg('GONGAN_BEIAN', ''));
define('GONGAN_URL', cfg('GONGAN_URL', 'https://beian.mps.gov.cn/'));
define('ENV_DATA_VERSION', cfg('DATA_VERSION', ''));
define('DISCLAIMER_ZH', cfg('DISCLAIMER_ZH', '西夏文与汉字非一一对应，多候选为常态；候选项据 tangutdata 中文释义反查 + 同音替代生成，tier 为自动分层近似。'));
define('DISCLAIMER_EN', cfg('DISCLAIMER_EN', 'Tangut-Chinese correspondence is many-to-many; candidates by tangutdata gloss reverse-lookup + homophone substitution; tier auto-layered (approximate).'));
define('LICENSE_TEXT', cfg('LICENSE_TEXT', '映射数据 tangutdata (MIT) 开放；释义版权归原出版社，引用须注明出处。'));
define('COLOPHON_PAPER', cfg('COLOPHON_PAPER', 'WARM WHITE #FAFAF8'));
define('COLOPHON_TYPE', cfg('COLOPHON_TYPE', 'LIGHT GROTESK + NOTO SERIF TANGUT'));
define('OG_TITLE', cfg('OG_TITLE', SITE_TITLE));
define('OG_DESCRIPTION', cfg('OG_DESCRIPTION', SITE_DESCRIPTION));
