<?php
declare(strict_types=1);

/**
 * 通用分组检字页（PRD W-10 / D-08 / T3.1–T3.3）。
 * 用于四角号码 / 声韵三体系 / 仓颉码 / IDS 结构 —— 均为 value → [字头列表] 的分组索引。
 *
 * 路由由 .htaccess / router.php 分发到本文件，通过 $_GET['idx'] 区分索引类型：
 *   sijiao  → index_sijiao.json  （四角号码）
 *   cangjie → index_cangjie.json （仓颉码）
 *   ids     → index_ids.json     （IDS 结构）
 * 声韵由 rhyme.php 单独处理（三体系嵌套）。
 *
 * URL 模式：
 *   /sijiao/              → 总览（所有分组，按字数降序）
 *   /sijiao/{value}/      → 该分组的字头列表（分页）
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/indexes.php';

$idxType = trim((string) ($_GET['idx'] ?? ''));
$value = trim((string) ($_GET['v'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));

// 索引类型配置
$IDX_CONFIG = [
    'sijiao'  => ['file' => 'index_sijiao.json', 'title' => '四角号码检字', 'label' => '四角号码', 'route' => '/sijiao/', 'value_label' => '四角号码'],
    'cangjie' => ['file' => 'index_cangjie.json', 'title' => '仓颉码检字', 'label' => '仓颉码', 'route' => '/cangjie/', 'value_label' => '仓颉码'],
    'ids'     => ['file' => 'index_ids.json', 'title' => 'IDS 结构检字', 'label' => '结构 (IDS)', 'route' => '/ids/', 'value_label' => '结构'],
];

if (!isset($IDX_CONFIG[$idxType])) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    echo '<p class="status">未知检字类型。</p>';
    page_end();
    exit;
}

$cfg = $IDX_CONFIG[$idxType];
if ($value !== '' && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

$idx = read_json(shard_dir() . '/' . $cfg['file']) ?? [];
$groups = $idx['groups'] ?? [];
$label = $cfg['label'];
$route = $cfg['route'];

// 无 value → 总览
if ($value === '') {
    $title = $cfg['title'] . ' · ' . SITE_NAME;
    $desc = "按{$label}浏览西夏文字头，共 {$idx['group_count']} 组。";
    page_start($title, $desc, $route);
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> 检字 <span class="sep">///</span> <?= h($label) ?></p>
    <p class="status done"><?= (int) ($idx['group_count'] ?? 0) ?> 组 · <?= (int) ($idx['total_chars'] ?? 0) ?> 字 · 按字数降序</p>
    <section class="radical-grid" aria-label="<?= h($label) ?>列表">
        <?php
        $sorted = $groups;
        uasort($sorted, static fn($a, $b) => $b['count'] <=> $a['count']);
        $gridTotal = count($sorted);
        $gridPerPage = 120;
        $gridPages = max(1, (int) ceil($gridTotal / $gridPerPage));
        $page = max(1, min($page, $gridPages));
        $gridSlice = array_slice($sorted, ($page - 1) * $gridPerPage, $gridPerPage, true);
        foreach ($gridSlice as $val => $g):
            if ($g['count'] < 1) continue;
            $encoded = rawurlencode($val);
        ?>
        <a class="radical-cell" href="<?= h($route . $encoded . '/') ?>">
            <span class="radical-glyph mono" style="font-size:14px"><?= h(mb_substr($val, 0, 8, 'UTF-8')) ?></span>
            <span class="radical-count"><?= (int) $g['count'] ?></span>
        </a>
        <?php endforeach; ?>
    </section>
    <?php render_pagination($page, $gridPages, $route); ?>
    <?php
    page_end();
    exit;
}

// 有 value → 字头列表
$decoded = rawurldecode($value);
if (!isset($groups[$decoded])) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    ?>
    <p class="status">未找到 <?= h($cfg['value_label']) ?> 「<?= h(mb_substr($decoded, 0, 40, 'UTF-8')) ?>」。
      请从 <a href="<?= h($route) ?>"><?= h($label) ?>总览</a> 选择。</p>
    <?php
    page_end();
    exit;
}

$g = $groups[$decoded];
$entries = $g['entries'];
$total = (int) $g['count'];
$title = $decoded . ' · ' . $cfg['title'] . ' · ' . SITE_NAME;
$desc = "{$cfg['value_label']}「{$decoded}」，共 {$total} 字。";
page_start($title, $desc, $route . $value . '/');
?>
<p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="<?= h($route) ?>"><?= h($label) ?></a> <span class="sep">///</span> <?= h(mb_substr($decoded, 0, 30, 'UTF-8')) ?></p>

<section class="detail-header">
    <div class="detail-glyph glyph" style="font-family:var(--mono);font-size:24px;word-break:break-all"><?= h(mb_substr($decoded, 0, 12, 'UTF-8')) ?></div>
    <div class="detail-meta">
        <p class="status done"><span class="chip"><?= $total ?> 字</span><span class="chip"><?= h(data_version()) ?></span></p>
    </div>
</section>

<?php
$pages = render_char_list($entries, $page, 60);
render_pagination($page, $pages, $route . $value . '/');
?>
<p class="backlinks"><a href="<?= h($route) ?>">回<?= h($label) ?>总览</a> <span class="sep">///</span> <a href="/">回首页查询</a></p>
<?php
page_end();
