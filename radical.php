<?php
declare(strict_types=1);

/**
 * 部首检字页（PRD W-10 / D-08 / T2.3）。
 * 路由：
 *   /radical/                     → 四体系总览
 *   /radical/{system}/            → 该体系部首列表（按字数降序）
 *   /radical/{system}/{cp}/       → 该部首下的字头列表（分页）
 * system: unicode / xh / eyh / xwd
 * cp: 部首码位（无 U+ 前缀，如 1761D）
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/indexes.php';

$system = trim((string) ($_GET['system'] ?? ''));
$radicalCp = trim((string) ($_GET['r'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));

if ($system !== '' && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

$idx = radical_index();
$systems = $idx['systems'] ?? [];

// 无 system → 总览页
if ($system === '' || !isset($systems[$system])) {
    $title = '部首检字 · ' . SITE_NAME;
    $desc = '按四套部首体系（Unicode / 夏漢字典 / 夏俄英漢詞典 / 西夏文詞典）浏览西夏文字头。';
    page_start($title, $desc, '/radical/');
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> 检字 <span class="sep">///</span> 部首</p>
    <h1 class="page-title">部首检字</h1>
    <p class="status done">四套部首体系，按体系浏览全部部首，点击进入字头列表。</p>
    <section class="index">
        <?php foreach ($systems as $sk => $sv): ?>
        <article class="row tier-P0">
            <div class="cell">
                <div class="meta">
                    <span class="tier"><?= h($sv['label']) ?></span>
                    <span class="chip"><?= (int) $sv['radical_count'] ?> 部首</span>
                    <span class="chip"><?= (int) $sv['total_chars'] ?> 字</span>
                </div>
                <p class="gloss"><a href="/radical/<?= h($sk) ?>/">浏览全部 <?= h($sv['label']) ?> →</a></p>
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
$radicals = $sys['radicals'] ?? [];

// 有 system 无 radical → 部首列表
if ($radicalCp === '') {
    $title = $sysLabel . '检字 · ' . SITE_NAME;
    $desc = "按{$sysLabel}浏览西夏文字头，共 {$sys['radical_count']} 个部首。";
    $canonical = '/radical/' . $system . '/';
    page_start($title, $desc, $canonical);
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/radical/">部首</a> <span class="sep">///</span> <?= h($sysLabel) ?></p>
    <h1 class="page-title"><?= h($sysLabel) ?> <span class="arrow">·</span> <?= (int) $sys['radical_count'] ?> 部首</h1>
    <p class="status done"><?= (int) $sys['total_chars'] ?> 字 · 按字数降序</p>
    <section class="radical-grid" aria-label="部首列表">
        <?php
        // 按字数降序排列
        $sorted = $radicals;
        uasort($sorted, static fn($a, $b) => $b['count'] <=> $a['count']);
        foreach ($sorted as $cp => $r):
            $cpClean = str_replace('U+', '', $cp);
        ?>
        <a class="radical-cell" href="/radical/<?= h($system) ?>/<?= h($cpClean) ?>/">
            <span class="glyph radical-glyph" lang="txg"><?= h($r['char']) ?></span>
            <span class="radical-count"><?= (int) $r['count'] ?></span>
        </a>
        <?php endforeach; ?>
    </section>
    <?php
    page_end();
    exit;
}

// 有 system + radical → 字头列表
$cpKey = 'U+' . strtoupper(ltrim($radicalCp, 'U+'));
$cpClean = str_replace('U+', '', $cpKey);
if (!isset($radicals[$cpKey])) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    ?>
    <h1 class="page-title">404<span class="arrow">.</span></h1>
    <p class="status">未找到该部首。请从 <a href="/radical/<?= h($system) ?>/">部首列表</a> 选择。</p>
    <?php
    page_end();
    exit;
}

$r = $radicals[$cpKey];
$entries = $r['entries'];
$total = $r['count'];
$title = $r['char'] . ' 部 · ' . $sysLabel . ' · ' . SITE_NAME;
$desc = "{$sysLabel}「{$r['char']}」部，共 {$total} 字。";
$canonical = '/radical/' . $system . '/' . $cpClean . '/';
page_start($title, $desc, '/radical/' . $system . '/' . $cpClean . '/');
?>
<p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/radical/">部首</a> <span class="sep">///</span> <a href="/radical/<?= h($system) ?>/"><?= h($sysLabel) ?></a> <span class="sep">///</span> <?= h($r['char']) ?> 部</p>

<section class="detail-header">
    <div class="detail-glyph glyph" lang="txg" aria-label="部首 <?= h($r['char']) ?>"><?= h($r['char']) ?></div>
    <div class="detail-meta">
        <h1 class="page-title"><?= h($r['char']) ?> <span class="arrow">·</span> <?= h($sysLabel) ?></h1>
        <p class="status done">
            <span class="chip"><?= (int) $total ?> 字</span>
            <span class="chip"><?= h($cpKey) ?></span>
            <span class="chip"><?= h(data_version()) ?></span>
        </p>
    </div>
</section>

<?php
$pages = render_char_list($entries, $page, 60);
render_pagination($page, $pages, '/radical/' . $system . '/' . $cpClean . '/');
?>
<p class="backlinks"><a href="/radical/<?= h($system) ?>/">回<?= h($sysLabel) ?>部首列表</a> <span class="sep">///</span> <a href="/">回首页查询</a></p>
<?php
page_end();
