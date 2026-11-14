<?php
/**
 * T4：由 detail/*.json 的「英文释义」构建英文单字倒排索引 index_shards/index_en.json。
 *
 * 格式约定（源自 tangutdata）：英文释义形如 "[word1/word2.phrase/word3]"，
 * 以 / 分隔词条，. 连接短语（如 be.able.to）。拆词规则：
 *   - 仅保留纯拉丁词（丢弃混入的汉字串，如某条的【丘】【咎】…）；
 *   - 短语按 . 拆分保留原串与各分项（分项长度 >= 2）；
 *   - 保留每词条原始英文串，供前端展示。
 *
 * 用法：php _qa/build_en_index.php
 * 产物：index_shards/index_en.json（word -> [{lfw,zh,char,unicode,raw}]）
 */
declare(strict_types=1);

$detailDir = __DIR__ . '/../detail';
$outPath = __DIR__ . '/../index_shards/index_en.json';

$terms = [];
$files = glob($detailDir . '/*.json') ?: [];
foreach ($files as $f) {
    $d = json_decode((string) file_get_contents($f), true);
    if (!is_array($d)) {
        continue;
    }
    $raw = (string) ($d['英文释义'] ?? '');
    if ($raw === '') {
        continue;
    }
    // 去括号后按 / 切分词条
    $inner = trim($raw, "[] \t\n\r\0\x0B");
    if ($inner === '') {
        continue;
    }
    $entry = [
        'lfw' => (string) ($d['lfw_id'] ?? ''),
        'zh' => (string) ($d['释义'] ?? ''),
        'char' => (string) ($d['字'] ?? ''),
        'unicode' => (string) ($d['unicode'] ?? ''),
        'raw' => $raw,
    ];
    foreach (preg_split('#/#', $inner) as $phrase) {
        $phrase = trim($phrase);
        if ($phrase === '') {
            continue;
        }
        // 纯拉丁 + 点/空格/连字符才收录（丢弃含汉字的混合串）
        if (!preg_match('/^[A-Za-z][A-Za-z.\'\- ]*$/u', $phrase)) {
            continue;
        }
        // 短语整体也索引（保留原串），并按 . / 空白拆成单字
        $parts = preg_split('/[.\s]+/', $phrase) ?: [];
        foreach ($parts as $w) {
            $w = strtolower(trim($w, "'-"));
            if ($w === '' || strlen($w) < 2) {
                continue;
            }
            if (preg_match('/^[a-z\'-]+$/', $w)) {
                $terms[$w][] = $entry;
            }
        }
    }
}

// 去重（同一字头多词条命中同一词时保留一份）
foreach ($terms as $w => $list) {
    $seen = [];
    foreach ($list as $e) {
        $seen[$e['lfw']] = $e;
    }
    $terms[$w] = array_values($seen);
}
ksort($terms);

$json = json_encode(['terms' => $terms], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($outPath, $json);
echo 'terms: ', count($terms), ' | bytes: ', strlen($json), "\n";
