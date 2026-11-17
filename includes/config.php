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
define('ASSET_VER', cfg('ASSET_VER', '1'));
define('DATA_SOURCE_FONT_URL', cfg('DATA_SOURCE_FONT_URL', 'https://github.com/notofonts/tangut'));
define('BIBLIO_WENHAI', cfg('BIBLIO_WENHAI', '3064'));
define('BIBLIO_YINTONG', cfg('BIBLIO_YINTONG', '6133'));
define('BIBLIO_XIAHAN', cfg('BIBLIO_XIAHAN', '6074'));

const FESTIVAL_KEYS = ['spring', 'dragon', 'midautumn'];

/**
 * 当前激活的节日主题键（spring|dragon|midautumn），无则 null。
 * 优先 POSTER_FESTIVAL_OVERRIDE（none=强制默认池；空=自动）；自动按预置农历日期表 ±3 天窗口判断。
 */
function festival_active(?int $now = null): ?string
{
    if ($now === null) {
        $now = time();
    }
    $ov = strtolower(trim(cfg('POSTER_FESTIVAL_OVERRIDE', '')));
    if ($ov === 'none') {
        return null;
    }
    if (in_array($ov, FESTIVAL_KEYS, true)) {
        return $ov;
    }
    // 预置农历节日公历日期表（年 => 节日键 => 公历日期）；表外年份自动回退默认池
    $table = [
        2026 => ['spring' => '2026-02-17', 'dragon' => '2026-06-19', 'midautumn' => '2026-09-25'],
        2027 => ['spring' => '2027-02-06', 'dragon' => '2027-06-09', 'midautumn' => '2027-09-15'],
        2028 => ['spring' => '2028-01-26', 'dragon' => '2028-05-28', 'midautumn' => '2028-10-03'],
        2029 => ['spring' => '2029-02-13', 'dragon' => '2029-06-16', 'midautumn' => '2029-09-22'],
        2030 => ['spring' => '2030-02-03', 'dragon' => '2030-06-05', 'midautumn' => '2030-09-11'],
    ];
    $y = (int) date('Y', $now);
    if (!isset($table[$y])) {
        return null;
    }
    $today = (int) strtotime('today', $now);
    // 重叠时优先级：春节 > 中秋 > 端午
    foreach (['spring', 'midautumn', 'dragon'] as $k) {
        $d = $table[$y][$k] ?? '';
        if ($d === '') {
            continue;
        }
        $fd = strtotime($d);
        if ($fd === false) {
            continue;
        }
        if ($today >= $fd - 3 * 86400 && $today <= $fd + 3 * 86400) {
            return $k;
        }
    }
    return null;
}

/**
 * 海报背景池：['default'=>[url...], 'festival'=>[key=>[url...]], 'active'=>?string]。
 * default 来自 POSTER_BG（| 分隔）；festival 按 POSTER_BG_FESTIVAL_DIR 下 spring|dragon|midautumn 子目录自动收集 *.webp；
 * 未配置返回空，share.js 回退硬编码池。按请求静态缓存。
 */
function poster_bg_pool(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $default = array_values(array_filter(array_map('trim', explode('|', cfg('POSTER_BG', '')))));
    $festival = [];
    $base = rtrim(cfg('POSTER_BG_FESTIVAL_DIR', ''), '/');
    if ($base !== '' && $base[0] === '/') {
        $fs = dirname(__DIR__) . $base;
        foreach (FESTIVAL_KEYS as $k) {
            $dir = $fs . '/' . $k;
            $files = is_dir($dir) ? glob($dir . '/*.webp') : [];
            if ($files) {
                $festival[$k] = array_values(array_map(
                    static fn(string $f): string => $base . '/' . $k . '/' . basename($f),
                    $files
                ));
            }
        }
    }
    return $c = ['default' => $default, 'festival' => $festival, 'active' => festival_active()];
}
