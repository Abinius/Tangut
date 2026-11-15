<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$radicalSvg = require __DIR__ . '/includes/radical_svg.php';
$radVb = implode(' ', $radicalSvg['viewbox']);

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 16, 'UTF-8');

// 主搜索框统一分发：汉字→西夏文、字头反查（LFW/码位）、单个西夏文、四角号码、词组（交给各自页面）
$inlineEn = null;
if ($q !== '') {
    $target = search_target($q);
    // T4：英文单字命中倒排索引 → 内联渲染字头候选，不跳词组页（多词/未命中仍走词组页）
    if ($target !== null && str_starts_with($target, '/word/') && !str_contains($q, ' ')) {
        $hits = lookup_en($q);
        if ($hits) {
            $inlineEn = $hits;
        } else {
            header('Location: ' . $target);
            exit;
        }
    } elseif ($target !== null) {
        header('Location: ' . $target);
        exit;
    }
    // 本页执行逐字查询才限流；重定向的搜索由目标页各自限流（避免一次搜索在两级页面重复计数）
    if (rate_limited()) {
        http_response_code(429);
        header('Retry-After: 60');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="zh"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>429 · ' . h(SITE_NAME) . '</title><body><p>查询过于频繁，请一分钟后再试。</p></body></html>';
        exit;
    }
}

$stats = site_stats();

// 首页标题/分享走 .env 的 SITE_TITLE / OG_TITLE / OG_DESCRIPTION（其余页面各自提供 title/og）
page_start(SITE_TITLE, SITE_DESCRIPTION, '/', OG_TITLE !== '' ? OG_TITLE : null, OG_DESCRIPTION !== '' ? OG_DESCRIPTION : null);
?>
<section class="hero">
  <svg class="corner corner-tl" width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true"><path d="M0 0L0 26" stroke="#7734db" stroke-width="0.6" opacity="0.45"/><path d="M0 0L26 0" stroke="#7734db" stroke-width="0.6" opacity="0.45"/></svg>
  <svg class="corner corner-br" width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true"><path d="M0 0L0 26" stroke="#7734db" stroke-width="0.6" opacity="0.45"/><path d="M0 0L26 0" stroke="#7734db" stroke-width="0.6" opacity="0.45"/></svg>
  <span class="glass-orb orb-a" aria-hidden="true"></span>
  <span class="glass-orb orb-b" aria-hidden="true"></span>
  <h1 class="hero-word" aria-label="<?= h(SITE_NAME) ?>."><?= h(SITE_NAME) ?><span class="dot">.</span></h1>
  <p class="hero-latin"><?= h(OG_TITLE) ?></p>

  
</section>
<script>if(window.innerWidth>768&&!/MicroMessenger/i.test(navigator.userAgent)){var s=<?= json_encode(explode('|', BRAND_TAGLINE)) ?>;if(s.length>1)document.title=s.join('|');}</script>

<section class="search">
  
  <form class="search-bar" method="get" action="/">
    <input id="q" type="search" name="q" value="<?= h($q) ?>" placeholder="输入汉字 / LFW 编号 / 码位" aria-label="输入汉字、LFW 编号或码位" autocomplete="off" autofocus>
    <?php search_btn('查询'); ?>
  </form>
  <p class="query-hint">支持：汉字（青）· 英文（blue）· 四角号（1141 20）· 直接粘贴西夏字</p>
  <dl class="hero-meta">
 
    <div><dt><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="2"/><path d="M12 5v14M5 12h14"/></svg>全量汉字</dt><dd><?= (int) ($stats['hanzi'] ?? 0) ?></dd></div>
    <div><dt><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4-8 4-8-4zM4 12l8 4 8-4M4 16l8 4 8-4"/></svg>候选项</dt><dd><?= (int) ($stats['candidates'] ?? 0) ?></dd></div>
    <div><dt><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h4v-4h4v-4h4V7h4"/></svg>分层</dt><dd>P0–P3 近似</dd></div>
  </dl>
</section>

