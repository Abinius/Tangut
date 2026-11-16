# 起名构建器 · 候选列布局（青狐互动式）开发文档

> 需求（2026-11-16）：候选框改为**一字一列**——每个字一列竖排候选字，各字横向排成行；PC 一排 **4** 字、平板 **3** 字、手机 **2** 字。示例：「青狐互动」→ 青/狐/互/动 各一列候选。
> 状态：**已实现并验证（2026-11-16）**。`style.css`（`.nb` 字列网格 + `.nb-wheel` 单列 + `.nb-card`）与 `layout.php` 内联 JS（`centerSelect`/`stepCard` 还原单列）已按本文改造。`php -S` + 无头 Chrome/CDP 实测：1200/800/500px → 每行 **4/3/2 字**；每列单列竖排、卡宽随列（221/218/198）；滚动→居中选中、点击居中偏移 0、`↑/↓` 逐张、列间独立；末卡居中 0。截图确认视觉。
>
> **实现中发现并修复的两个问题**：
> 1. `.nb` 网格里 `.seclabel`（区头）与 `.row-actions`（生成按钮）也是 grid item，占掉首格把第 4 字挤到次行 → 加 `.nb > .seclabel, .nb > .row-actions { grid-column: 1 / -1; }` 跨满整行。
> 2. `.nb-card` 变回 flex 项后默认 `flex-shrink:1` 把卡片压到内容最小高（~128px，非 170），使居中 padding 公式按 170 算导致末卡偏 21px → 加 `flex: 0 0 var(--nb-card-h)` 防压缩。
>
> **连带修复（海报回归）**：上一轮海报 UX 在 `.poster-dlg` 基础规则加了无条件 `display:flex`，使**关闭态弹窗也显示**（黑底"生成中…"常驻页面）；已移除（仅 `.poster-dlg[open]` 保留 `display:flex`），关闭态恢复 `display:none`。

---

## §1 需求（需求文档）

### 1.1 目标
- 起名构建器把「**每个字**」作为一个**纵向候选列**：列内候选字上下排列、可上下滚动（滑轮），居中即选中。
- 各字的列**横向排列**成网格：PC 每行 4 列（4 个字）、平板 3 列、手机 2 列；字多于行容量则换行。
- 每个字独立滚动/独立选中；「生成海报」按各列选中项汇总（载荷不变）。

### 1.2 示例（青狐互动）
```
PC（一排 4 列）：
[ 青 ] [ 狐 ] [ 互 ] [ 动 ]      ← 每列顶部是字头
 [候选1] [候选1] [候选1] [候选1]
 [候选2] [候选2] [候选2] [候选2]
 [候选3] [候选3] …      …      ← 各列纵向滚动、互不影响

平板（一排 3 列）：青狐互 / 动换行到第二行；手机（一排 2 列）：青狐 / 互动。
```

### 1.3 现状对比
| 维度 | 当前 | 目标 |
|---|---|---|
| 字与字关系 | 竖向堆叠（`.nb` flex column） | **横向网格**（`.nb` grid 4/3/2 列） |
| 每字候选 | 多列网格（4/3/2 张候选卡） | **单列竖排**（一列候选） |
| 断点作用对象 | `.nb-wheel`（候选卡列数） | `.nb`（**字数/行**） |

### 1.4 关键决策点（需确认）
1. **列内可见候选数**：单列竖排建议显示 **~2.6 行**（居中卡 + 上下各露约 0.8 行，方便浏览）；上一轮你选的「1.3 行」也可沿用（更紧凑，但单列每次只看到约 1 张卡，浏览候选需多滚）。**默认建议 2.6，可调。**
2. **候选卡尺寸**：卡宽随列宽（PC 列 ~225px、手机列 ~160px），高保持 `--nb-card-h`（PC 170 / 手机 150）。
3. **选中语义**：列内滚动 → 垂直居中卡自动选中；点击任意卡 → 居中并选中；`↑/↓` 逐张步进。

### 1.5 边界
- 字数 > 行容量：换行（如 5 字 PC → 4+1；平板 3+2；手机 2+2+1）。
- 单字查询：仅一列（1.3 需求里单字不出 nameBuilder，此布局针对 ≥2 字多字查询）。
- 候选极多（如「明」36 候选）：单列长列表，列内滚动，高度固定不撑高页面。
- 移动端：列变窄，字形/字号收小；两列仍可独立滚动。

---

## §2 开发设计（开发文档）

### 2.1 结构（不改标记，仅 CSS/JS 取向）
```
.nb                    ← 改为 grid：repeat(4,1fr) / 3 / 2（字列网格）
  .nb-group（每字一列）  ← 改为 stretch，去掉 position:relative
    .nb-char（字头）
    .nb-wheel           ← 改回单列 flex column（纵向滑轮）
      .nb-card ×N       ← 卡宽=列宽，高=--nb-card-h
```

标记 `includes/layout.php` `render_name_builder()` 的 `.nb-group` 结构**不变**（仍是 `.nb-char` + `.nb-wheel` + `.nb-card`）；仅容器 `.nb` 与 `.nb-wheel` 的 CSS 取向改变。

