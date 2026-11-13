<?php
declare(strict_types=1);

/**
 * 检字索引加载与列表渲染（PRD D-08 / T2.3–T2.4）。
 * 部首四体系 + 笔画数索引，供 radical.php / stroke.php 共用。
 */

require_once __DIR__ . '/data.php';

/** 加载部首索引（进程内缓存） */
function radical_index(): array
{
    static $idx = null;
    if ($idx === null) {
        $idx = read_json(shard_dir() . '/index_radical.json') ?? [];
    }
    return $idx;
}

/** 加载笔画索引（进程内缓存） */
function stroke_index(): array
{
    static $idx = null;
    if ($idx === null) {
        $idx = read_json(shard_dir() . '/index_stroke.json') ?? [];
    }
    return $idx;
}

/**
 * 渲染字头列表（检字结果页用，带分页）。
 * 返回总页数。
 */
function render_char_list(array $entries, int $page = 1, int $perPage = 60): int
{
    $total = count($entries);
    if ($total === 0) {
        echo '<p class="empty">无结果</p>';
        return 1;
    }
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($entries, $offset, $perPage);

    echo '<section class="index" aria-label="字头列表">';
    foreach ($slice as $e) {
        $lfw = (string) ($e['lfw'] ?? '');
        $char = (string) ($e['char'] ?? '');
        $unicode = (string) ($e['unicode'] ?? '');
        $gloss = (string) ($e['gloss'] ?? '');
        $gong = (string) ($e['gong'] ?? '');
        ?>
  <article class="row tier-P0">
    <div class="glyph" lang="txg" aria-label="西夏文 <?= h($char) ?>"><?= h($char) ?></div>
    <div class="cell">
      <div class="meta">
        <?php if ($lfw): ?>
        <span class="chip"><a href="/c-<?= h(rawurlencode($lfw)) ?>/">LFW <?= h($lfw) ?> ↗</a></span>
        <?php endif; ?>
        <?php if ($unicode): ?><span class="chip"><?= h($unicode) ?></span><?php endif; ?>
        <?php if ($gong): ?><span class="chip">龚煌城：<?= h($gong) ?></span><?php endif; ?>
      </div>
      <?php if ($gloss): ?><p class="gloss">释义：<?= h($gloss) ?></p><?php endif; ?>
    </div>
  </article>
        <?php
    }
    echo '</section>';
    return $pages;
}

/** 渲染分页导航 */
function render_pagination(int $page, int $pages, string $baseUrl): void
{
    if ($pages <= 1) {
        return;
    }
    $page = max(1, min($page, $pages));
    echo '<nav class="pagination" aria-label="分页">';
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $page) {
            echo '<span class="page-current">' . $i . '</span>';
        } else {
            echo '<a href="' . h($baseUrl . $sep . 'p=' . $i) . '">' . $i . '</a>';
        }
    }
    echo '</nav>';
}
