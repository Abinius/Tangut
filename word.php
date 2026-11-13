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

if (($q !== '' || $hanzi !== '') && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
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
    <?php
    _render_word_list($results, $page);
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

<section class="search">
  <div class="seclabel"><span>搜索词组</span><span class="rule"></span></div>
  <form class="search-bar" method="get" action="/word/">
    <input type="search" name="q" placeholder="输入西夏文词条、中文或英文释义" aria-label="搜索词组" autocomplete="off">
    <button class="btn" type="submit">搜索</button>
  </form>
</section>

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

    echo '<section class="index word-list" aria-label="词组列表">';
    foreach ($slice as $e) {
        $word = (string) ($e['word'] ?? '');
        $glossCn = (string) ($e['gloss_cn'] ?? '');
        $glossEn = (string) ($e['gloss_en'] ?? '');
        $gxc = (string) ($e['gxc'] ?? '');
        ?>
  <article class="row tier-P0">
    <div class="glyph" lang="txg" style="font-size:24px" aria-label="西夏文词组 <?= h($word) ?>"><?= h($word) ?></div>
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
        $baseUrl = strtok($_SERVER['REQUEST_URI'] ?? '/word/', '?');
        render_pagination($page, $pages, $baseUrl);
    }
}
