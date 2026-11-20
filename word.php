<?php
declare(strict_types=1);

/**
 * 词组查询页（PRD W-11 / D-11 / T3.4–T3.5）。
 *
 * 路由：
 *   /word/              → 词组总览（搜索 + 最近词组）
 *   /word/?q=X          → 搜索词组（按西夏文词条或中文释义）
 *   /word/h-{汉字}/     → 含某汉字释义的词组列表
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/indexes.php';

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 32, 'UTF-8');
$hanzi = trim((string) ($_GET['h'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));

// 统一分发：LFW/码位、单个西夏文、四角号码 → 对应页面；词条/中文/英文释义走下方词组搜索
// routeToWord=false：词条串在此页语境处理，避免跳回自身造成自循环
if ($q !== '' && ($target = search_target($q, false)) !== null) {
    header('Location: ' . $target);
    exit;
}

// 本页执行词组搜索才限流；上面重定向的搜索由目标页各自限流（避免一次搜索双计数）
if (($q !== '' || $hanzi !== '') && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="zh"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>429 · ' . h(SITE_NAME) . '</title><body><p>查询过于频繁，请一分钟后再试。</p></body></html>';
    exit;
}

$idx = read_json(shard_dir() . '/index_word.json') ?? [];
$allWords = $idx['words'] ?? [];
$byHanzi = $idx['by_hanzi'] ?? [];

// 按汉字查词组
if ($hanzi !== '' && isset($byHanzi[$hanzi])) {
    $entries = $byHanzi[$hanzi]['entries'];
    $total = (int) $byHanzi[$hanzi]['count'];
    $title = $hanzi . ' 的西夏文词组 · 词组查询 · ' . SITE_NAME;
    $desc = "释义含「{$hanzi}」的西夏文词组，共 {$total} 条。";
    page_start($title, $desc, '/word/h-' . rawurlencode($hanzi) . '/');
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/word/">词组</a> <span class="sep">///</span> <?= h($hanzi) ?></p>
    <p class="status done"><?= $total ?> 条词组 · 释义含「<?= h($hanzi) ?>」</p>
    <?php
    _word_search_form($hanzi);
    _render_word_list($entries, $page);
    ?>
    <p class="backlinks"><a href="/word/">回词组总览</a> <span class="sep">///</span> <a href="/">回首页查询</a></p>
    <?php
    page_end();
    exit;
}

// 搜索词组
if ($q !== '') {
    $results = [];
    foreach ($allWords as $w) {
        $word = (string) ($w['word'] ?? '');
        $glossCn = (string) ($w['gloss_cn'] ?? '');
        $glossEn = (string) ($w['gloss_en'] ?? '');
        if (str_contains($word, $q) || str_contains($glossCn, $q) || str_contains($glossEn, $q)) {
            $results[] = $w;
        }
    }
    $title = '搜索「' . $q . '」· 词组查询 · ' . SITE_NAME;
    page_start($title, "搜索「{$q}」的西夏文词组结果。", '/word/');
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/word/">词组</a> <span class="sep">///</span> 搜索</p>
    <p class="status done"><?= count($results) ?> 条结果 · 搜索「<?= h($q) ?>」</p>
    <?php if (preg_match('/^[A-Za-z][A-Za-z\s\'-]*$/', $q)): ?>
    <p class="status">拼音检索仅供参考（按字面匹配词组与释义）</p>
    <?php endif; ?>
    <?php
    _word_search_form($q);
    if ($results) {
        _render_word_list($results, $page);
    } elseif (preg_match('/^[\x{17000}-\x{18AFF}]+$/u', $q)) {
        _render_tangut_chars($q);
    } else {
        _render_word_list($results, $page);
    }
    ?>
    <p class="backlinks"><a href="/word/">回词组总览</a></p>
    <?php
    page_end();
    exit;
}

// 总览
$total = (int) ($idx['total_words'] ?? 0);
$title = '词组查询 · ' . SITE_NAME;
page_start($title, "西夏文词组查询，共 {$total} 条词组。", '/word/');
?>
<p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> 词组</p>
<p class="status done"><?= $total ?> 条词组 · <?= (int) ($idx['total_hanzi_with_words'] ?? 0) ?> 汉字有词组数据</p>

<?php _word_search_form(''); ?>

<section>
  <div class="seclabel"><span>最近词组</span><span class="rule"></span></div>
  <?php
  // 展示前 30 条
  $recent = array_slice($allWords, 0, 30);
  _render_word_list($recent, 1, 30);
  ?>
</section>
<p class="backlinks"><a href="/">回首页查询</a></p>
<?php
page_end();


/** 词组搜索表单（总览 / 搜索结果 / 汉字结果共用，回显当前查询值） */
function _word_search_form(string $value): void
{
    ?>
    <section class="search">
      <div class="seclabel"><span>搜索词组</span><span class="rule"></span></div>
      <form class="search-bar search-bar-line" method="get" action="/word/">
        <input type="search" name="q" value="<?= h($value) ?>" placeholder="词条 / 释义 / LFW 编号 / 码位" aria-label="搜索词组，或输入 LFW 编号/码位查字头" autocomplete="off">
        <?php search_btn('搜索'); ?>
      </form>
    </section>
    <?php
}

