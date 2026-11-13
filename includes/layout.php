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
function page_start(string $title, string $desc, string $canonical, ?string $ogTitle = null, ?string $ogDesc = null, bool $noindex = false): void
{
    $dv = data_version();
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
</head>
<body>
<header class="site-header">
  <div class="register">
    <div class="container register-inner mono">
      <span class="rp">[ <?= h(SITE_NAME) ?> ]</span>
      <span><span class="sep">///</span> 全量索引 · P0–P3</span>
      <span><span class="sep">///</span> <?= h($dv) ?></span>
      <span><span class="sep">///</span> PHP · 动态</span>
    </div>
  </div>
  <div class="container header-inner">
    <a class="brand" href="/" aria-label="返回首页">
      <span class="brand-mark" aria-hidden="true"><?= h(BRAND) ?><em>.</em></span>
    </a>
    <nav class="nav" aria-label="主导航">
      <a href="/" class="nav-link">查询</a>
      <a href="/radical/" class="nav-link">部首</a>
      <a href="/stroke/" class="nav-link">笔画</a>
      <a href="/about" class="nav-link">关于</a>
    </nav>
  </div>
</header>
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
