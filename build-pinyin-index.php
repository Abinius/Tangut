<?php
/**
 * 构建拼音近似索引（方案 A·分级近音版）。
 * 输入：pinyin-data py.txt + 现有 shards（已索引汉字）。
 * 输出：index_shards/pinyin_index.json
 *   to:     { "斌": "bīn", ... }              全部 CJK 字 → 主拼音（含声调）
 *   exact:  { "bīn": ["斌","彬",...] }        仅含≥1已索引字的精确同音组
 *   near:   { "b|en":[...], "in":[...], "b":[...] }  已索引字按 声母|韵族 / 韵 / 声母 分组（分级近音来源）
 */
declare(strict_types=1);

$pyFile = $argv[1] ?? 'C:/Users/admin/AppData/Local/Temp/1/py.txt';
$dir = __DIR__ . '/index_shards';

$indexed = [];
$m = json_decode((string) file_get_contents("$dir/manifest.json"), true);
foreach (($m['shards'] ?? []) as $k) {
    $s = json_decode((string) file_get_contents("$dir/$k.json"), true);
    foreach (array_keys($s['entries'] ?? []) as $h) $indexed[$h] = 1;
}

// 韵族：近音归并（bin~ben~bing 同族）
$FAM = [
    'a' => ['a','ia','ua'], 'o' => ['o','uo'], 'e' => ['e','ie','üe'], 'i' => ['i','ü'], 'u' => ['u'],
    'ai' => ['ai','uai'], 'ei' => ['ei','ui'], 'ao' => ['ao','iao'], 'ou' => ['ou','iu'],
    'an' => ['an','ian','uan','üan'], 'en' => ['en','in','un','ün'], 'ang' => ['ang','iang','uang'], 'eng' => ['eng','ing'],
];
function stripTone($p) {
    $p = preg_replace('/[āáǎà]/u', 'a', $p);
    $p = preg_replace('/[ēéěè]/u', 'e', $p);
    $p = preg_replace('/[īíǐì]/u', 'i', $p);
    $p = preg_replace('/[ōóǒò]/u', 'o', $p);
    $p = preg_replace('/[ūúǔù]/u', 'u', $p);
    $p = preg_replace('/[ǖǘǚǜ]/u', 'ü', $p);
    return $p;
}
function splitPy($py) {
    $py = stripTone($py);
    foreach (['zh','ch','sh','b','p','m','f','d','t','n','l','g','k','h','j','q','x','r','z','c','s','y','w'] as $i) {
        if (strpos($py, $i) === 0) return [$i, substr($py, strlen($i))];
    }
    return ['', $py];
}
function famOf($final, array $FAM) {
    foreach ($FAM as $fam => $members) if (in_array($final, $members, true)) return $fam;
    return $final;
}

// 解析
$allPy = [];
$groups = [];
$idxInfo = []; // 已索引字 → [init, final, fam]
foreach (file($pyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line === '' || $line[0] === '#') continue;
    // 格式：U+XXXX: 拼音1,拼音2  # 注释 —— 单读音字无逗号，须剥离行尾注释
    if (!preg_match('/^U\+([0-9A-F]+):\s*([^,#]+)/i', $line, $mm)) continue;
    $cp = hexdec($mm[1]);
    if ($cp < 0x4E00 || $cp > 0x9FFF) continue;
    $ch = mb_chr($cp, 'UTF-8');
    $py = trim($mm[2]);
    $allPy[$ch] = $py;
    $groups[$py][] = $ch;
    if (isset($indexed[$ch])) {
        [$i, $f] = splitPy($py);
        $idxInfo[$ch] = [$i, $f, famOf($f, $FAM)];
    }
}

// exact：含已索引字的同音组
$exact = [];
foreach ($groups as $py => $chars) {
    foreach ($chars as $c) if (isset($indexed[$c])) { $exact[$py] = $chars; break; }
}

// near：已索引字按 声母|韵族 / 韵 / 声母 分组
$near = [];
foreach ($idxInfo as $ch => [$i, $f, $fam]) {
    if ($i !== '') $near[$i . '|' . $fam][] = $ch;
    $near['f:' . $f][] = $ch;
    if ($i !== '') $near['i:' . $i][] = $ch;
}

$json = json_encode(['to' => $allPy, 'exact' => $exact, 'near' => $near], JSON_UNESCAPED_UNICODE);
file_put_contents("$dir/pinyin_index.json", $json);
echo '写入 pinyin_index.json: ' . number_format(strlen($json)) . " bytes（to=" . count($allPy) . ", exact=" . count($exact) . ", near=" . count($near) . " 组）\n";

// 验证：斌的分级近音
[$i, $f, $fam] = splitPy($allPy['斌'] ?? '');
echo "斌 → [$i|$fam] 近音候选: " . implode('/', array_slice($near[$i . '|' . $fam] ?? [], 0, 10)) . "\n";
