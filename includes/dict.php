<?php
declare(strict_types=1);

/**
 * 字头详情页渲染（PRD D-07 / W-09 / T1.3）。
 * 六组分区展示 75 字段，空值不渲染，拟音默认展示龚煌城、其余折叠。
 * 字段定义与 build_field_report.py GROUPS 一致。
 */

require_once __DIR__ . '/data.php';

/** 西夏文字形字段（需 lang="txg" + 字体渲染） */
const TANGUT_FIELDS = [
    '字', 'Unicode部首', '夏漢字典部首', '夏俄英漢詞典部首', '西夏文詞典部首',
    '部件', '结构', '声符',
    '文海 反切上字', '文海 反切下字',
    '合編 反切A上字', '合編 反切A下字', '合編 反切A下字.1', '合編 反切B上字',
];

/** 六组字段定义：field_key => label */
const DETAIL_GROUPS = [
    '编号' => [
        'title' => '编号体系',
        'fields' => [
            'LFW' => '李范文编号', 'LFW 1997' => 'LFW 1997', 'No.' => '夏漢字典序號',
            'Sofronov序號' => 'Sofronov 序号', 'Sofronov 1968' => 'Sofronov 1968',
            '夏俄英漢詞典序號' => '夏俄英漢詞典序号', 'Kychanov 2006' => 'Kychanov 2006',
            '韓小忙序號' => '韓小忙序号', '韓小忙 2008' => '韓小忙 2008',
            '西夏文詞典' => '西夏文詞典位置', '西田 1966' => '西田 1966',
        ],
    ],
    '拟音' => [
        'title' => '拟音',
        'fields' => [
            'GHC' => '龚煌城', 'GX' => 'GX', 'Kychanov2006' => 'Kychanov 2006',
            'Miyake2019' => 'Miyake 2019', 'Nishida1966' => '聂历山 1966',
            'Sofronov1968' => 'Sofronov 1968', '上标声调' => '上标声调',
        ],
    ],
    '音韵' => [
        'title' => '音韵',
        'fields' => [
            '同音' => '同音', '韵类' => '韵类', '声调' => '声调', '声調' => '声調',
            '声調韻' => '声調韻', '総合韻' => '総合韻', '小韵' => '小韵',
            '声母' => '声母', '等' => '等', '声符' => '声符', 'TYA' => 'TYA', 'TYB' => 'TYB',
            '新版同音' => '新版同音', '新版同音 小類' => '新版同音 小類',
            '新版同音 小類内順序' => '新版同音 小類内順序', 'sort(新版同音)' => 'sort(新版同音)',
            '旧版同音' => '旧版同音', '旧版同音 声母' => '旧版同音 声母',
            '旧版同音 小類' => '旧版同音 小類', '旧版同音 小類内順序' => '旧版同音 小類内順序',
            '文海' => '文海', '文海 反切上字' => '文海 反切上字', '文海 反切下字' => '文海 反切下字',
            '文海 反切注' => '文海 反切注', '文海 小韻' => '文海 小韻',
            '文海 小韻内順序' => '文海 小韻内順序', 'sort(文海)' => 'sort(文海)',
            '文海寶韻' => '文海寶韻', '文海寶韻 小韻' => '文海寶韻 小韻',
            '文海寶韻小韻内順序' => '文海寶韻小韻内順序',
            '合編' => '合編', '合編 反切A上字' => '合編 反切A上字',
            '合編 反切A下字' => '合編 反切A下字', '合編 反切A下字.1' => '合編 反切A下字.1',
            '合編 反切B上字' => '合編 反切B上字', '合編 反切注' => '合編 反切注',
            'Cₚ' => 'Cₚ', 'Cᵢ' => 'Cᵢ', 'Cₘ' => 'Cₘ', 'V₁' => 'V₁', 'V₂' => 'V₂',
            'Cf' => 'Cf', '[±紧 ±卷舌]' => '[±紧 ±卷舌]',
        ],
    ],
    '检字' => [
        'title' => '检字与字形',
        'fields' => [
            '四角号码' => '四角号码', '笔画' => '笔画', '仓颉码' => '仓颉码',
            'N4522笔画码' => 'N4522 笔画码', '结构' => '结构 (IDS)', '部件' => '部件',
        ],
    ],
    '部首' => [
        'title' => '部首',
        'fields' => [
            'Unicode部首' => 'Unicode 部首', '夏漢字典部首' => '夏漢字典部首',
            '夏俄英漢詞典部首' => '夏俄英漢詞典部首', '西夏文詞典部首' => '西夏文詞典部首',
        ],
    ],
    '释义' => [
        'title' => '释义',
        'fields' => [
            '释义' => '中文释义', '英文释义' => '英文释义', 'Notes' => '备注',
        ],
    ],
];

/** 取字段值（标量化、去空白） */
function field_value(array $detail, string $field): string
{
    $v = $detail[$field] ?? '';
    if (is_array($v)) {
        $v = implode(', ', array_map('strval', $v));
    } elseif (is_float($v) || is_int($v)) {
        // 整数化浮点（tangutdata 中笔画/序号存为 10.0、392.0）
        $v = $v == (int) $v ? (string) (int) $v : (string) $v;
    }
    return trim((string) $v);
}

function is_tangut_field(string $field): bool
{
    return in_array($field, TANGUT_FIELDS, true);
}

/** 深链：字段值 → 对应检字/文献页 URL（无对应页返回 null）。 */
function field_link(string $field, string $val): ?string
{
    if ($val === '') {
        return null;
    }
    return match ($field) {
        '四角号码' => '/sijiao/' . rawurlencode($val) . '/',
        '仓颉码'   => '/cangjie/' . rawurlencode($val) . '/',
        '结构'     => '/ids/' . rawurlencode($val) . '/',
        '笔画'     => '/stroke/' . rawurlencode($val) . '/',
        '文海'     => '/wenhai/' . rawurlencode($val) . '/',
        '同音'     => '/tongyin/' . rawurlencode($val) . '/',
        default    => null,
    };
}

