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
      <p class="credit">数据 tangutdata (MIT) · 字形 Noto Serif Tangut (OFL) · 候选项据释义反查生成，tier 为自动分层近似</p>
      <?php if (ICP_BEIAN !== '' || GONGAN_BEIAN !== ''): ?>
      <p class="beian">
        <?php if (ICP_BEIAN !== ''): ?><a href="<?= h(ICP_URL) ?>" target="_blank" rel="noopener noreferrer"><?= h(ICP_BEIAN) ?></a><?php endif; ?>
        <?php if (GONGAN_BEIAN !== ''): ?><a href="<?= h(GONGAN_URL) ?>" target="_blank" rel="noopener noreferrer"><?= h(GONGAN_BEIAN) ?></a><?php endif; ?>
      </p>
      <?php endif; ?>
    </div>
  </div>
</footer>
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
