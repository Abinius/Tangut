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

/** HTML 转义 */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

define('SITE_URL', rtrim(cfg('SITE_URL', 'https://voue.cn'), '/'));
define('BRAND', cfg('BRAND', 'TANGUT'));
define('BRAND_TAGLINE', cfg('BRAND_TAGLINE', '汉字 → 西夏文对应关系查询'));
define('SITE_NAME', cfg('SITE_NAME', 'TANGUT Lab'));
define('SITE_TITLE', cfg('SITE_TITLE', SITE_NAME . ' — 汉字→西夏文查询'));
define('SITE_DESCRIPTION', cfg('SITE_DESCRIPTION', '免费公益工具：输入姓名、姓氏或任意汉字，查看西夏文候选、拟音与义项依据。'));
define('OG_IMAGE', cfg('OG_IMAGE', '/og-image.png'));
define('ICP_BEIAN', cfg('ICP_BEIAN', ''));
define('ICP_URL', cfg('ICP_URL', 'https://beian.miit.gov.cn/'));
define('GONGAN_BEIAN', cfg('GONGAN_BEIAN', ''));
define('GONGAN_URL', cfg('GONGAN_URL', 'https://beian.mps.gov.cn/'));
define('ENV_DATA_VERSION', cfg('DATA_VERSION', ''));
