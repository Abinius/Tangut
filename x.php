<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$h = trim((string) ($_GET['h'] ?? ''));
$isSingle = ($h !== '' && mb_strlen($h, 'UTF-8') === 1);

if (!$isSingle) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    ?>
    <p class="status">无效的汉字参数。请从 <a href="/">首页查询</a> 输入单个汉字。</p>
    <?php
    page_end();
    exit;
}

if (rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="zh"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>429 · ' . h(SITE_NAME) . '</title><body><p>查询过于频繁，请一分钟后再试。</p></body></html>';
    exit;
}

$cands = lookup_hanzi($h);
$top = $cands[0] ?? null;
$dv = data_version();

if (!$cands) {
    http_response_code(404);
}

if ($cands) {
    $preview = implode('、', array_map(static fn($c) => (string) ($c['tangut'] ?? ''), array_slice($cands, 0, 3)));
    $desc = "「{$h}」的西夏文写法：{$preview}。候选项据西夏文字典释义反查生成，按 tier 分层。";
} else {
    $desc = "「{$h}」无精确对应西夏文候选（释义不含该字）。";
}
$title = "{$h} 的西夏文写法 · " . SITE_NAME;
$canonical = '/x-' . rawurlencode($h) . '/';

page_start($title, $desc, $canonical, $title, $desc, !$cands);
?>
<p class="breadcrumb" aria-label="面包屑">
  <a href="/">查询</a> <span class="sep">///</span> 汉字 <span class="sep">///</span> <?= h($h) ?>
</p>

<p class="status done"><?= count($cands) ?> 个候选 · 数据版本 <?= h($dv) ?></p>

<section class="search">
  <div class="search-bar">
    <form method="get" action="/" style="display:flex;flex:1;gap:12px;min-width:0">
      <input id="q" type="search" name="q" placeholder="输入汉字 / LFW 编号 / 码位" aria-label="输入汉字、LFW 编号或码位" autocomplete="off">
      <?php search_btn('查询'); ?>
    </form>
  </div>
</section>

<?php if ($cands): ?>
<section class="index" aria-label="<?= h($h) ?> 的西夏文候选">
  <?php foreach ($cands as $c) { render_candidate($c); } ?>
</section>
<?php else: ?>
<p class="empty">无精确对应候选（释义不含该字，或走同音替代/最近义建议）</p>
<?php endif; ?>

<?php if ($top) { render_specimen((string) $top['tangut']); } ?>

<p class="backlinks">
  <a href="/">回首页查询</a> <span class="sep">///</span> <a href="/about">关于与致谢</a>
</p>
<?php
page_end();
