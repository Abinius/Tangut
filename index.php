<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 16, 'UTF-8');

if ($q !== '' && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="zh"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>429 · ' . h(SITE_NAME) . '</title><body><p>查询过于频繁，请一分钟后再试。</p></body></html>';
    exit;
}

$stats = site_stats();
$dv = data_version();

page_start(SITE_TITLE, SITE_DESCRIPTION, '/');
?>
<section class="hero">
  <svg class="corner corner-tl" width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true"><path d="M0 0L0 26" stroke="#d4a574" stroke-width="0.6" opacity="0.45"/><path d="M0 0L26 0" stroke="#d4a574" stroke-width="0.6" opacity="0.45"/></svg>
  <svg class="corner corner-br" width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true"><path d="M0 0L0 26" stroke="#d4a574" stroke-width="0.6" opacity="0.45"/><path d="M0 0L26 0" stroke="#d4a574" stroke-width="0.6" opacity="0.45"/></svg>
  <h1 class="hero-word" aria-label="<?= h(BRAND) ?>."><?= h(BRAND) ?><span class="dot">.</span></h1>
  <p class="hero-latin"><?= h(SITE_NAME) ?></p>
  <p class="hero-strike"><?= h(BRAND_TAGLINE) ?></p>
  <dl class="hero-meta">
    <div><dt>数据版本</dt><dd><?= h($dv) ?></dd></div>
    <div><dt>全量汉字</dt><dd><?= (int) ($stats['hanzi'] ?? 0) ?></dd></div>
    <div><dt>候选项</dt><dd><?= (int) ($stats['candidates'] ?? 0) ?></dd></div>
    <div><dt>分层</dt><dd>P0–P3 自动近似</dd></div>
  </dl>
</section>

<section class="search">
  <div class="seclabel"><span>查询</span><span class="rule"></span></div>
  <form class="search-bar" method="get" action="/">
    <input id="q" type="search" name="q" value="<?= h($q) ?>" placeholder="输入汉字（如：青、张、张青）" aria-label="输入要查询的汉字" autocomplete="off" autofocus>
    <button class="btn" type="submit" aria-label="查询" title="查询">
      <svg class="btn-icon" viewBox="0 0 1024 1024" width="19" height="19" aria-hidden="true" focusable="false"><path d="M932.571429 493.714286C932.571429 261.485714 744.228571 73.142857 512 73.142857S91.428571 261.485714 91.428571 493.714286s188.342857 420.571429 420.571429 420.571428c89.6 0 171.885714-27.428571 239.542857-74.971428l111.542857 111.542857 51.2-51.2-106.057143-106.057143c76.8-76.8 124.342857-182.857143 124.342858-299.885714zM164.571429 493.714286C164.571429 301.714286 320 146.285714 512 146.285714s347.428571 155.428571 347.428571 347.428572-155.428571 347.428571-347.428571 347.428571S164.571429 685.714286 164.571429 493.714286z" fill="currentColor"></path></svg>
    </button>
  </form>
  <p class="status done">数据版本 <?= h($dv) ?> · 全量 <?= (int) ($stats['hanzi'] ?? 0) ?> 字可查 · 服务端实时查询</p>
</section>

<?php if ($q !== ''): ?>
<section class="index" aria-live="polite">
<?php
$items = lookup_text($q);
$any = false;
foreach ($items as $it):
    $cands = $it['candidates'];
    $top = $cands[0] ?? null;
    if ($top) {
        $any = true;
        $t = (string) ($top['tier'] ?? 'none');
        ?>
  <article class="row tier-<?= h($t) ?>">
    <div class="glyph" lang="txg" aria-label="西夏文 <?= h((string) $top['tangut']) ?>"><?= h((string) $top['tangut']) ?></div>
    <div class="cell">
      <div class="meta"><span class="tier"><?= h($it['char']) ?> → <?= h($t) ?> (<?= count($cands) ?> 候选)</span></div>
      <p class="gloss">释义：<?= h((string) ($top['gloss_zh'] ?? '')) ?></p>
    </div>
  </article>
        <?php
    } else {
        ?>
  <article class="row tier-none">
    <div class="glyph" aria-label="无对应">□</div>
    <div class="cell">
      <div class="meta"><span class="tier"><?= h($it['char']) ?> → 无精确对应</span></div>
      <p class="gloss">释义不含该字，或走同音替代/最近义建议</p>
    </div>
  </article>
        <?php
    }
endforeach;
if (!$any && !$items) {
    echo '<p class="empty">未识别的输入</p>';
}
?>
</section>
<?php endif; ?>

<section>
  <div class="seclabel"><span>检字</span><span class="rule"></span></div>
  <div class="lookup-grid">
    <a class="lookup-card" href="/radical/">
      <span class="lookup-label">部首检字</span>
      <span class="lookup-desc">四套部首体系</span>
    </a>
    <a class="lookup-card" href="/stroke/">
      <span class="lookup-label">笔画检字</span>
      <span class="lookup-desc">4–22 画浏览</span>
    </a>
    <a class="lookup-card" href="/sijiao/">
      <span class="lookup-label">四角号码</span>
      <span class="lookup-desc">2687 组</span>
    </a>
    <a class="lookup-card" href="/rhyme/">
      <span class="lookup-label">声韵检字</span>
      <span class="lookup-desc">同音 / 文海 / 韵类</span>
    </a>
    <a class="lookup-card" href="/cangjie/">
      <span class="lookup-label">仓颉码</span>
      <span class="lookup-desc">5977 组</span>
    </a>
    <a class="lookup-card" href="/word/">
      <span class="lookup-label">词组查询</span>
      <span class="lookup-desc">18292 条词组</span>
    </a>
    <form class="lookup-card lookup-form" onsubmit="var v=this.q.value.trim();if(v)window.location='/c-'+encodeURIComponent(v)+'/';return false">
      <span class="lookup-label">字头反查</span>
      <div class="search-bar" style="margin-top:0.4rem">
        <input type="search" name="q" placeholder="LFW 编号 / 码位 (如 0257、U+1760D)" aria-label="输入 LFW 编号或码位" autocomplete="off">
        <button class="btn" type="submit">查看</button>
      </div>
    </form>
  </div>
</section>

<section>
  <div class="seclabel"><span>字形 specimen</span><span class="rule"></span></div>
  <!-- 设计样张：固定示例字形，不随数据版本变化（仅演示三档字号渲染） -->
  <div class="spec">
    <div class="spec-cell"><span class="label">caption · 22px</span><span class="glyph s1">𗘍</span></div>
    <div class="spec-cell"><span class="label">display · 48px</span><span class="glyph s2">𗘍</span></div>
    <div class="spec-cell"><span class="label">hero · 96px</span><span class="glyph s3">𗘍</span></div>
  </div>
</section>
<?php
page_end();
