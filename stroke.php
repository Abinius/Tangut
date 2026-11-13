<?php
declare(strict_types=1);

/**
 * 笔画检字页（PRD W-10 / D-08 / T2.4）。
 * 路由：
 *   /stroke/        → 笔画总览（4–22 画入口）
 *   /stroke/{n}/    → n 画的字头列表（分页）
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/indexes.php';

$n = trim((string) ($_GET['n'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));

if ($n !== '' && rate_limited()) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

$idx = stroke_index();
$strokes = $idx['strokes'] ?? [];
$min = (int) ($idx['min'] ?? 0);
$max = (int) ($idx['max'] ?? 0);

// 无效笔画数 → 404（与其余检字页一致，避免软 404）
if ($n !== '' && !isset($strokes[$n])) {
    http_response_code(404);
    page_start('404 · ' . SITE_NAME, '页面不存在', '', null, null, true);
    echo '<p class="status">未找到「' . h($n) . '」画字头。<a href="/stroke/">回笔画总览</a></p>';
    page_end();
    exit;
}

// 无 n → 总览
if ($n === '') {
    $title = '笔画检字 · ' . SITE_NAME;
    $desc = "按笔画数浏览西夏文字头，{$min}–{$max} 画。";
    page_start($title, $desc, '/stroke/');
    ?>
    <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> 检字 <span class="sep">///</span> 笔画</p>
    <p class="status done"><?= $min ?>–<?= $max ?> 画 · 按笔画数浏览字头</p>
    <section class="stroke-grid stroke-grid-5" aria-label="笔画入口">
        <?php for ($i = $min; $i <= $max; $i++): 
            $sn = (string) $i;
            if (!isset($strokes[$sn])) continue;
            $cnt = (int) $strokes[$sn]['count'];
        ?>
        <a class="stroke-cell" href="/stroke/<?= $i ?>/">
            <span class="stroke-num"><?= $i ?></span>
            <span class="stroke-count"><?= $cnt ?> 字</span>
        </a>
        <?php endfor; ?>
    </section>
    <?php
    page_end();
    exit;
}

// 有 n → 字头列表
$entry = $strokes[$n];
$entries = $entry['entries'];
$total = (int) $entry['count'];
$title = $n . ' 画字头 · 笔画检字 · ' . SITE_NAME;
$desc = "笔画数 {$n} 的西夏文字头，共 {$total} 字。";
page_start($title, $desc, '/stroke/' . $n . '/');
?>
<p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> <a href="/stroke/">笔画</a> <span class="sep">///</span> <?= h($n) ?> 画</p>

<section class="detail-header">
    <div class="detail-glyph glyph" style="font-family:var(--mono);font-size:48px"><?= h($n) ?></div>
    <div class="detail-meta">
        <p class="status done">
            <span class="chip"><?= $total ?> 字</span>
            <span class="chip"><?= h(data_version()) ?></span>
        </p>
    </div>
</section>

<?php
$pages = render_char_list($entries, $page, 60);
render_pagination($page, $pages, '/stroke/' . $n . '/');
?>
<p class="backlinks"><a href="/stroke/">回笔画总览</a> <span class="sep">///</span> <a href="/">回首页查询</a></p>
<?php
page_end();
