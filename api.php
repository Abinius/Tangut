<?php
declare(strict_types=1);

/**
 * 蕃汉 TANGUT Lab REST API（只读 JSON，PRD A-01~A-06）。
 * 路由：.htaccess 将 ^api/v1/(.*)$ 重写为 api.php?path=$1；router.php 同映射。
 * 契约对齐 PRD 6.10 与 Cloudflare Worker 实现（字段、data_version 贯通）。
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/data.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function api_json(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'GET') {
    api_json(['error' => 'method not allowed', 'data_version' => data_version()], 405);
}

// A-05：宽松限流（与网页查询同池，60 次/分/IP，超限 429）
if (rate_limited()) {
    header('Retry-After: 60');
    api_json(['error' => 'rate limited', 'retry_after' => 60, 'data_version' => data_version()], 429);
}

$dv = data_version();
$path = trim((string) ($_GET['path'] ?? ''), '/');

// 自描述（A-06）：/api/v1 或 /api/v1/
if ($path === '') {
    api_json([
        'service' => 'TangutLab API',
        'data_version' => $dv,
        'mode' => 'PHP 只读 JSON 端点（PRD A-01~A-06）',
        'endpoints' => [
            'GET /api/v1/lookup?char=X — 单字查询',
            'GET /api/v1/lookup?text=XYZ — 整串查询（逐字，≤64 字）',
            'GET /api/v1/character/{lfw_id} — 字头详情',
        ],
        'disclaimer' => DISCLAIMER_ZH, 'disclaimer_en' => DISCLAIMER_EN, 'license' => LICENSE_TEXT,
    ]);
}

// A-01 / A-02：/api/v1/lookup?char=X 或 ?text=XYZ
if ($path === 'lookup') {
    $char = (string) ($_GET['char'] ?? '');
    $text = (string) ($_GET['text'] ?? '');
    if ($char !== '' && mb_strlen($char, 'UTF-8') === 1) {
        $cands = lookup_hanzi($char);
        api_json([
            'query' => $char, 'count' => count($cands), 'candidates' => $cands,
            'disclaimer' => DISCLAIMER_ZH, 'disclaimer_en' => DISCLAIMER_EN,
            'license' => LICENSE_TEXT, 'data_version' => $dv,
        ]);
    }
    if ($text !== '') {
        if (mb_strlen($text, 'UTF-8') > 64) {
            api_json(['error' => 'text too long (max 64 chars)', 'data_version' => $dv], 400);
        }
        $items = [];
        if (preg_match_all('/./us', $text, $mm)) {
            foreach ($mm[0] as $ch) {
                $items[] = ['char' => $ch, 'candidates' => lookup_hanzi($ch)];
            }
        }
        api_json([
            'query' => $text, 'count' => count($items), 'results' => $items,
            'disclaimer' => DISCLAIMER_ZH, 'disclaimer_en' => DISCLAIMER_EN,
            'license' => LICENSE_TEXT, 'data_version' => $dv,
        ]);
    }
    api_json(['error' => 'missing param: char (single) or text', 'data_version' => $dv], 400);
}

// A-03：/api/v1/character/{lfw_id}
if (preg_match('#^character/(.+)$#', $path, $m)) {
    $lfw = rawurldecode($m[1]);
    $c = find_by_lfw($lfw);
    if (!$c) {
        api_json(['error' => 'not found', 'lfw_id' => mb_substr($lfw, 0, 64, 'UTF-8'), 'data_version' => $dv], 404);
    }
    api_json(['character' => $c, 'source' => 'tangutdata (MIT)', 'data_version' => $dv]);
}

api_json(['error' => 'not found', 'path' => $path, 'data_version' => $dv], 404);