/** 渲染单行字段；空值返回 false（调用方跳过） */
function render_field_row(array $detail, string $field, string $label): bool
{
    $val = field_value($detail, $field);
    if ($val === '') {
        return false;
    }
    $isTangut = is_tangut_field($field);
    $link = field_link($field, $val);
    ?>
    <div class="field-row">
      <dt><?= h($label) ?></dt>
      <dd<?php if ($isTangut): ?> class="glyph-inline" lang="txg"<?php endif; ?>>
        <?php if ($link): ?><a class="deep-link" href="<?= h($link) ?>"><?= h($val) ?> ↗</a>
        <?php else: ?><?= h($val) ?><?php endif; ?>
      </dd>
    </div>
    <?php
    return true;
}

/** 渲染普通字段组（空值不渲染，整组空则不输出 section） */
function render_detail_group(array $detail, string $groupKey, array $group): void
{
    $shown = 0;
    ob_start();
    foreach ($group['fields'] as $field => $label) {
        if (render_field_row($detail, $field, $label)) {
            $shown++;
        }
    }
    $html = ob_get_clean();
    if ($shown === 0) {
        return;
    }
    ?>
    <section class="detail-group" id="<?= h($groupKey) ?>">
      <div class="seclabel"><span><?= h($group['title']) ?></span><span class="rule"></span></div>
      <dl class="field-list"><?= $html ?></dl>
    </section>
    <?php
}

/** 渲染拟音组：GHC 默认展示，其余折叠 */
function render_phonetics_group(array $detail, string $groupKey, array $group): void
{
    $gong = field_value($detail, 'GHC');
    $tone = field_value($detail, '上标声调');
    $others = [];
    foreach ($group['fields'] as $field => $label) {
        if ($field === 'GHC' || $field === '上标声调') {
            continue;
        }
        $val = field_value($detail, $field);
        if ($val !== '') {
            $others[$field] = ['label' => $label, 'val' => $val];
        }
    }
    if ($gong === '' && !$others && $tone === '') {
        return;
    }
    ?>
    <section class="detail-group" id="<?= h($groupKey) ?>">
      <div class="seclabel"><span><?= h($group['title']) ?></span><span class="rule"></span></div>
      <dl class="field-list">
        <?php if ($gong): ?>
        <div class="field-row">
          <dt>龚煌城（默认）</dt>
          <dd class="mono"><?= h($gong) ?><?php if ($tone): ?> <span class="tone-mark"><?= h($tone) ?></span><?php endif; ?></dd>
        </div>
        <?php endif; ?>
      </dl>
      <?php if ($others): ?>
      <details class="phonetics-fold">
        <summary>展开全部拟音（<?= count($others) ?> 家）</summary>
        <dl class="field-list">
          <?php foreach ($others as $info): ?>
          <div class="field-row"><dt><?= h($info['label']) ?></dt><dd class="mono"><?= h($info['val']) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </details>
      <?php endif; ?>
    </section>
    <?php
}

/** 统计非空字段数（用于页面元信息） */
function count_nonempty_fields(array $detail): int
{
    $n = 0;
    foreach (DETAIL_GROUPS as $group) {
        foreach ($group['fields'] as $field => $_) {
            if (field_value($detail, $field) !== '') {
                $n++;
            }
        }
    }
    return $n;
}

/** 渲染详情页主体 */
function render_detail_page(array $detail): void
{
    $char = (string) ($detail['字'] ?? '');
    $lfw = (string) ($detail['lfw_id'] ?? '');
    $unicode = (string) ($detail['unicode'] ?? '');
    $gloss = (string) ($detail['释义'] ?? '');
    $glossEn = (string) ($detail['英文释义'] ?? '');
    $gong = (string) ($detail['GHC'] ?? '');
    $dv = (string) ($detail['data_version'] ?? '');
    $nonempty = count_nonempty_fields($detail);
    $total = 0;
    foreach (DETAIL_GROUPS as $group) {
        $total += count($group['fields']);
    }
    ?>
    <p class="breadcrumb" aria-label="面包屑">
      <a href="/">查询</a> <span class="sep">///</span> 字头 <span class="sep">///</span> <?= h($lfw ?: $unicode) ?>
    </p>

    <section class="detail-header">
      <div class="detail-glyph glyph" lang="txg" aria-label="西夏文 <?= h($char) ?>"><?= h($char) ?></div>
      <div class="detail-meta">
        <p class="status done">
          <?php if ($unicode): ?><span class="chip"><?= h($unicode) ?></span><?php endif; ?>
          <?php if ($gloss): ?><span class="chip">释义：<?= h($gloss) ?></span><?php endif; ?>
          <?php if ($gong): ?><span class="chip">龚煌城：<?= h($gong) ?></span><?php endif; ?>
          <span class="chip"><?= $nonempty ?>/<?= $total ?> 字段</span>
          <span class="chip"><?= h($dv) ?></span>
        </p>
        <?php if ($glossEn): ?>
        <p class="status" style="color:var(--ink-mute)"><?= h($glossEn) ?></p>
        <?php endif; ?>
      </div>
    </section>

    <?php
    foreach (DETAIL_GROUPS as $gkey => $group) {
        if ($gkey === '拟音') {
            render_phonetics_group($detail, $gkey, $group);
        } else {
            render_detail_group($detail, $gkey, $group);
        }
    }
    ?>
    <p class="backlinks">
      <a href="/">回首页查询</a> <span class="sep">///</span> <a href="/about">关于与致谢</a>
    </p>
    <?php
}
