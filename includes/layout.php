<?php
declare(strict_types=1);

require_once __DIR__ . '/data.php';

function abs_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return SITE_URL . '/' . ltrim($path, '/');
}

/** 输出 <head> + 站点头部（配置全部来自 .env） */
function page_start(string $title, string $desc, string $canonical, ?string $ogTitle = null, ?string $ogDesc = null, bool $noindex = false, bool $preloadRest = false): void
{
    $canon = abs_url($canonical);
    $ogt = $ogTitle ?? $title;
    $ogd = $ogDesc ?? $desc;
    $ogImg = abs_url(OG_IMAGE);
    $host = (string) (parse_url(SITE_URL, PHP_URL_HOST) ?: 'tangutlab.org');
    ?><!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?></title>
<?php if ($canonical !== ''): ?>
<link rel="canonical" href="<?= h($canon) ?>">
<?php endif; ?>
<?php if ($noindex): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="description" content="<?= h($desc) ?>">
<meta property="og:title" content="<?= h($ogt) ?>">
<meta property="og:description" content="<?= h($ogd) ?>">
<meta property="og:image" content="<?= h($ogImg) ?>">
<?php if ($canonical !== ''): ?>
<meta property="og:url" content="<?= h($canon) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= h(OG_TYPE) ?>">
<meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
<meta name="twitter:card" content="<?= h(TWITTER_CARD) ?>">
<meta name="twitter:title" content="<?= h($ogt) ?>">
<meta name="twitter:description" content="<?= h($ogd) ?>">
<meta name="twitter:image" content="<?= h($ogImg) ?>">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" href="/favicon.ico">
<link rel="apple-touch-icon" href="/favicon.png">
<link rel="stylesheet" href="/assets/style.css">
<link rel="preload" href="<?= h(TANGUT_CORE_FONT) ?>" as="font" type="font/woff2" crossorigin>
<?php if ($preloadRest): ?>
<link rel="preload" href="<?= h(TANGUT_REST_FONT) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= h(TANGUT_FIX_FONT) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= h(TANGUT_IDSOPS_FONT) ?>" as="font" type="font/woff2" crossorigin>
<?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="返回首页">
      <span class="brand-mark" aria-hidden="true"><?= h(BRAND) ?><em>.</em></span>
    </a>
    <button class="menu-toggle" type="button" aria-label="打开菜单" aria-controls="navDrawer" aria-expanded="false">
      <svg class="menu-icon" viewBox="0 0 1024 1024" aria-hidden="true" focusable="false"><path d="M382.2 165.7H217.4c-47.5 0-86.2 38.7-86.2 86.2v164.8c0 47.5 38.7 86.2 86.2 86.2h164.8c47.5 0 86.2-38.7 86.2-86.2V251.9c0.1-47.6-38.6-86.2-86.2-86.2z m-9.9 241.1h-145v-145h145v145zM382.2 557.6H217.4c-47.5 0-86.2 38.7-86.2 86.2v164.8c0 47.5 38.7 86.2 86.2 86.2h164.8c47.5 0 86.2-38.7 86.2-86.2V643.8c0.1-47.5-38.6-86.2-86.2-86.2z m-9.9 241.1h-145v-145h145v145zM871.6 273.3L755.1 156.8c-16.3-16.3-37.9-25.3-61-25.3-23 0-44.7 9-61 25.3L516.6 273.3c-33.6 33.6-33.6 88.3 0 121.9l116.6 116.5c16.3 16.3 37.9 25.3 61 25.3 23 0 44.7-9 61-25.3l116.6-116.6c33.4-33.4 33.4-88.1-0.2-121.8zM694.1 436.8L591.6 334.3l102.5-102.5 102.5 102.5-102.5 102.5zM776.5 557.6H611.7c-47.5 0-86.2 38.7-86.2 86.2v164.8c0 47.5 38.7 86.2 86.2 86.2h164.8c47.5 0 86.2-38.7 86.2-86.2V643.8c0-47.5-38.7-86.2-86.2-86.2z m-9.9 241.1h-145v-145h145v145z" fill="currentColor"/></svg>
    </button>
  </div>
</header>
<div class="drawer-backdrop" id="navBackdrop"></div>
<aside class="drawer" id="navDrawer" aria-label="主导航" aria-hidden="true">
  <div class="drawer-head">
    <button class="drawer-close" type="button" aria-label="关闭菜单" aria-controls="navDrawer">×</button>
  </div>
  <nav class="nav" aria-label="主导航">
    <a href="/" class="nav-link"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>查询</a>
    <a href="/radical/" class="nav-link"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M12 4v16M4 12h16"/></svg>部首</a>
    <a href="/stroke/" class="nav-link"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19l2.5-7L16 3.5 20.5 8 12 16.5z"/><path d="M14 6l4 4"/></svg>笔画</a>
    <a href="/rhyme/" class="nav-link"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h3M8 7v10M13 4v16M18 8v8M21 12h-2"/></svg>声韵</a>
    <a href="/word/" class="nav-link"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="6" width="16" height="5" rx="1.5"/><rect x="4" y="13" width="16" height="5" rx="1.5"/></svg>词组</a>
    <a href="/wenhai/" class="nav-link"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12v16H6z"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>文献</a>
    <a href="/about" class="nav-link"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 11v5M12 8h.01"/></svg>关于</a>
  </nav>
</aside>
<main class="container main">
<?php
}

