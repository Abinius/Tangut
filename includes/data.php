<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** 索引分片目录（与前端共用的 index_shards） */
function shard_dir(): string
{
    return dirname(__DIR__) . '/index_shards';
}

/** 读取并解析 JSON 文件；缺失或解析失败时记 error_log 并标记 500，返回 null（调用方兜底为空） */
function read_json(string $path): ?array
{
    if (!is_file($path)) {
        error_log("tangut: missing data file: {$path}");
        if (!headers_sent()) {
            http_response_code(500);
        }
        return null;
    }
    $d = json_decode((string) file_get_contents($path), true);
    if (!is_array($d)) {
        error_log("tangut: invalid JSON in {$path}: " . json_last_error_msg());
        if (!headers_sent()) {
            http_response_code(500);
        }
        return null;
    }
    return $d;
}

function manifest(): array
{
    static $m = null;
    if ($m === null) {
        $m = read_json(shard_dir() . '/manifest.json') ?? [];
    }
    return $m;
}

function shard(string $key): array
{
    static $c = [];
    if (!isset($c[$key])) {
        $c[$key] = read_json(shard_dir() . '/' . basename($key) . '.json') ?? [];
    }
    return $c[$key];
}

/** 数据版本：以数据自身为准，回退 .env */
function data_version(): string
{
    $v = (string) (manifest()['data_version'] ?? '');
    return $v !== '' ? $v : ENV_DATA_VERSION;
}

/** 单字查询：manifest 映射 → 对应分片 */
function lookup_hanzi(string $hanzi): array
{
    $m = manifest();
    $key = $m['hanzi_to_shard'][$hanzi] ?? null;
    if (!$key) {
        return [];
    }
    $s = shard((string) $key);
    return $s['entries'][$hanzi] ?? [];
}

/** 整串查询（逐字） */
function lookup_text(string $text): array
{
    $out = [];
    if (!preg_match_all('/./us', $text, $mm)) {
        return $out;
    }
    foreach ($mm[0] as $ch) {
        $out[] = ['char' => $ch, 'candidates' => lookup_hanzi($ch)];
    }
    return $out;
}

/** 李范文编号 → 候选（预构建 lfw_index，O(1)） */
function find_by_lfw(string $lfwId): ?array
{
    static $idx = null;
    if ($idx === null) {
        $d = read_json(shard_dir() . '/lfw_index.json');
        $idx = ($d['entries'] ?? []);
    }
    $norm = ltrim($lfwId, '0');
    return ($idx[$norm] ?? $idx[$lfwId] ?? null);
}

/** 字头详情：LFW 编号或码位 → detail JSON（O(1) 索引 + 单文件读取） */
function find_detail(string $key): ?array
{
    static $idx = null;
    if ($idx === null) {
        $idx = read_json(shard_dir() . '/detail_index.json') ?? [];
    }
    // 尝试 LFW 编号（归一化：去 L/l 前缀 + 去前导零）
    $norm = ltrim($key, 'Ll');
    $norm = ltrim($norm, '0');
    $fname = $idx['by_lfw'][$norm] ?? $idx['by_lfw'][$key] ?? null;

    // 尝试码位（U+1760D、U1760D、1760D → 统一为 U+1760D）
    if (!$fname) {
        $cp = strtoupper(ltrim($key));
        if (preg_match('/^U?\+?([0-9A-F]{4,6})$/i', $cp, $m)) {
            $fname = $idx['by_codepoint']['U+' . strtoupper($m[1])] ?? null;
        }
        if (!$fname) {
            $fname = $idx['by_codepoint'][$key] ?? null;
        }
    }

    if (!$fname) {
        return null;
    }
    return read_json(dirname(__DIR__) . '/detail/' . basename($fname) . '.json');
}

/** 站点统计（优先读预生成的 stats.json，缺失或损坏时遍历分片重算） */
function site_stats(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $p = shard_dir() . '/stats.json';
    $d = is_file($p) ? json_decode((string) file_get_contents($p), true) : null;
    if (is_array($d) && isset($d['hanzi'])) {
        return $s = $d;
    }
    if ($d === null && is_file($p)) {
        error_log("tangut: invalid JSON in {$p}: " . json_last_error_msg());
    }
    $m = manifest();
    $total = 0;
    foreach (($m['shards'] ?? []) as $key) {
        $sd = shard((string) $key);
        foreach (($sd['entries'] ?? []) as $cands) {
            $total += count($cands);
        }
    }
    return $s = ['hanzi' => (int) ($m['total_hanzi'] ?? 0), 'candidates' => $total, 'data_version' => data_version()];
}

/** tier 中文标签 */
function tier_label(string $tier): string
{
    return [
        'P0' => '释义即该字',
        'P1' => '首义项含该字',
        'P2' => '释义含该字',
        'P3' => '同音替代',
    ][$tier] ?? '';
}