<?php if ($q !== ''): ?>
<section class="index" aria-live="polite">
<?php
if ($inlineEn !== null) {
    echo '<p class="status done">英文「' . h($q) . '」匹配 ' . count($inlineEn) . ' 个字头</p>';
    foreach ($inlineEn as $hit) {
        $d = find_detail($hit['lfw']);
        if (!$d) { continue; }
        render_candidate([
            'tier' => 'P0',
            'tangut' => (string) ($d['字'] ?? ''),
            'unicode' => (string) ($d['unicode'] ?? ''),
            'gong' => (string) ($d['GHC'] ?? ''),
            'lfw_id' => (string) ($d['lfw_id'] ?? ''),
            'gloss_zh' => (string) ($d['释义'] ?? ''),
        ]);
    }
    $rel = lookup_word_en($q, 12);
    if ($rel) {
        echo '<div class="seclabel" style="margin-top:1.5rem"><span>相关词组</span><span class="rule"></span></div>';
        foreach ($rel as $w) {
            $word = (string) ($w['word'] ?? '');
            $gcn = (string) ($w['gloss_cn'] ?? '');
            $gen = (string) ($w['gloss_en'] ?? '');
            $gxc = (string) ($w['gxc'] ?? '');
            echo '<article class="row tier-P0"><div class="glyph" lang="txg" style="font-size:24px" aria-label="西夏文词组 ' . h($word) . '">' . h($word) . '</div><div class="cell"><div class="meta">';
            if ($gxc) { echo '<span class="chip">龚煌城：' . h($gxc) . '</span>'; }
            echo '</div>';
            if ($gcn) { echo '<p class="gloss">释义：' . h($gcn) . '</p>'; }
            if ($gen) { echo '<p class="gloss" style="color:var(--ink-mute)">' . h($gen) . '</p>'; }
            echo '</div></article>';
        }
    }
} else {
if (!preg_match('/\p{Han}/u', $q)) {
    echo '<p class="empty">未识别的输入</p>';
} else {
$items = lookup_text($q);
$any = false;
// 起名构建器（娱乐参考）：纯汉字多字查询才启用，逐字选择候选项后生成图片
$nameBuilder = preg_match('/^\p{Han}+$/u', $q) && count($items) >= 2;
if ($nameBuilder) {
    render_name_builder($items);
} else {
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
        $posterRow = json_encode([
            'hanzi'  => $it['char'],
            'tangut' => (string) ($top['tangut'] ?? ''),
            'phon'   => $gong,
            'gloss'  => mb_substr((string) ($top['gloss_zh'] ?? ''), 0, 22, 'UTF-8'),
            'tier'   => $t,
            'lfw'    => $lfw,
            'dv'     => data_version(),
        ], JSON_UNESCAPED_UNICODE);
        ?>
  <article class="row tier-<?= h($t) ?>">
    <div class="glyph" lang="txg" aria-label="西夏文 <?= h((string) $top['tangut']) ?>"><?= h((string) $top['tangut']) ?></div>
    <div class="cell">
      <div class="meta">
        <span class="tier"><?= h($it['char']) ?> → <?= h($t) ?> (<?= count($cands) ?> 候选)</span>
        <a class="chip" href="/x-<?= h(rawurlencode($it['char'])) ?>/">查看全部候选 ↗</a>
        <?php if ($lfw): ?><a class="chip" href="/c-<?= h(rawurlencode($lfw)) ?>/">LFW <?= h($lfw) ?> ↗</a><?php endif; ?>
        <?php if ($gong): ?><span class="chip">龚煌城：<?= h($gong) ?></span><?php endif; ?>
        <?php if ($sj): ?><a class="chip" href="/sijiao/<?= h(rawurlencode($sj)) ?>/">四角 <?= h($sj) ?> ↗</a><?php endif; ?>
      </div>
      <p class="gloss">释义：<?= h((string) ($top['gloss_zh'] ?? '')) ?></p>
      <div class="row-actions"><button class="btn share" type="button" data-poster='<?= h($posterRow) ?>'>分享</button></div>
    </div>
  </article>
        <?php
    } else {
        $posterNone = json_encode([
            'hanzi'  => $it['char'],
            'tangut' => '○',
            'phon'   => '',
            'gloss'  => '',
            'tier'   => 'none',
            'lfw'    => '',
            'dv'     => data_version(),
        ], JSON_UNESCAPED_UNICODE);
        ?>
  <article class="row tier-none">
    <div class="glyph" aria-label="无对应">□</div>
    <div class="cell">
      <div class="meta"><span class="tier"><?= h($it['char']) ?> → 无精确对应</span></div>
      <p class="gloss">释义不含该字，或走同音替代/最近义建议</p>
      <div class="row-actions"><button class="btn share" type="button" data-poster='<?= h($posterNone) ?>'>分享</button></div>
    </div>
  </article>
  <?php if (!$nameBuilder): ?>
  <?php foreach (pinyin_fallback($it['char']) as $fb):
      $posterFb = json_encode([
          'hanzi'  => $it['char'],
          'tangut' => $fb['tangut'],
          'phon'   => $fb['gong'],
          'gloss'  => mb_substr($fb['gloss_zh'], 0, 22, 'UTF-8'),
          'tier'   => 'P3',
          'lfw'    => $fb['lfw_id'],
          'homophone' => (string) ($fb['homophone'] ?? ''),
          'dv'     => data_version(),
      ], JSON_UNESCAPED_UNICODE);
  ?>
  <article class="row tier-P3">
    <div class="glyph" lang="txg" aria-label="西夏文 <?= h($fb['tangut']) ?>"><?= h($fb['tangut']) ?></div>
    <div class="cell">
      <div class="meta"><span class="tier"><?= h($fb['label']) ?>替代（娱乐参考）· 借字「<?= h($fb['homophone']) ?>」</span></div>
      <p class="gloss">「<?= h($it['char']) ?>」无对应西夏字，按<?= h($fb['label']) ?>取「<?= h($fb['homophone']) ?>」的西夏文作参考</p>
      <div class="row-actions"><button class="btn share" type="button" data-poster='<?= h($posterFb) ?>'>分享</button></div>
    </div>
  </article>
  <?php endforeach; ?>
  <?php endif; ?>
        <?php
    }
endforeach;
if (count($items) >= 2) {
    $posterCombo = json_encode([
        'mode' => 'combo',
        'items' => array_map(static fn($it) => [
            'hanzi'  => $it['char'],
            'tangut' => $it['candidates'][0]['tangut'] ?? '○',
            'phon'   => $it['candidates'][0]['gong'] ?? '—',
            'gloss'  => mb_substr((string) ($it['candidates'][0]['gloss_zh'] ?? '西夏文无此义项'), 0, 22, 'UTF-8'),
            'tier'   => $it['candidates'][0]['tier'] ?? 'none',
            'lfw'    => $it['candidates'][0]['lfw_id'] ?? '—',
        ], $items),
        'dv' => data_version(),
    ], JSON_UNESCAPED_UNICODE);
    echo '<div class="row-actions combo-cta"><button class="btn share" type="button" data-poster=\'' . h($posterCombo) . '\'>分享组合</button></div>';
}
if (!$any) {
    echo '<p class="empty">无精确对应候选</p>';
}
}
}
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
