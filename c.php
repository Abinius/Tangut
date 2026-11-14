<?php
declare(strict_types=1);

/**
 * 字头详情页（PRD W-09 / D-07）。
 * 路由：/c-{lfw}/ 或 /c-{U+码位}/ → c.php?c={lfw或码位}
 * 75 字段六组分区展示，空值不渲染，拟音默认展示龚煌城、其余折叠。
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/dict.php';

$raw = trim((string) ($_GET['c'] ?? ''));

// 限流
if ($raw !== '' && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="zh"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>429 · ' . h(SITE_NAME) . '</title><body><p>查询过于频繁，请一分钟后再试。</p></body></html>';
    exit;
}

$detail = $raw !== '' ? find_detail($raw) : null;

if (!$detail) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    ?>
    <p class="status">未找到该字头。请输入有效的 LFW 编号（如 0257）或码位（如 U+1760D）。
      从 <a href="/">首页查询</a> 汉字，再点击候选卡片进入字头详情。</p>
    <?php
    page_end();
    exit;
}

$char = (string) ($detail['字'] ?? '');
$lfw = (string) ($detail['lfw_id'] ?? '');
$unicode = (string) ($detail['unicode'] ?? '');
$gloss = (string) ($detail['释义'] ?? '');
$gong = (string) ($detail['GHC'] ?? '');
$dv = data_version();

// SEO
$glossShort = mb_substr($gloss, 0, 20, 'UTF-8');
$desc = "西夏文字头 {$char}";
if ($gloss) {
    $desc .= "（{$glossShort}）";
}
if ($gong) {
    $desc .= "，龚煌城拟音 {$gong}";
}
$desc .= "。LFW {$lfw}，{$unicode}，75 字段全量详情。";
$title = "{$char} · LFW {$lfw} · 西夏文字头 · " . SITE_NAME;
$canonical = '/c-' . rawurlencode($lfw ?: $unicode) . '/';

page_start($title, $desc, $canonical, $title, $desc, false, true);
?>
<section class="search">
  <div class="search-bar">
    <form method="get" action="/" style="display:flex;flex:1;gap:12px;min-width:0">
      <input type="search" name="q" placeholder="输入汉字 / LFW 编号 / 码位" aria-label="输入汉字、LFW 编号或码位" autocomplete="off">
      <?php search_btn('查询'); ?>
    </form>
  </div>
</section>
<?php
render_detail_page($detail);
page_end();
