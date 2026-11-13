<?php
declare(strict_types=1);

/**
 * 文海文献浏览页（PRD 8.6 / T4.2）。
 * 将《文海》反切数据组织为可浏览的结构化索引。
 *
 * 路由：
 *   /wenhai/           → 总览（按卷统计 + 条目列表入口）
 *   /wenhai/{ref}/     → 该文海条目下的字头 + 反切上字/下字/小韵
 * ref 如 "1.40.221"（卷.韵.条）
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/indexes.php';

$ref = trim((string) ($_GET['ref'] ?? ''));

if ($ref !== '' && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

$page = max(1, (int) ($_GET['p'] ?? 1));

$idx = read_json(shard_dir() . '/index_wenhai.json') ?? [];
$entries = $idx['entries'] ?? [];
$byVolume = $idx['by_volume'] ?? [];

// 无 ref → 总览
if ($ref === '') {
    $title = '文海文献 · ' . SITE_NAME;
    $desc = "《文海》反切数据结构化浏览，共 {$idx['total_refs']} 条目、{$idx['total_chars']} 字。";
    page_start($title, $desc, '/wenhai/');
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> 文献 <span class="sep">///</span> 文海</p>
    <p class="status done"><?= (int) ($idx['total_refs'] ?? 0) ?> 条目 · <?= (int) ($idx['total_chars'] ?? 0) ?> 字 · 反切数据覆盖 51%</p>

    <section>
      <div class="seclabel"><span>按卷统计</span><span class="rule"></span></div>
      <div class="overview-grid">
        <?php foreach ($byVolume as $vol => $info): ?>
        <div class="overview-card">
          <span class="ov-label">卷 <?= h($vol) ?></span>
          <span class="ov-count"><?= (int) $info['refs'] ?> 条目 · <?= (int) $info['count'] ?> 字</span>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section>
      <div class="seclabel"><span>条目列表</span><span class="rule"></span></div>
      <div class="radical-grid">
        <?php
        $gridTotal = count($entries);
        $gridPerPage = 120;
        $gridPages = max(1, (int) ceil($gridTotal / $gridPerPage));
        $page = max(1, min($page, $gridPages));
        $gridSlice = array_slice($entries, ($page - 1) * $gridPerPage, $gridPerPage, true);
        foreach ($gridSlice as $refKey => $info):
        ?>
        <a class="radical-cell" href="/wenhai/<?= h(rawurlencode($refKey)) ?>/">
          <span class="radical-glyph mono" style="font-size:13px"><?= h($refKey) ?></span>
          <span class="radical-count"><?= (int) $info['count'] ?> 字</span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php render_pagination($page, $gridPages, '/wenhai/'); ?>
    </section>
    <p class="backlinks"><a href="/">回首页</a> <span class="sep">///</span> <a href="/tongyin/">同音文献</a></p>
    <?php
    page_end();
    exit;
}

// 有 ref → 条目详情
$decoded = rawurldecode($ref);
if (!isset($entries[$decoded])) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    echo '<p class="status">未找到文海条目「' . h(mb_substr($decoded, 0, 40, 'UTF-8')) . '」。<a href="/wenhai/">回文海总览</a></p>';
    page_end();
    exit;
}

$entry = $entries[$decoded];
$chars = $entry['entries'];
$total = (int) $entry['count'];
$title = '文海 ' . $decoded . ' · 文献 · ' . SITE_NAME;
page_start($title, "《文海》条目 {$decoded}，共 {$total} 字。", '/wenhai/' . $ref . '/');
?>
<p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/wenhai/">文海</a> <span class="sep">///</span> <?= h($decoded) ?></p>

<section class="detail-header">
    <div class="detail-glyph glyph" style="font-family:var(--mono);font-size:24px"><?= h($decoded) ?></div>
    <div class="detail-meta">
        <p class="status done"><span class="chip"><?= $total ?> 字</span><span class="chip"><?= h(data_version()) ?></span></p>
    </div>
</section>

<section class="index" aria-label="文海条目字头">
    <?php foreach ($chars as $e):
        $lfw = (string) ($e['lfw'] ?? '');
        $char = (string) ($e['char'] ?? '');
        $gloss = (string) ($e['gloss'] ?? '');
        $gong = (string) ($e['gong'] ?? '');
        $fqUp = (string) ($e['fanqie_upper'] ?? '');
        $fqLow = (string) ($e['fanqie_lower'] ?? '');
        $fqNote = (string) ($e['fanqie_note'] ?? '');
        $xy = (string) ($e['xiaoyun'] ?? '');
        $xyOrder = (string) ($e['xiaoyun_order'] ?? '');
    ?>
    <article class="row tier-P0">
      <div class="glyph" lang="txg" aria-label="西夏文 <?= h($char) ?>"><?= h($char) ?></div>
      <div class="cell">
        <div class="meta">
          <?php if ($lfw): ?><span class="chip"><a href="/c-<?= h(rawurlencode($lfw)) ?>/">LFW <?= h($lfw) ?> ↗</a></span><?php endif; ?>
          <?php if ($gong): ?><span class="chip">龚煌城：<?= h($gong) ?></span><?php endif; ?>
          <?php if ($xy): ?><span class="chip">小韵 <?= h($xy) ?><?php if ($xyOrder): ?> · 第<?= h($xyOrder) ?></span><?php endif; ?>
          <?php endif; ?>
        </div>
        <?php if ($gloss): ?><p class="gloss">释义：<?= h($gloss) ?></p><?php endif; ?>
        <?php if ($fqUp || $fqLow): ?>
        <p class="gloss" style="color:var(--ink-mute);font-size:13px">
          反切：
          <?php if ($fqUp): ?><span class="glyph-inline" lang="txg"><?= h($fqUp) ?></span><?php endif; ?>
          <?php if ($fqUp && $fqLow): ?> + <?php endif; ?>
          <?php if ($fqLow): ?><span class="glyph-inline" lang="txg"><?= h($fqLow) ?></span><?php endif; ?>
          <?php if ($fqNote): ?>（<?= h($fqNote) ?>）<?php endif; ?>
        </p>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
</section>

<p class="backlinks"><a href="/wenhai/">回文海总览</a> <span class="sep">///</span> <a href="/">回首页</a></p>
<?php
page_end();
