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

/** 拼音去声调 + 拆声母/韵母（近音分级用） */
function pinyin_split(string $py): array
{
    $s = preg_replace('/[āáǎà]/u', 'a', $py);
    $s = preg_replace('/[ēéěè]/u', 'e', $s);
    $s = preg_replace('/[īíǐì]/u', 'i', $s);
    $s = preg_replace('/[ōóǒò]/u', 'o', $s);
    $s = preg_replace('/[ūúǔù]/u', 'u', $s);
    $s = preg_replace('/[ǖǘǚǜ]/u', 'ü', $s);
    foreach (['zh','ch','sh','b','p','m','f','d','t','n','l','g','k','h','j','q','x','r','z','c','s','y','w'] as $i) {
        if (str_starts_with($s, $i)) {
            return [$i, substr($s, strlen($i))];
        }
    }
    return ['', $s];
}

/** 韵母 → 近音韵族（bin~ben~bing 同族，供娱乐级近音） */
function pinyin_fam(string $final): string
{
    foreach ([
        ['a', ['a','ia','ua']], ['o', ['o','uo']], ['e', ['e','ie','üe']], ['i', ['i','ü']], ['u', ['u']],
        ['ai', ['ai','uai']], ['ei', ['ei','ui']], ['ao', ['ao','iao']], ['ou', ['ou','iu']],
        ['an', ['an','ian','uan','üan']], ['en', ['en','in','un','ün']], ['ang', ['ang','iang','uang']], ['eng', ['eng','ing']],
    ] as $fam) {
        if (in_array($final, $fam[1], true)) {
            return $fam[0];
        }
    }
    return $final;
}

/**
 * 拼音近似（方案 A·分级近音，娱乐参考级）：
 * L1 精确同音 → L2 同声母+同韵族 → L3 同韵 → L4 同声母。
 * 返回候选数组（hanzi=查询字, homophone=同音/近音来源字, label=分级标签）。
 */
