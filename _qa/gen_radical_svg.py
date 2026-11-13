#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""生成首页检索卡片与部首检字页用的西夏文部首 SVG 路径。

输出（均由本脚本生成，勿手改；字体或数据版本变更后重新运行）：
  includes/radical_svg.php  —— 首页：Unicode 体系按字数降序前 N 个字形 + 四套体系统计
  includes/radical_all.php  —— 部首检字页：四套体系全部可提取部首的字形映射（键=码位）

字形路径从 tangut_fix / tangut_rest 字体提取（UPM 均为 1000），共享 viewBox
（同一坐标空间，保持字体比例一致），y 已翻转为 SVG y-down。
"""
import json
import os
import sys
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FIX_FONT = os.path.join(ROOT, 'fonts', 'tangut_fix-bc6408ee5bc4.woff2')
REST_FONT = os.path.join(ROOT, 'fonts', 'tangut_rest-cba4d927d1e4.woff2')
RADICAL_IDX = os.path.join(ROOT, 'index_shards', 'index_radical.json')
OUT_HOME = os.path.join(ROOT, 'includes', 'radical_svg.php')
OUT_ALL = os.path.join(ROOT, 'includes', 'radical_all.php')
COUNT = int(sys.argv[1]) if len(sys.argv) > 1 else 10
PAD = 20  # viewBox 边距（字体单位）
UPM = 1000
FLIP = (1, 0, 0, -1, 0, UPM)  # y 翻转进 SVG y-down

idx = json.load(open(RADICAL_IDX, encoding='utf-8'))
uni = idx['systems']['unicode']['radicals']

# 字体：优先 tangut_fix（覆盖西夏文部首/部件），回退 tangut_rest
FONTS = [(TTFont(FIX_FONT), 'fix'), (TTFont(REST_FONT), 'rest')]
FONT_SETS = [(ft.getGlyphSet(), ft.getBestCmap(), label) for ft, label in FONTS]


def extract(cp: int):
    """返回 (path_d, bounds) 或 (None, None)。"""
    for gs, cmap, _ in FONT_SETS:
        gname = cmap.get(cp)
        if gname is None:
            continue
        bp = BoundsPen(gs)
        gs[gname].draw(TransformPen(bp, FLIP))
        if bp.bounds is None:
            continue
        sp = SVGPathPen(gs)
        gs[gname].draw(TransformPen(sp, FLIP))
        return sp.getCommands(), bp.bounds
    return None, None


# ── 首页：Unicode 体系按字数降序前 COUNT 个 ──────────────────
picked = sorted(uni.items(), key=lambda kv: -int(kv[1].get('count', 0)))[:COUNT]
home = []
minx = miny = 10 ** 9
maxx = maxy = -10 ** 9
for cp, r in picked:
    d, b = extract(ord(r['char']))
    if d is None:
        continue
    home.append({'cp': cp, 'char': r['char'], 'count': int(r.get('count', 0)), 'd': d})
    x0, y0, x1, y1 = b
    minx, miny = min(minx, x0), min(miny, y0)
    maxx, maxy = max(maxx, x1), max(maxy, y1)

if not home:
    print('no home glyphs', file=sys.stderr)
    sys.exit(1)

vx, vy = minx - PAD, miny - PAD
vw, vh = (maxx - minx) + 2 * PAD, (maxy - miny) + 2 * PAD

lines = [
    '<?php', 'declare(strict_types=1);', '',
    '/**',
    ' * 部首 SVG 字形 + 四套部首体系汇总（首页检索卡片用）。',
    ' * 字形路径从 tangut_fix 字体提取，由 _qa/gen_radical_svg.py 生成，勿手改；',
    ' * 字体、数据版本或所选部首变更后重新生成。',
    ' * 所有字形共用 viewBox（同一坐标空间，保持字体比例），y 已翻转为 SVG 坐标。',
    ' */',
    'return [',
    "    'viewbox' => [%d, %d, %d, %d]," % (vx, vy, vw, vh),
    "    'systems' => [",
]
for sk, sv in idx['systems'].items():
    lines.append("        '%s' => ['label' => '%s', 'radical_count' => %d, 'total_chars' => %d],"
                 % (sk, sv['label'].replace("'", "\\'"), int(sv['radical_count']), int(sv['total_chars'])))
lines += ['    ],', "    'glyphs' => ["]
for g in home:
    lines.append("        ['cp' => '%s', 'char' => '%s', 'count' => %d, 'd' => '%s'],"
                 % (g['cp'], g['char'], g['count'], g['d'].replace("'", "\\'")))
lines += ['    ],', '];', '']
open(OUT_HOME, 'w', encoding='utf-8', newline='\n').write('\n'.join(lines))

# ── 部首页：四套体系全部可提取部首 ──────────────────────────
all_radicals = {}
for k, v in idx['systems'].items():
    for cp, r in v['radicals'].items():
        all_radicals.setdefault(cp, r['char'])

allg = {}
aminx = aminy = 10 ** 9
amaxx = amaxy = -10 ** 9
for cp, ch in all_radicals.items():
    d, b = extract(ord(ch))
    if d is None:
        continue
    allg[cp] = {'char': ch, 'd': d}
    x0, y0, x1, y1 = b
    aminx, aminy = min(aminx, x0), min(aminy, y0)
    amaxx, amaxy = max(amaxx, x1), max(amaxy, y1)

avx, avy = aminx - PAD, aminy - PAD
avw, avh = (amaxx - aminx) + 2 * PAD, (amaxy - aminy) + 2 * PAD

lines = [
    '<?php', 'declare(strict_types=1);', '',
    '/**',
    ' * 全部部首 SVG 字形（部首检字页字形复用；从 tangut_fix/rest 提取）。',
    ' * 由 _qa/gen_radical_svg.py 生成，勿手改；键为码位（如 "U+18800"）。',
    ' * 缺失码位（PUA 占位）不在映射内，调用方回退字体渲染。',
    ' */',
    'return [',
    "    'viewbox' => [%d, %d, %d, %d]," % (avx, avy, avw, avh),
    "    'glyphs' => [",
]
for cp in sorted(allg):
    g = allg[cp]
    lines.append("        '%s' => ['char' => '%s', 'd' => '%s'],"
                 % (cp, g['char'], g['d'].replace("'", "\\'")))
lines += ['    ],', '];', '']
open(OUT_ALL, 'w', encoding='utf-8', newline='\n').write('\n'.join(lines))

print('home=%d viewbox=[%d,%d,%d,%d] all=%d all_viewbox=[%d,%d,%d,%d]'
      % (len(home), vx, vy, vw, vh, len(allg), avx, avy, avw, avh))
print('wrote', OUT_HOME)
print('wrote', OUT_ALL)
