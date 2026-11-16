# 搜索结果网格化（3/3/2）开发文档

> 需求（2026-11-16）：多个搜索结果按网格排列——**PC 一排 3 个、平板一排 3 个、手机一排 2 个**。参考 `/word/?q=bin`（已是 3 列网格）。复用到所有类似结果列表页，含 `x.php`。
> 状态：**已实现并验证（2026-11-16）**。`style.css` 两处改动：`.index` 从 `flex column` 改为 `grid repeat(3,1fr)`（手机 ≤620px `repeat(2,1fr)`）+ 非 `.row` 子项 `grid-column:1/-1`；`.word-list` 平板断点 2→3。`php -S` + 无头 Chrome/CDP 实测：`?q=blue`/`?q=明`/`x.php`/`word.php` PC 与平板均 3 列、手机 2 列；`.status` 等非结果子项跨满行；起名构建器、海报无回归；无控制台异常。

---

## §1 需求

### 1.1 目标
把搜索结果列表从**单列**改为**响应式网格**：PC 3 列 / 平板 3 列 / 手机 2 列。参考 `/word/?q=bin` 的 `.word-list` 网格视觉（磨砂玻璃卡，字形在上、注音/释义在下）。

### 1.2 受影响页面（共用 `.index` 容器）
| 页面 | 容器 | 当前 | 结果项 |
|---|---|---|---|
| 首页 `?q=blue`（英文内联） | `.index` | 单列 | 14 `.row` + `.status` |
| 首页 `?q=明`（汉字逐字） | `.index` | 单列 | 多 `.row` + `.status`/`.empty` |
| `x.php`（字头候选） | `.index` | 单列 | 多 `.row`（`render_candidate`） |
| `tongyin.php` | `.index` | 单列 | `.row` 列表 |
| `includes/indexes.php` | `.index` | 单列 | 字头列表 |
| `word.php` | `.index.word-list.wordpage` | **已 3/2/2 网格** | `.row`（参考样板） |

> `word.php` 已是网格，仅把**平板断点 2→3** 对齐本次需求。

### 1.3 非目标
- `.overview-grid`（radical/stroke 总览小卡）、`.overview-grid-3`（rhyme/wenhai 总览）内容形态不同（迷你卡 vs 结果行），本次不动；若后续要对齐列数另开任务。
- 结果项 `.row` 内部结构不改（仍字形格 + meta + gloss + 分享按钮）。
- 海报、起名构建器、海报弹窗不动。

### 1.4 关键约束
- `.index` 容器里混有**非 `.row` 子项**（`.status`/`.empty`/`.seclabel`/`.combo-cta`/`.row-actions`），网格化时这些必须**跨满整行**（`grid-column: 1/-1`），否则会被塞进单个网格格扭曲布局——这与起名构建器 `.nb` 网格化时遇到的问题同源。
- `.row` 本身是 `flex-direction: column; align-items: center`，放进网格格天然适配，无需改。

---

## §2 开发设计

### 2.1 CSS（`assets/style.css`）
**`.index` 改为网格**（替换第 240 行）：
```css
.index { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.9rem; }
.index:empty { display: none; }
/* 非 .row 子项跨满整行（状态/空提示/区头/CTA 不进网格格） */
.index > .status, .index > .empty, .index > .seclabel, .index > .combo-cta, .index > .row-actions, .index > .backlinks { grid-column: 1 / -1; }
```
**断点**：
```css
@media (max-width: 600px) { .index { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.7rem; } }
```
> 平板（601–1023px）保持 3 列，与 PC 同——满足「平板一排 3 个」。

**`.word-list` 对齐断点**（第 850 行平板 2→3）：
```css
@media (max-width: 1023px) {
  .word-list { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.8rem; }  /* was repeat(2,...) */
}
```
> word.php 手机断点（≤620）保持 2 列，已符合需求。

### 2.2 标记
**不改**。`.index` 与 `.row` 结构在所有页面已统一（`render_candidate` 输出 `.row`，首页内联也输出 `.row`）。

### 2.3 JS
**不改**。无交互逻辑依赖 `.index` 的流式布局；海报绑定走 `data-poster`，与容器布局无关。

### 2.4 边界与风险
| 项 | 处理 |
|---|---|
| 非 `.row` 子项进网格格 | `grid-column: 1/-1` 跨满（status/empty/seclabel/cta/backlinks） |
| 单结果（1 个 `.row`） | 网格 3 列只占 1 格，左对齐——可接受；若需居中可 `justify-items: center`，但多结果时左对齐更自然，默认不加 |
| `.row` 高度不齐 | 网格行默认按最高项；`.row` 内容长短不一会留白——可接受（参考 `/word/?q=bin` 同样行为） |
| `.word-list` 与 `.index` 同在 word.php | `.word-list` 规则覆盖 `.index`（更具体），不冲突 |
| `indexes.php` 字头列表 | 同 `.index` 网格化，验证渲染 |
| 海报/起名构建器 | 不受影响（独立容器） |

---

## §3 开发计划

**范围**：纯 CSS，`assets/style.css` 两处（`.index` 网格化 + `.word-list` 平板 2→3）。标记/JS 零改动。

**步骤**
1. **`.index` 网格化**：`flex column` → `grid repeat(3,1fr)`；加 `.index > 非row子项 { grid-column: 1/-1 }`；手机断点 `repeat(2,1fr)`。
2. **`.word-list` 平板对齐**：`@media(max-width:1023px)` 下 `repeat(2,...)` → `repeat(3,...)`。
3. **本地验证**：`php -S`（router.php）+ 无头 Chrome/CDP——
   - `?q=blue`（14 结果）：PC/平板 3 列、手机 2 列；`.status` 跨满行；
   - `?q=明`：结果网格化、`.status`/`.empty` 跨满行；
   - `x.php`（`/x-明/`）：候选网格化 3/3/2；
   - `/word/?q=bin`：平板从 2 列变 3 列；
   - `tongyin.php`、`indexes.php`：渲染正常；
   - 截图对比 `/word/?q=bin` 视觉一致；无控制台错误。
4. **收尾**：无回归（海报/起名构建器/总览页不受影响）；`grep` 核对 `.index` 用法无遗漏；本文档标已实现。

## §4 验收清单
- [ ] `?q=blue`、`?q=明`、`x.php`、`tongyin.php`、`indexes.php` 结果列表 PC/平板 **3 列**、手机 **2 列**。
- [ ] `.status`/`.empty`/`.seclabel` 等非结果子项跨满整行，不扭曲网格。
- [ ] `/word/?q=bin` 平板变 3 列（与 PC 一致），手机仍 2 列。
- [ ] 单结果、多结果、结果数非 3 的倍数均布局正常（末行左对齐）。
- [ ] 海报分享、起名构建器、总览页（overview-grid）无回归。
- [ ] 无控制台错误；无 PHP 告警。