### 2.2 CSS（`assets/style.css` 1039-1055）
```css
/* .nb：字列网格，PC 4 列 */
.nb { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.1rem;
  padding: 1.4rem; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 14px; }
.nb-group { display: flex; flex-direction: column; gap: 0.6rem; align-items: stretch; width: 100%; }
/* .nb-wheel：单列纵向滑轮 */
.nb-wheel { --nb-card-h: 170px; width: 100%; height: calc(var(--nb-card-h) * 2.6);
  overflow-y: auto; overscroll-behavior: contain; scroll-snap-type: y proximity;
  scroll-padding-block: calc(var(--nb-card-h) * 0.8); padding-block: calc(var(--nb-card-h) * 0.8);
  display: flex; flex-direction: column; gap: 0.6rem;
  scrollbar-width: none; -webkit-overflow-scrolling: touch;
  -webkit-mask-image: linear-gradient(to bottom, transparent, #000 16%, #000 84%, transparent);
  mask-image: linear-gradient(to bottom, transparent, #000 16%, #000 84%, transparent); }
/* .nb-card：卡宽=列宽 */
.nb-card { height: var(--nb-card-h); min-width: 0; scroll-snap-align: center;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.4rem;
  padding: 0.9rem 0.6rem; background: #FFFFFF; border: 1px solid var(--hairline); border-radius: 14px;
  cursor: pointer; color: var(--ink); text-align: center;
  transition: border-color var(--fast) var(--ease), box-shadow var(--fast) var(--ease),
              opacity var(--fast) var(--ease), transform var(--fast) var(--ease); }
/* 断点：平板 3 字 / 手机 2 字 */
@media (max-width: 900px) { .nb { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 600px) { .nb { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .nb-wheel { --nb-card-h: 150px; } .nb-card { padding: 0.7rem 0.4rem; } .nb-glyph-lg { font-size: 2.6rem; } }
```
> 删除旧断点里的 `.nb-wheel { grid-template-columns: repeat(3/2,…) }`（候选卡列数已不再用）；`.nb-group` 去掉 `position: relative`。

### 2.3 JS（`includes/layout.php` 内联）
`centerSelect` 还原**单列最近垂直居中**（网格版按行逻辑在单列下等价，但更简）：
```js
function centerSelect(wheel) {
  var cards = wheel.querySelectorAll('.nb-card');
  if (!cards.length) return;
  var r = wheel.getBoundingClientRect(), c = r.top + r.height / 2, best = cards[0], bd = 1e9;
  for (var i = 0; i < cards.length; i++) {
    var o = cards[i].getBoundingClientRect(), d = Math.abs((o.top + o.height / 2) - c);
    if (d < bd) { bd = d; best = cards[i]; }
  }
  var grp = wheel.closest('.nb-group');
  if (grp) selectIn(grp, best);
}
function stepCard(wheel, dir) {   // 单列：±1 张
  var cs = Array.prototype.slice.call(wheel.querySelectorAll('.nb-card'));
  if (!cs.length) return;
  var sel = wheel.querySelector('.nb-card.selected');
  var i = sel ? cs.indexOf(sel) : 0, n = i + dir;
  if (n < 0 || n >= cs.length) return;
  cs[n].scrollIntoView({ block: 'center', behavior: 'smooth' });
  selectIn(wheel.closest('.nb-group'), cs[n]);
}
```
> `selectIn`、点击卡 `scrollIntoView(center)`、`#nbGen` 汇总 `.nb-card.selected`、`openPoster` 均不变。

### 2.4 边界与风险
| 项 | 处理 |
|---|---|
| 字超行容量 | `.nb` 网格自动换行（grid 天然换行） |
| 单列候选多 | 列内滚动，高固定 `2.6×卡高`，不撑高页面 |
| 列宽变窄卡挤压 | `min-width:0` + 释义截断；移动端降字号/内边距 |
| 各列独立滚动 | 每列自有 `.nb-wheel`（overflow-y:auto + overscroll contain） |
| 选中/海报回归 | 载荷与 `.selected` 读取不变；`selectIn/centerSelect` 单列版与网格版在单列下行为一致 |
| `1.3` vs `2.6` | 见 §1.4 决策点 1；改 `.nb-wheel` 高度乘子与 `padding-block`（=(N−1)/2）即可 |

### 2.5 验收清单
- [ ] 多字查询：每字一列竖排候选；PC 一排 4 字 / 平板 3 / 手机 2；超行容量自动换行。
- [ ] 每列独立滚动、独立选中；滚动停稳居中和自动选中；点击卡居中选中；`↑/↓` 逐张步进。
- [ ] 单候选列、候选多的列、首末卡均能居中；各列互不影响。
- [ ] 卡宽随列宽自适应（PC ~225 / 手机 ~160），字形/释义不溢出。
- [ ] 「生成海报」汇总与改动前一致（载荷、借字标注）；无控制台错误。
- [ ] 无残留死代码（旧 `.nb-wheel` 网格断点、`.nb-group` 的 `position:relative` 已清）。

---

## §3 开发计划（开发计划）

**范围**：纯前端，`assets/style.css`（`.nb` 取向 + 断点 + `.nb-wheel` 单列）与 `includes/layout.php` 内联 JS（`centerSelect`/`stepCard` 还原单列）；标记与 `data-nb-val` 载荷、`share.js` 海报零改动。

**步骤**
1. **CSS**：`.nb` → grid `repeat(4,1fr)` + 断点 3/2；`.nb-wheel` → flex 单列（去 `grid-template-columns`、去 `width:min(100%,820px)`）；`.nb-card` 卡宽=列宽；删除旧 `.nb-wheel` 网格断点；`.nb-group` 去 `position:relative`。
2. **JS**：`centerSelect` 还原单列最近垂直居中；`stepCard` 还原 `±1`；`selectIn`/点击/`#nbGen`/`openPoster` 不动。
3. **本地验证**：`php -S`（router.php）+ 无头 Chrome/CDP——PC/平板/手机三宽度列数 4/3/2；`?q=青狐互动` 渲染 4 列；每列滚动→居中选中、点击居中、↑/↓ ±1；单列候选多/首末居中；截图确认视觉；无控制台错误。
4. **收尾**：`node --check`/`php -l`；清临时文件/服务器；本文档与实现一致；无死代码。
