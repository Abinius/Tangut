<?php
declare(strict_types=1);

/**
 * 声韵检字页（PRD W-10 / D-08 / T3.2）。
 * 三体系：同音 / 文海 / 韵类
 *
 * 路由：
 *   /rhyme/                   → 三体系总览
 *   /rhyme/{system}/          → 该体系分组列表（按字数降序）
 *   /rhyme/{system}/{value}/  → 该分组的字头列表（分页）
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/indexes.php';

$system = trim((string) ($_GET['system'] ?? ''));
$value = trim((string) ($_GET['v'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));

if ($system !== '' && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

$idx = read_json(shard_dir() . '/index_rhyme.json') ?? [];
$systems = $idx['systems'] ?? [];

// 无 system → 总览
if ($system === '' || !isset($systems[$system])) {
    page_start('声韵检字 · ' . SITE_NAME, '按同音 / 文海 / 韵类三体系浏览西夏文字头。', '/rhyme/');
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> 检字 <span class="sep">///</span> 声韵</p>
    <p class="status done">三体系：同音 / 文海 / 韵类</p>
    <section class="overview-grid overview-grid-3">
        <?php foreach ($systems as $sk => $sv): ?>
        <article class="row tier-P0 overview-mini">
            <div class="cell">
                <span class="overview-ic"><?= system_icon($sk) ?></span>
                <h2 class="overview-title"><?= h($sv['label']) ?></h2>
                <div class="meta">
                    <span class="chip"><?= (int) $sv['group_count'] ?> 组</span>
                    <span class="chip"><?= (int) $sv['total_chars'] ?> 字</span>
                </div>
                <p class="gloss"><a href="/rhyme/<?= h($sk) ?>/">查看全部 →</a></p>
            </div>
        </article>
        <?php endforeach; ?>
    </section>
    <?php
    page_end();
    exit;
}

$sys = $systems[$system];
$sysLabel = $sys['label'];
$groups = $sys['groups'] ?? [];
$route = '/rhyme/' . $system . '/';

// 有 system 无 value → 分组列表
if ($value === '') {
    page_start($sysLabel . '检字 · ' . SITE_NAME, "按{$sysLabel}浏览西夏文字头，共 {$sys['group_count']} 组。", $route);
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/rhyme/">声韵</a> <span class="sep">///</span> <?= h($sysLabel) ?></p>
    <p class="status done"><?= (int) $sys['total_chars'] ?> 字 · 按字数降序</p>
    <section class="radical-grid" aria-label="分组列表">
        <?php
        $sorted = $groups;
        uasort($sorted, static fn($a, $b) => $b['count'] <=> $a['count']);
        $gridTotal = count($sorted);
        $gridPerPage = 120;
        $gridPages = max(1, (int) ceil($gridTotal / $gridPerPage));
        $page = max(1, min($page, $gridPages));
        $gridSlice = array_slice($sorted, ($page - 1) * $gridPerPage, $gridPerPage, true);
        foreach ($gridSlice as $val => $g):
        ?>
        <a class="radical-cell" href="<?= h($route . rawurlencode((string) $val) . '/') ?>">
            <span class="radical-glyph mono" style="font-size:14px"><?= h($val) ?></span>
            <span class="radical-count"><?= (int) $g['count'] ?></span>
        </a>
        <?php endforeach; ?>
    </section>
    <?php render_pagination($page, $gridPages, $route); ?>
    <?php
    page_end();
    exit;
}

// 有 system + value → 字头列表
$decoded = rawurldecode($value);
if (!isset($groups[$decoded])) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    echo '<p class="status">未找到该分组。<a href="' . h($route) . '">回' . h($sysLabel) . '总览</a></p>';
    page_end();
    exit;
}

$g = $groups[$decoded];
$entries = $g['entries'];
$total = (int) $g['count'];
page_start($decoded . ' · ' . $sysLabel . ' · ' . SITE_NAME, "{$sysLabel}「{$decoded}」，共 {$total} 字。", $route . $value . '/');
?>
<p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/rhyme/">声韵</a> <span class="sep">///</span> <a href="<?= h($route) ?>"><?= h($sysLabel) ?></a> <span class="sep">///</span> <?= h($decoded) ?></p>

<section class="detail-header">
    <div class="detail-glyph glyph" style="font-family:var(--mono);font-size:28px"><?= h($decoded) ?></div>
    <div class="detail-meta">
        <p class="status done"><span class="chip"><?= $total ?> 字</span><span class="chip"><?= h(data_version()) ?></span></p>
    </div>
</section>

<?php
$pages = render_char_list($entries, $page, 60);
render_pagination($page, $pages, $route . $value . '/');
?>
<p class="backlinks"><a href="<?= h($route) ?>">回<?= h($sysLabel) ?>总览</a> <span class="sep">///</span> <a href="/">回首页查询</a></p>
<?php
page_end();