function pinyin_fallback(string $char, int $limit = 6): array
{
    static $idx = null;
    if ($idx === null) {
        $idx = read_json(shard_dir() . '/pinyin_index.json') ?? ['to' => [], 'exact' => [], 'near' => []];
    }
    $py = (string) ($idx['to'][$char] ?? '');
    if ($py === '') {
        return [];
    }
    $picked = [];
    $addFrom = static function (array $chars, string $label) use (&$picked, $char, $limit): void {
        foreach ($chars as $h) {
            if ($h === $char) {
                continue;
            }
            $cands = lookup_hanzi($h);
            if (!$cands) {
                continue;
            }
            foreach ($cands as $top) {
                $k = (string) ($top['tangut'] ?? '');
                if ($k === '' || isset($picked[$k])) {
                    continue;
                }
                $picked[$k] = [
                    'hanzi'     => $char,
                    'homophone' => $h,
                    'label'     => $label,
                    'tangut'    => $k,
                    'gong'      => (string) ($top['gong'] ?? ''),
                    'gloss_zh'  => (string) ($top['gloss_zh'] ?? ''),
                    'tier'      => 'P3',
                    'lfw_id'    => (string) ($top['lfw_id'] ?? ''),
                ];
                if (count($picked) >= $limit) {
                    return;
                }
                break;
            }
            if (count($picked) >= $limit) {
                return;
            }
        }
    };
    $addFrom($idx['exact'][$py] ?? [], '同音');
    [$i, $f] = pinyin_split($py);
    if ($i !== '') {
        $addFrom($idx['near'][$i . '|' . pinyin_fam($f)] ?? [], '近音');
    }
    $addFrom($idx['near']['f:' . $f] ?? [], '同韵');
    if ($i !== '') {
        $addFrom($idx['near']['i:' . $i] ?? [], '同声母');
    }
    return array_values($picked);
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

/**
 * 统一搜索分发器（首页/词组等搜索框共用）：
 * 按输入形态返回跳转 URL，无法判定时返回 null 交由各框自身语境处理。
 * 分派顺序（工单 T2，消除 LFW/四角号歧义）：
 *   1. 显式 LFW 编号（LFW1141 / L1141 / #1141）→ 字头详情
 *   2. 显式码位（U+1760D / U1760D / u1760d）→ /c-u{hex}/
 *   3. 裸 5–6 位十六进制 ≥ U+17000（必非 LFW，无歧义）→ 码位
 *   4. 单个西夏文字 → 码位
 *   5. 完整四角号「XXXX YY」→ 该分组
 *   6. 4 位数字：四角号前缀优先，未命中再看 LFW（附 ?from=lfw 标记）
 *   7. 拉丁字母串（≥2 字符）→ 英文/词组搜索（$routeToWord=false 时不跳，供 word 页自身语境）
 */
function search_target(string $q, bool $routeToWord = true): ?string
{
    $q = trim($q);
    if ($q === '') {
        return null;
    }

    // 1. 显式 LFW 编号：LFW1141 / L1141 / #1141（前缀必填，避免裸 4 位数字被当 LFW）
    if (preg_match('/^(?:lfw|l|#)\s*(\d{1,4})$/i', $q, $m)) {
        return find_detail($m[1]) !== null ? '/c-' . $m[1] . '/' : null;
    }

    // 2. 显式码位：U+1760D / U1760D / u1760d / u+1760d
    if (preg_match('/^U\+?([0-9A-F]{4,6})$/i', $q, $m)) {
        $hex = strtolower($m[1]);
        return find_detail('U+' . strtoupper($m[1])) !== null ? '/c-u' . $hex . '/' : null;
    }

    // 3. 裸 5–6 位十六进制 ≥ U+17000（LFW 编号最大 4 位，必是码位，无歧义）
    if (preg_match('/^([0-9A-F]{5,6})$/i', $q, $m) && hexdec($m[1]) >= 0x17000) {
        $hex = strtolower($m[1]);
        return find_detail('U+' . strtoupper($m[1])) !== null ? '/c-u' . $hex . '/' : null;
    }

    // 4. 单个西夏文字（U+17000–U+18AFF）→ 转码位 → 字头详情（/c-u{hex} 无特殊字符）
    if (mb_strlen($q, 'UTF-8') === 1) {
        $cp = mb_ord($q, 'UTF-8');
        if ($cp !== false && $cp >= 0x17000 && $cp <= 0x18AFF) {
            $hex = strtolower(dechex($cp));
            if (find_detail('U+' . strtoupper($hex)) !== null) {
                return '/c-u' . $hex . '/';
            }
        }
    }

    // 5. 多字西夏文串（词条/词组）→ 词组页（word 页未命中词组时回退逐字展示）
    if (preg_match_all('/./us', $q, $mm) && count($mm[0]) >= 2) {
        $allTangut = true;
        foreach ($mm[0] as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp === false || $cp < 0x17000 || $cp > 0x18AFF) {
                $allTangut = false;
                break;
            }
        }
        if ($allTangut) {
            return $routeToWord ? '/word/?q=' . rawurlencode($q) : null;
        }
    }

    // 6. 完整「XXXX YY」四角号码分组键（从四角页复制整串粘回）直接命中
    if (preg_match('/^\d{4}\s+\d{2}$/', $q)) {
        $groups = (read_json(shard_dir() . '/index_sijiao.json') ?? [])['groups'] ?? [];
        $norm = preg_replace('/\s+/', ' ', $q);
        return isset($groups[$norm]) ? '/sijiao/' . rawurlencode($norm) . '/' : null;
    }

    // 7. 4 位数字：四角号前缀优先，未命中再看 LFW（附 ?from=lfw 提示区分检索方式）
    if (preg_match('/^\d{4}$/', $q)) {
        $groups = (read_json(shard_dir() . '/index_sijiao.json') ?? [])['groups'] ?? [];
        foreach ($groups as $key => $_g) {
            if (str_starts_with((string) $key, $q . ' ')) {
                return '/sijiao/' . rawurlencode((string) $key) . '/';
            }
        }
        if (find_detail($q) !== null) {
            return '/c-' . $q . '/?from=lfw';
        }
        return null;
    }

    // 8. 拉丁字母串（≥2 字符，避免单字母触发海量结果）→ 英文/词组搜索
    if (preg_match('/^[A-Za-z][A-Za-z\s\'-]{1,40}$/', $q)) {
        return $routeToWord ? '/word/?q=' . rawurlencode($q) : null;
    }

    return null;
}

/** 英文单字 → 西夏文字头候选（T4 倒排索引，词条级 O(1)） */
function lookup_en(string $term): array
{
    static $idx = null;
    if ($idx === null) {
        $idx = read_json(shard_dir() . '/index_en.json') ?? [];
    }
    $t = strtolower(trim($term));
    return $idx['terms'][$t] ?? [];
}

/** 词组按英文释义检索（T4 相关词组，命中前 $limit 条） */
function lookup_word_en(string $term, int $limit = 12): array
{
    static $words = null;
    if ($words === null) {
        $words = (read_json(shard_dir() . '/index_word.json') ?? [])['words'] ?? [];
    }
    $t = strtolower(trim($term));
    $out = [];
    foreach ($words as $w) {
        if (str_contains(strtolower((string) ($w['gloss_en'] ?? '')), $t) || str_contains((string) ($w['word'] ?? ''), $term)) {
            $out[] = $w;
            if (count($out) >= $limit) {
                break;
            }
        }
    }
    return $out;
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