/** 关闭 main + 页脚（免责/备案/版权，全部来自 .env） */
function page_end(): void
{
    $dv = data_version();
    $host = (string) (parse_url(SITE_URL, PHP_URL_HOST) ?: 'tangutlab.org');
    ?></main>
<footer class="site-footer">
  <div class="container">
    <div class="colophon">
      <p>PRESS: <?= h(strtoupper($host)) ?> <span class="sep">///</span> PAPER: WARM WHITE #FAFAF8 <span class="sep">///</span> TYPE: LIGHT GROTESK + NOTO SERIF TANGUT <span class="sep">///</span> EDITION: <?= h($dv) ?></p>
      <?php $fl = friend_links(); ?>
      <?php if ($fl): ?>
      <p class="credit">友链：<?php foreach ($fl as $i => $f): ?><?= $i > 0 ? ' · ' : '' ?><a href="<?= h($f['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($f['name']) ?></a><?php endforeach; ?></p>
      <?php endif; ?>
      <?php if (ICP_BEIAN !== '' || GONGAN_BEIAN !== ''): ?>
      <p class="beian">
        <?php if (ICP_BEIAN !== ''): ?><a href="<?= h(ICP_URL) ?>" target="_blank" rel="noopener noreferrer"><?= h(ICP_BEIAN) ?></a><?php endif; ?>
        <?php if (GONGAN_BEIAN !== ''): ?><a href="<?= h(GONGAN_URL) ?>" target="_blank" rel="noopener noreferrer"><?= h(GONGAN_BEIAN) ?></a><?php endif; ?>
      </p>
      <?php endif; ?>
    </div>
  </div>
</footer>
<button class="backtop" id="backTop" type="button" aria-label="返回顶部" title="返回顶部">
  <svg viewBox="0 0 1024 1024" aria-hidden="true" focusable="false">
    <path d="M527.019 176.922c-3.98-3.987-9.385-6.225-15.02-6.225s-11.037 2.237-15.022 6.225l-154.8 154.795c-8.295 8.295-8.295 21.745 0 30.04 8.3 8.295 21.747 8.295 30.042 0l139.78-139.775 139.77 139.775c4.15 4.15 9.59 6.225 15.025 6.225s10.875-2.075 15.025-6.225c8.295-8.295 8.295-21.745 0-30.04L527.019 176.922 527.019 176.922z"/>
    <path d="M149.2992 525.70112l133.91872 0 0 26.28096-47.88224 0 0 241.5616c0 28.55936-14.76096 42.83904-44.27776 42.83904-12.24192 0-22.08256-0.24064-29.52192-0.72192-1.19808-11.03872-3.1232-21.71904-5.76-32.04096 11.03872 2.64192 22.43584 3.95776 34.2016 3.95776 11.52 0 17.28-5.51936 17.28-16.55808l0-239.04256L149.2992 551.97696 149.2992 525.70112zM376.82176 635.14112l27.71968 0 0 39.59808c0 29.99808-3.24096 55.56224-9.71776 76.68224 40.56064 25.92256 77.52192 51.35872 110.87872 76.31872l-20.16256 24.12032c-32.15872-26.63936-65.87904-51.84-101.16096-75.60192-2.40128 4.80256-5.03808 9.11872-7.92064 12.95872-19.92192 27.36128-54.35904 48.72192-103.3216 64.08192-4.56192-7.92064-10.32192-16.55808-17.28-25.92256 45.35808-12.95872 77.39904-30.96064 96.11776-54.00064 16.55808-21.60128 24.84224-54.24128 24.84224-97.92L376.81664 635.14112zM294.02112 591.58016l67.31776 0 10.08128-48.59904L290.05824 542.98112l0-25.92256 214.55872 0 0 25.92256-104.0384 0-11.52 48.59904 99.35872 0 0 172.4416L460.6976 764.02176l0-146.51904L321.7408 617.50272l0 147.95776-27.71968 0L294.02112 591.58016z"/>
    <path d="M519.74144 639.09888l115.2 0c14.27968-24.71936 25.92256-47.27808 34.9184-67.68128l26.63936 13.68064c-10.43968 18.24256-21.53984 36.23936-33.30048 54.00064l62.8224 0 0 24.12032-206.27968 0L519.74144 639.09888zM527.66208 544.06144l89.99936 0c-4.67968-10.79808-11.76064-24.00256-21.23776-39.59808l24.84224-11.52c10.79808 18.00192 18.47808 32.27648 23.04 42.83904l-17.6384 8.27904 92.88192 0 0 24.12032-191.88224 0L527.6672 544.06144zM545.65888 700.66176l155.52 0 0 146.51904-26.63936 0 0-26.63936-102.24128 0 0 26.99776-26.63936 0L545.65888 700.66176zM551.7824 586.90048l21.95968-13.32224c12.00128 17.03936 22.92224 34.07872 32.75776 51.11808l-24.47872 14.03904C574.58176 623.62112 564.50048 606.34112 551.7824 586.90048zM674.53952 724.78208l-102.24128 0 0 70.92224 102.24128 0L674.53952 724.78208zM874.7008 729.82016c0 41.28256-16.19968 63.60064-48.59904 66.95936-12.24192 1.43872-28.55936 0.96256-48.96256-1.43872-1.19808-8.64256-2.75968-19.2-4.67968-31.68256 17.52064 2.88256 32.52224 4.32128 44.99968 4.32128 21.12 0 30.72-14.52032 28.8-43.56096-0.96256-25.68192-19.31776-51.95776-55.08096-78.83776 15.60064-30.96064 31.44192-65.39776 47.51872-103.3216l-73.44128 0 0 307.08224-27.36128 0 0-333.7216 129.60256 0 0 29.52192c-9.35936 24.23808-23.87968 55.68-43.56096 94.32064C857.7792 665.86112 874.7008 695.98208 874.7008 729.82016z"/>
  </svg>
</button>
<script>
(function () {
  var b = document.querySelector('.menu-toggle');
  var d = document.getElementById('navDrawer');
  var k = document.getElementById('navBackdrop');
  var c = document.querySelector('.drawer-close');
  if (!b || !d) return;
  function open() {
    d.classList.add('open'); d.setAttribute('aria-hidden', 'false');
    k.classList.add('show'); b.setAttribute('aria-expanded', 'true');
    document.body.classList.add('menu-open');
  }
  function close() {
    d.classList.remove('open'); d.setAttribute('aria-hidden', 'true');
    k.classList.remove('show'); b.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('menu-open');
  }
  b.addEventListener('click', open);
  if (c) c.addEventListener('click', close);
  k.addEventListener('click', close);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
(function () {
  var bt = document.getElementById('backTop');
  if (!bt) return;
  function onScroll() { bt.classList.toggle('show', window.scrollY > 240); }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  bt.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
})();
</script>
</body>
</html>
<?php
}

/** 候选卡片（服务端渲染） */
function render_candidate(array $c): void
{
    $tier = (string) ($c['tier'] ?? 'none');
    ?><article class="row tier-<?= h($tier) ?>">
  <div class="glyph" lang="txg" aria-label="西夏文 <?= h((string) ($c['tangut'] ?? '')) ?>"><?= h((string) ($c['tangut'] ?? '')) ?></div>
  <div class="cell">
    <div class="meta">
      <span class="tier"><?= h($tier) ?> · <?= h(tier_label($tier)) ?></span>
      <span class="chip"><?= h((string) ($c['unicode'] ?? '')) ?></span>
      <span class="chip">拟音（龚煌城）：<?= h(((string) ($c['gong'] ?? '')) !== '' ? (string) $c['gong'] : '—') ?></span>
      <?php $lfwId = (string) ($c['lfw_id'] ?? ''); ?>
      <?php if ($lfwId): ?>
      <span class="chip"><a href="/c-<?= h(rawurlencode($lfwId)) ?>/">LFW <?= h($lfwId) ?> ↗</a></span>
      <?php else: ?>
      <span class="chip">LFW —</span>
      <?php endif; ?>
    </div>
    <p class="gloss">释义：<?= h((string) ($c['gloss_zh'] ?? '')) ?></p>
  </div>
</article>
<?php
}

/** 字形 specimen（三档字号） */
function render_specimen(string $tangut): void
{
    ?><section>
  <div class="seclabel"><span>字形 specimen</span><span class="rule"></span></div>
  <div class="spec">
    <div class="spec-cell"><span class="label">caption · 22px</span><span class="glyph s1"><?= h($tangut) ?></span></div>
    <div class="spec-cell"><span class="label">display · 48px</span><span class="glyph s2"><?= h($tangut) ?></span></div>
    <div class="spec-cell"><span class="label">hero · 96px</span><span class="glyph s3"><?= h($tangut) ?></span></div>
  </div>
</section>
<?php
}

/** 全站统一搜索按钮（SVG 放大镜图标，禁止再用文字按钮） */
function search_btn(string $label = '查询'): void
{
    ?><button class="btn" type="submit" aria-label="<?= h($label) ?>" title="<?= h($label) ?>">
  <svg class="btn-icon" viewBox="0 0 1024 1024" width="19" height="19" aria-hidden="true" focusable="false"><path d="M932.571429 493.714286C932.571429 261.485714 744.228571 73.142857 512 73.142857S91.428571 261.485714 91.428571 493.714286s188.342857 420.571429 420.571429 420.571428c89.6 0 171.885714-27.428571 239.542857-74.971428l111.542857 111.542857 51.2-51.2-106.057143-106.057143c76.8-76.8 124.342857-182.857143 124.342858-299.885714zM164.571429 493.714286C164.571429 301.714286 320 146.285714 512 146.285714s347.428571 155.428571 347.428571 347.428572-155.428571 347.428571-347.428571 347.428571S164.571429 685.714286 164.571429 493.714286z" fill="currentColor"></path></svg>
</button><?php
}

/** 检字体系图标（描边 currentColor，24 viewBox，部首/声韵总览卡复用） */
function system_icon(string $key): string
{
    $icons = [
        'unicode' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M9.5 9.5v10"/>',
        'xh' => '<path d="M5 4.5h12.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H6.5A2.5 2.5 0 0 1 4 17V6a2 2 0 0 1 1-1.5z"/><path d="M4 16.5a2.5 2.5 0 0 1 2.5-2.5H19"/>',
        'eyh' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.6 2.8 2.6 14.2 0 17M12 3.5c-2.6 2.8-2.6 14.2 0 17"/>',
        'xwd' => '<path d="M12 6c-1.6-1.3-3.6-2-6-2v14c2.4 0 4.4.7 6 2 1.6-1.3 3.6-2 6-2V4c-2.4 0-4.4.7-6 2z"/><path d="M12 6v14"/>',
        'tongyin' => '<path d="M3 12h2M8 7.5v9M13 4v16M18 8v8M21 12h.01"/>',
        'wenhai' => '<path d="M12 6c-1.6-1.3-3.6-2-6-2v14c2.4 0 4.4.7 6 2 1.6-1.3 3.6-2 6-2V4c-2.4 0-4.4.7-6 2z"/><path d="M12 6v14"/>',
        'yunlei' => '<path d="M4 12V5a1 1 0 0 1 1-1h7l8 8-8 8-8-8z"/><circle cx="8.5" cy="8.5" r="1.4"/>',
    ];
    $body = $icons[$key] ?? '<circle cx="12" cy="12" r="8.5"/>';
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}
