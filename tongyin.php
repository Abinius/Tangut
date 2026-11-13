<?php
declare(strict_types=1);

/**
 * 同音文献对照页（PRD 8.6 / T4.3）。
 * 按《同音》组浏览字头，展示新版/旧版同音小类对比。
 *
 * 路由：
 *   /tongyin/             → 总览（同音组列表）
 *   /tongyin/{group}/     → 该组字头 + 新旧版同音对照
 * group 如 "Ⅵ-101"
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/indexes.php';

$group = trim((string) ($_GET['group'] ?? ''));

if ($group !== '' && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

$idx = read_json(shard_dir() . '/index_tongyin_full.json') ?? [];
$groups = $idx['groups'] ?? [];

// 无 group → 总览
if ($group === '') {
    $title = '同音文献 · ' . SITE_NAME;
    $desc = "《同音》分组浏览，新版/旧版小类对照，共 {$idx['total_groups']} 组、{$idx['total_chars']} 字。";
    page_start($title, $desc, '/tongyin/');
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> 文献 <span class="sep">///</span> 同音</p>
    <p class="status done"><?= (int) ($idx['total_groups'] ?? 0) ?> 组 · <?= (int) ($idx['total_chars'] ?? 0) ?> 字 · 覆盖 100%</p>
    <section class="radical-grid" aria-label="同音组列表">
        <?php foreach ($groups as $gk => $g): ?>
        <a class="radical-cell" href="/tongyin/<?= h(rawurlencode($gk)) ?>/">
            <span class="radical-glyph mono" style="font-size:14px"><?= h($gk) ?></span>
            <span class="radical-count"><?= (int) $g['count'] ?> 字</span>
        </a>
        <?php endforeach; ?>
    </section>
    <p class="backlinks"><a href="/">回首页</a> <span class="sep">///</span> <a href="/wenhai/">文海文献</a></p>
    <?php
    page_end();
    exit;
}

// 有 group → 对照详情
$decoded = rawurldecode($group);
if (!isset($groups[$decoded])) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    echo '<p class="status">未找到同音组「' . h($decoded) . '」。<a href="/tongyin/">回同音总览</a></p>';
    page_end();
    exit;
}

$g = $groups[$decoded];
$chars = $g['entries'];
$total = (int) $g['count'];
$title = '同音 ' . $decoded . ' · 文献 · ' . SITE_NAME;
page_start($title, "《同音》组 {$decoded}，共 {$total} 字，新版/旧版小类对照。", '/tongyin/' . $group . '/');
?>
<p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/tongyin/">同音</a> <span class="sep">///</span> <?= h($decoded) ?></p>

<section class="detail-header">
    <div class="detail-glyph glyph" style="font-family:var(--mono);font-size:28px"><?= h($decoded) ?></div>
    <div class="detail-meta">
        <p class="status done"><span class="chip"><?= $total ?> 字</span><span class="chip"><?= h(data_version()) ?></span></p>
    </div>
</section>

<section class="index" aria-label="同音组字头">
    <?php foreach ($chars as $e):
        $lfw = (string) ($e['lfw'] ?? '');
        $char = (string) ($e['char'] ?? '');
        $gloss = (string) ($e['gloss'] ?? '');
        $gong = (string) ($e['gong'] ?? '');
        $sm = (string) ($e['shengmu'] ?? '');
        $yl = (string) ($e['yunlei'] ?? '');
        $sd = (string) ($e['shengdiao'] ?? '');
        $newTy = (string) ($e['new_tongyin'] ?? '');
        $newXl = (string) ($e['new_xiaolei'] ?? '');
        $newOrd = (string) ($e['new_order'] ?? '');
        $oldTy = (string) ($e['old_tongyin'] ?? '');
        $oldXl = (string) ($e['old_xiaolei'] ?? '');
        $oldOrd = (string) ($e['old_order'] ?? '');
    ?>
    <article class="row tier-P0">
      <div class="glyph" lang="txg" aria-label="西夏文 <?= h($char) ?>"><?= h($char) ?></div>
      <div class="cell">
        <div class="meta">
          <?php if ($lfw): ?><span class="chip"><a href="/c-<?= h(rawurlencode($lfw)) ?>/">LFW <?= h($lfw) ?> ↗</a></span><?php endif; ?>
          <?php if ($gong): ?><span class="chip">龚煌城：<?= h($gong) ?></span><?php endif; ?>
          <?php if ($sm): ?><span class="chip">声母 <?= h($sm) ?></span><?php endif; ?>
          <?php if ($yl): ?><span class="chip">韵类 <?= h($yl) ?></span><?php endif; ?>
          <?php if ($sd): ?><span class="chip">声调 <?= h($sd) ?></span><?php endif; ?>
        </div>
        <?php if ($gloss): ?><p class="gloss">释义：<?= h($gloss) ?></p><?php endif; ?>
        <p class="gloss" style="color:var(--ink-mute);font-size:13px">
          新版同音：<?= $newTy ? h($newTy) . ' · 小类' . h($newXl) . ' · 第' . h($newOrd) : '—' ?>
          <span class="sep">///</span>
          旧版同音：<?= $oldTy ? h($oldTy) . ' · 小类' . h($oldXl) . ' · 第' . h($oldOrd) : '—' ?>
        </p>
      </div>
    </article>
    <?php endforeach; ?>
</section>

<p class="backlinks"><a href="/tongyin/">回同音总览</a> <span class="sep">///</span> <a href="/">回首页</a></p>
<?php
page_end();
