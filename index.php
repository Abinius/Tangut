<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$radicalSvg = require __DIR__ . '/includes/radical_svg.php';
$radVb = implode(' ', $radicalSvg['viewbox']);

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
  <svg class="corner corner-tl" width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true"><path d="M0 0L0 26" stroke="#7734db" stroke-width="0.6" opacity="0.45"/><path d="M0 0L26 0" stroke="#7734db" stroke-width="0.6" opacity="0.45"/></svg>
  <svg class="corner corner-br" width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true"><path d="M0 0L0 26" stroke="#7734db" stroke-width="0.6" opacity="0.45"/><path d="M0 0L26 0" stroke="#7734db" stroke-width="0.6" opacity="0.45"/></svg>
  <span class="glass-orb orb-a" aria-hidden="true"></span>
  <span class="glass-orb orb-b" aria-hidden="true"></span>
  <h1 class="hero-word" aria-label="<?= h(BRAND) ?>."><?= h(BRAND) ?><span class="dot">.</span></h1>
  <p class="hero-latin"><?= h(SITE_NAME) ?></p>
  <p class="hero-strike"><?= h(BRAND_TAGLINE) ?></p>
  <dl class="hero-meta">
    <div><dt><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M5 7v13h14V7M8 4h8l3 3"/></svg>数据版本</dt><dd><?= h($dv) ?></dd></div>
    <div><dt><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="2"/><path d="M12 5v14M5 12h14"/></svg>全量汉字</dt><dd><?= (int) ($stats['hanzi'] ?? 0) ?></dd></div>
    <div><dt><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4-8 4-8-4zM4 12l8 4 8-4M4 16l8 4 8-4"/></svg>候选项</dt><dd><?= (int) ($stats['candidates'] ?? 0) ?></dd></div>
    <div><dt><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h4v-4h4v-4h4V7h4"/></svg>分层</dt><dd>P0–P3 自动近似</dd></div>
  </dl>
</section>

