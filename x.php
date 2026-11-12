<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$h = trim((string) ($_GET['h'] ?? ''));
$isSingle = ($h !== '' && mb_strlen($h, 'UTF-8') === 1);

if (!$isSingle) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '/404');
    ?>
    <h1 class="page-title">404<span class="arrow">.</span></h1>
    <p class="status">无效的汉字参数。请从 <a href="/">首页查询</a> 输入单个汉字。</p>
    <?php
    page_end();
    exit;
}

$cands = lookup_hanzi($h);
$top = $cands[0] ?? null;
$dv = data_version();

if ($cands) {
    $preview = implode('、', array_map(static fn($c) => (string) $c['tangut'], array_slice($cands, 0, 3)));
    $desc = "「{$h}」的西夏文写法：{$preview}。候选项据西夏文字典释义反查生成，按 tier 分层。";
} else {
    $desc = "「{$h}」无精确对应西夏文候选（释义不含该字）。";
}
$title = "{$h} 的西夏文写法 · " . SITE_NAME;
$canonical = '/x-' . rawurlencode($h) . '/';

page_start($title, $desc, $canonical, $title, $desc);
?>
<p class="breadcrumb" aria-label="面包屑">
  <a href="/">查询</a> <span class="sep">///</span> 汉字 <span class="sep">///</span> <?= h($h) ?>
</p>

<h1 class="page-title"><?= h($h) ?> <span class="arrow">→</span> 西夏文</h1>
<p class="status done"><?= count($cands) ?> 个候选 · 数据版本 <?= h($dv) ?></p>

<section class="search">
  <div class="search-bar">
    <form method="get" action="/" style="display:flex;flex:1;gap:12px;min-width:0">
      <input id="q" type="search" name="q" placeholder="输入汉字查询（如：青、张）" aria-label="输入要查询的汉字" autocomplete="off">
      <button class="btn" type="submit" aria-label="查询" title="查询">
        <svg class="btn-icon" viewBox="0 0 1024 1024" width="19" height="19" aria-hidden="true" focusable="false"><path d="M932.571429 493.714286C932.571429 261.485714 744.228571 73.142857 512 73.142857S91.428571 261.485714 91.428571 493.714286s188.342857 420.571429 420.571429 420.571428c89.6 0 171.885714-27.428571 239.542857-74.971428l111.542857 111.542857 51.2-51.2-106.057143-106.057143c76.8-76.8 124.342857-182.857143 124.342858-299.885714zM164.571429 493.714286C164.571429 301.714286 320 146.285714 512 146.285714s347.428571 155.428571 347.428571 347.428572-155.428571 347.428571-347.428571 347.428571S164.571429 685.714286 164.571429 493.714286z" fill="currentColor"></path></svg>
      </button>
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
