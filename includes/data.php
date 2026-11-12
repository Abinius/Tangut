<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** 索引分片目录（与前端共用的 index_shards） */
function shard_dir(): string
{
    return dirname(__DIR__) . '/index_shards';
}

function manifest(): array
{
    static $m = null;
    if ($m === null) {
        $p = shard_dir() . '/manifest.json';
        $m = is_file($p) ? (json_decode((string) file_get_contents($p), true) ?: []) : [];
    }
    return $m;
}

function shard(string $key): array
{
    static $c = [];
    if (!isset($c[$key])) {
        $p = shard_dir() . '/' . basename($key) . '.json';
        $c[$key] = is_file($p) ? (json_decode((string) file_get_contents($p), true) ?: []) : [];
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

/** 西夏字 / U+ 码位 → 候选（遍历分片，供详情反查） */
function find_by_tangut(string $t): ?array
{
    $norm = strtoupper(preg_replace('/^U\+/i', '', $t) ?? '');
    foreach ((manifest()['shards'] ?? []) as $key) {
        $s = shard((string) $key);
        foreach (($s['entries'] ?? []) as $cands) {
            foreach ($cands as $c) {
                if (($c['tangut'] ?? '') === $t) {
                    return $c;
                }
                $u = strtoupper(preg_replace('/^U\+/i', '', (string) ($c['unicode'] ?? '')) ?? '');
                if ($norm !== '' && $u === $norm) {
                    return $c;
                }
            }
        }
    }
    return null;
}

/** 站点统计（优先读预生成的 stats.json，缺失时遍历分片） */
function site_stats(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $p = shard_dir() . '/stats.json';
    if (is_file($p)) {
        $d = json_decode((string) file_get_contents($p), true);
        if (is_array($d) && isset($d['hanzi'])) {
            return $s = $d;
        }
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