<section class="search">
  <div class="seclabel"><span>查询</span><span class="rule"></span></div>
  <form class="search-bar" method="get" action="/">
    <input id="q" type="search" name="q" value="<?= h($q) ?>" placeholder="输入汉字（如：青、张、张青）" aria-label="输入要查询的汉字" autocomplete="off" autofocus>
    <?php search_btn('查询'); ?>
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
        $lfw = (string) ($top['lfw_id'] ?? '');
        $gong = (string) ($top['gong'] ?? '');
        $detail = find_detail($lfw);
        $sj = is_array($detail) ? (string) ($detail['四角号码'] ?? '') : '';
        ?>
  <article class="row tier-<?= h($t) ?>">
    <div class="glyph" lang="txg" aria-label="西夏文 <?= h((string) $top['tangut']) ?>"><?= h((string) $top['tangut']) ?></div>
    <div class="cell">
      <div class="meta">
        <span class="tier"><?= h($it['char']) ?> → <?= h($t) ?> (<?= count($cands) ?> 候选)</span>
        <?php if ($lfw): ?><a class="chip" href="/c-<?= h(rawurlencode($lfw)) ?>/">LFW <?= h($lfw) ?> ↗</a><?php endif; ?>
        <?php if ($gong): ?><span class="chip">龚煌城：<?= h($gong) ?></span><?php endif; ?>
        <?php if ($sj): ?><a class="chip" href="/sijiao/<?= h(rawurlencode($sj)) ?>/">四角 <?= h($sj) ?> ↗</a><?php endif; ?>
      </div>
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

  <div class="retrieve-card">
    <div class="retrieve-head">
      <h2 class="retrieve-title">部首检字</h2>
      <p class="retrieve-sub">四套部首体系 · SVG 矢量字形</p>
    </div>
    <div class="retrieve-glyphs" aria-label="西夏文部首矢量字形样例">
      <?php foreach ($radicalSvg['glyphs'] as $g):
          $cpClean = str_replace('U+', '', $g['cp']);
          $gCount = (int) $g['count'];
      ?>
      <a class="retrieve-glyph" href="/radical/unicode/<?= h($cpClean) ?>/" title="<?= h($g['char']) ?> · <?= $gCount ?> 字" aria-label="部首 <?= h($g['char']) ?>，<?= $gCount ?> 字">
        <svg viewBox="<?= h($radVb) ?>" aria-hidden="true" focusable="false"><path d="<?= h($g['d']) ?>"/></svg>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="retrieve-systems">
      <?php foreach ($radicalSvg['systems'] as $sk => $sv): ?>
      <a class="retrieve-system" href="/radical/<?= h($sk) ?>/">
        <span class="rs-label"><?= h($sv['label']) ?></span>
        <span class="rs-count"><?= (int) $sv['radical_count'] ?> 部首 · <?= (int) $sv['total_chars'] ?> 字</span>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="retrieve-cta-row">
      <a class="retrieve-cta" href="/radical/">进入检字
        <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16M14 6l6 6-6 6"/></svg>
      </a>
    </div>
  </div>

  <div class="lookup-grid">
    <a class="lookup-card" href="/radical/">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="2"/><path d="M12 5v14M5 12h14"/></svg>
      <span class="lookup-label">部首检字</span>
      <span class="lookup-desc">四套部首体系</span>
    </a>
    <a class="lookup-card" href="/stroke/">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19l2.5-7L16 3.5 20.5 8 12 16.5z"/><path d="M14 6l4 4"/></svg>
      <span class="lookup-label">笔画检字</span>
      <span class="lookup-desc">4–22 画浏览</span>
    </a>
    <a class="lookup-card" href="/sijiao/">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h4M5 5v4M15 5h4M19 5v4M5 15v4h4M15 15h4v4"/></svg>
      <span class="lookup-label">四角号码</span>
      <span class="lookup-desc">2687 组</span>
    </a>
    <a class="lookup-card" href="/rhyme/">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h3M8 7v10M13 4v16M18 8v8M21 12h-2"/></svg>
      <span class="lookup-label">声韵检字</span>
      <span class="lookup-desc">同音 / 文海 / 韵类</span>
    </a>
    <a class="lookup-card" href="/cangjie/">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="8" width="16" height="9" rx="1.5"/><path d="M8 8v9M12 8v9M16 8v9M4 12.5h16"/></svg>
      <span class="lookup-label">仓颉码</span>
      <span class="lookup-desc">5977 组</span>
    </a>
    <a class="lookup-card" href="/word/">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="5" rx="1.5"/><rect x="4" y="13" width="16" height="5" rx="1.5"/></svg>
      <span class="lookup-label">词组查询</span>
      <span class="lookup-desc">18292 条词组</span>
    </a>
    <a class="lookup-card" href="/wenhai/">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12v16H6z"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>
      <span class="lookup-label">文海文献</span>
      <span class="lookup-desc">3064 条目 · 反切</span>
    </a>
    <a class="lookup-card" href="/tongyin/">
      <svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h5v16H6zM13 4h5v16h-5z"/></svg>
      <span class="lookup-label">同音文献</span>
      <span class="lookup-desc">1320 组 · 新旧版对照</span>
    </a>
    <form class="lookup-card lookup-form" onsubmit="var v=this.q.value.trim();if(v)window.location='/c-'+encodeURIComponent(v)+'/';return false">
      <svg class="ic ic-violet" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16M14 6l6 6-6 6"/></svg>
      <span class="lookup-label">字头反查</span>
      <div class="search-bar" style="margin-top:0.4rem">
        <input type="search" name="q" placeholder="LFW 编号 / 码位 (如 0257、U+1760D)" aria-label="输入 LFW 编号或码位" autocomplete="off">
        <?php search_btn('查看'); ?>
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