/** 多字西夏文串未命中词组时，回退逐字展示每个字头候选（工单验收 #5） */
function _render_tangut_chars(string $text): void
{
    preg_match_all('/./us', $text, $mm);
    echo '<section class="index wordpage" aria-label="西夏文逐字"><div class="seclabel"><span>逐字展示</span><span class="rule"></span></div>';
    foreach ($mm[0] as $ch) {
        $cp = mb_ord($ch, 'UTF-8');
        if ($cp === false || $cp < 0x17000 || $cp > 0x18AFF) {
            continue;
        }
        $hex = strtolower(dechex($cp));
        $d = find_detail('U+' . strtoupper($hex));
        if (!$d) {
            continue;
        }
        render_candidate([
            'tier' => 'P0',
            'tangut' => (string) ($d['字'] ?? ''),
            'unicode' => (string) ($d['unicode'] ?? ''),
            'gong' => (string) ($d['GHC'] ?? ''),
            'lfw_id' => (string) ($d['lfw_id'] ?? ''),
            'gloss_zh' => (string) ($d['释义'] ?? ''),
        ]);
    }
    echo '</section>';
}

function _render_word_list(array $entries, int $page, int $perPage = 60): void
{
    $total = count($entries);
    if ($total === 0) {
        echo '<p class="empty">无词组结果</p>';
        return;
    }
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($entries, $offset, $perPage);

    echo '<section class="index word-list wordpage" aria-label="词组列表">';
    foreach ($slice as $e) {
        $word = (string) ($e['word'] ?? '');
        $glossCn = (string) ($e['gloss_cn'] ?? '');
        $glossEn = (string) ($e['gloss_en'] ?? '');
        $gxc = (string) ($e['gxc'] ?? '');
        ?>
  <article class="row tier-P0">
    <div class="glyph" lang="txg" aria-label="西夏文词组 <?= h($word) ?>"><?= h($word) ?></div>
    <div class="cell">
      <div class="meta">
        <?php if ($gxc): ?><span class="chip">龚煌城：<?= h($gxc) ?></span><?php endif; ?>
      </div>
      <?php if ($glossCn): ?><p class="gloss">释义：<?= h($glossCn) ?></p><?php endif; ?>
      <?php if ($glossEn): ?><p class="gloss" style="color:var(--ink-mute)"><?= h($glossEn) ?></p><?php endif; ?>
    </div>
  </article>
        <?php
    }
    echo '</section>';
    if ($pages > 1) {
        // 保留 q/h 查询参数，仅去掉分页 p（strtok 会丢 query 导致翻页回总览）
        $baseUrl = preg_replace('/([?&])p=\d+$/', '', (string) ($_SERVER['REQUEST_URI'] ?? '/word/'));
        render_pagination($page, $pages, $baseUrl);
    }
}
