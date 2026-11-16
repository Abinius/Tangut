# 起名构建器 · 竖排滑轮候选 开发文档

> **状态：已实现并验证（2026-11-16）**。`render_name_builder()`（layout.php）与 `.nb-*`（style.css）已按本文改造；`php -S` + 无头 Chrome（CDP）实测：滚动→居中自动选中、点击选中并居中、首/末卡均可居中、ARIA 一致、无控制台错误，截图确认视觉无误。旧类名无活代码残留。
>
> **修订（同日，需求变更）**：滑轮由**单列**改为**响应式多列网格**——PC 一排 4 个、平板（≤900px）3 个、手机（≤600px）2 个，仍纵向滚动 + 居中行选中。标记不变（仍是 `.nb-card` 全尺寸卡片），仅 CSS（`.nb-wheel` 改 `display:grid; grid-template-columns:repeat(N,minmax(0,1fr))`，卡宽随格自适应）与 JS（`centerSelect` 改为**按行取中**：滚动时选中垂直居中的行，行内保留用户点击的卡，避免横向被抢；`stepCard` 按列数同列跨行步进）变化。网格版验证：1100px→4 列、800px→3 列、500px→2 列；滚动落居中行、行内点击不被抢、ArrowDown 同列跨行、末行居中偏移 0。
>
> 注：正文 §3 及以下代码块描述的是**单列**初版，网格版以本节修订与代码为准。
>
> **修订（同日，窗口高度）**：滑轮窗口由 `2.6×卡高` 调为 **`1.3×卡高`**（居中行全显 + 上下各露约 0.15 行），`padding-block`/`scroll-padding-block` 同步调为 `0.15×卡高`。验证：`windowH 221 / cardH 170 = 1.3`，首行、末行居中偏移均 0，滚动选中等同。

> 需求：把「起名 · 娱乐参考」每个字的候选字改成**竖排、上下自由滑动**的滑轮形式。
> 每个候选字渲染成与当前选中字体卡片同高的全尺寸卡片；PC 鼠标滚轮上下滑动；超出部分隐藏；**砍掉左右切换箭头**。
>
> 涉及文件：
> - `includes/layout.php` → `render_name_builder()`（标记 + 内联 JS），行 229 起
> - `assets/style.css` → `.nb-*` 块，行 1038–1058

---

## 1. 可行性结论

**可行，且是纯前端改动，零后端 / 零海报生成改动。**

- 候选字数据载体是每张卡片上的 `data-nb-val` JSON（`tangut / phon / gloss / tier / lfw / homophone`）。改成竖排卡片**不改这个载荷**，因此「生成海报」的 `TangutPoster.open({ mode:'combo', items, dv })` 逻辑完全不受影响。
- 改动范围收敛在两处：`render_name_builder()` 的标记/内联 JS，和 `style.css` 的 `.nb-*` 块。`nb-preview`（大预览卡）与候选小条合并为**一个竖排滑轮**——每个候选本身就是全尺寸卡片，居中的那张就是「当前选中」，不再需要独立的预览区。
- 现有交互骨架（滚动 → 防抖 → 取最近居中的卡片为选中）可原样复用，只把横向坐标换成纵向。

---

## 2. 现状

每个字一组 `.nb-group`（`layout.php:272-289`）：

```
[nb-char] 汉字
[nb-preview]  大卡：nb-glyph-lg + nb-phon-lg + nb-borrow + nb-gloss-lg   ← 独立预览区
[nb-thumbs]   横排小卡 .nb-thumb（44px）scroll-snap-type:x proximity     ← 候选小条
[nb-nav.prev] ‹   [nb-nav.next] ›                                         ← 左右箭头（scrollBy 横条）
```

JS：`updatePreview()` 刷预览卡；`selectIn()` 打选中；`centerSelect(track)` 用 `left/width` 求最近居中；滚动防抖 50ms 触发；点击 `.nb-thumb` 选中、`.nb-nav` 横滚、`#nbGen` 汇总 `.nb-thumb.selected`。

CSS：`.nb-preview`（白底大卡）、`.nb-thumbs`（横条 snap）、`.nb-thumb`（44px 小牌）、`.nb-nav`（左右圆钮）等，`style.css:1043-1057`。

---

## 3. 目标设计

每组改为一个**竖排滑轮**，候选卡 = 全尺寸卡片（与今天预览卡同高），滑轮窗口只显示约 2.6 张卡（中间一张完整，上下两张露头并渐隐），`overflow-y: auto`，鼠标滚轮上下滑，**居中那张即选中**。

```
[nb-char] 汉字
[nb-wheel]  height = 2.6 × 卡高，overflow-y:auto，snap y proximity，上/下渐隐遮罩
   [nb-card.selected]  𗘍 / ŋwər¹ / 借「…」 / 青     ← 居中、accent 描边
   [nb-card]           𗘎 / ŋwər² / …               ← 略淡、略缩
   [nb-card]           𗘏 / …                       ← 露头渐隐
```

- 卡片高固定为 `--nb-card-h`（≈ 170px 桌面 / 150px 移动），保证 snap 居中整齐。
- 首/末卡也能滚到正中：滑轮上下各留 `padding-block = (2.6−1)/2 × 卡高 = 0.8 × 卡高`。
- 箭头 `.nb-nav` 与独立预览 `.nb-preview`、小条 `.nb-thumbs` 全部删除。
- 卡片本身即 `<button role="radio">`，可点击、可聚焦，键盘可达。

### 3.1 标记（layout.php 内 `render_name_builder`）

把 `nb-preview` + `nb-thumbs` + 两个 `nb-nav` 替换为单个 `.nb-wheel`，候选卡复用原预览卡内容结构：

```html
<div class="nb-group" data-nb-group="<?= $gi ?>">
  <div class="nb-char"><?= h($g['char']) ?></div>
  <div class="nb-wheel" role="radiogroup" aria-label="字「<?= h($g['char']) ?>」的候选">
    <?php foreach ($g['opts'] as $oi => $o): ?>
    <button type="button" class="nb-card<?= $oi === 0 ? ' selected' : '' ?>"
            role="radio" aria-checked="<?= $oi === 0 ? 'true' : 'false' ?>"
            data-nb-val='<?= h(json_encode($o, JSON_UNESCAPED_UNICODE)) ?>'>
      <span class="nb-glyph-lg" lang="txg"><?= h($o['tangut']) ?></span>
      <span class="nb-phon-lg"><?= h($o['phon'] !== '' ? $o['phon'] : '—') ?></span>
      <?php if ($o['homophone'] !== ''): ?><span class="nb-borrow">借「<?= h($o['homophone']) ?>」</span><?php endif; ?>
      <?php if ($o['gloss'] !== ''): ?><span class="nb-gloss-lg"><?= h($o['gloss']) ?></span><?php endif; ?>
    </button>
    <?php endforeach; ?>
  </div>
</div>
```

> `data-nb-val` 载荷、`#nbGen` 汇总逻辑不变。

### 3.2 CSS（style.css，替换 1038–1058 整块）

```css
/* ── 起名构建器：竖排滑轮候选（每候选 = 全尺寸卡片，滚轮上下滑，居中选中） ── */
.nb { display: flex; flex-direction: column; gap: 1.1rem; padding: 1.4rem;
  background: var(--glass-bg); -webkit-backdrop-filter: var(--glass-blur); backdrop-filter: var(--glass-blur);
  border: 1px solid var(--glass-border); border-radius: 14px; box-shadow: var(--glass-shadow); }
.nb-group { position: relative; display: flex; flex-direction: column; gap: 0.6rem; align-items: center; width: 100%; }
.nb-char { font-family: var(--mono); font-size: 12px; letter-spacing: 0.1em; color: var(--accent-deep); font-weight: 500; }
.nb-wheel {
  --nb-card-h: 170px;
  width: 184px; height: calc(var(--nb-card-h) * 2.6);
  overflow-y: auto; overscroll-behavior: contain;
  scroll-snap-type: y proximity;
  scroll-padding-block: calc(var(--nb-card-h) * 0.8);
  padding-block: calc(var(--nb-card-h) * 0.8);
  display: flex; flex-direction: column; gap: 0.6rem; align-items: center;
  scrollbar-width: none; -webkit-overflow-scrolling: touch;
  -webkit-mask-image: linear-gradient(to bottom, transparent, #000 16%, #000 84%, transparent);
  mask-image: linear-gradient(to bottom, transparent, #000 16%, #000 84%, transparent);
}
.nb-wheel::-webkit-scrollbar { display: none; }
.nb-card {
  flex: 0 0 var(--nb-card-h); height: var(--nb-card-h); width: 160px;
  scroll-snap-align: center;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.4rem;
  padding: 1rem; background: #FFFFFF; border: 1px solid var(--hairline); border-radius: 14px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.08), inset 0 1px 0 rgba(255,255,255,0.9);
  cursor: pointer; color: var(--ink); text-align: center;
  transition: border-color var(--fast) var(--ease), box-shadow var(--fast) var(--ease),
              opacity var(--fast) var(--ease), transform var(--fast) var(--ease);
}
.nb-card:hover { border-color: rgba(119, 52, 219, 0.45); }
.nb-card.selected { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(119, 52, 219, 0.18), 0 2px 6px rgba(119,52,219,0.1); }
.nb-card:not(.selected) { opacity: 0.78; transform: scale(0.97); }
.nb-glyph-lg { font-family: var(--tangut); font-size: 3.4rem; line-height: 1; }
.nb-phon-lg { font-family: var(--mono); font-size: 11px; color: var(--ink-mute); }
.nb-borrow { font-family: var(--mono); font-size: 10px; color: var(--accent-deep);
  border: 1px solid var(--hairline-acid); border-radius: 9999px; padding: 0.1rem 0.45rem; white-space: nowrap; }
.nb-gloss-lg { font-size: 0.75rem; color: var(--ink-mute); line-height: 1.4; text-align: center; max-width: 140px; }
@media (max-width: 600px) {
  .nb-wheel { --nb-card-h: 150px; width: 164px; }
  .nb-card { width: 140px; }
  .nb-glyph-lg { font-size: 2.8rem; }
}
```

删除的旧规则：`.nb-preview`、`.nb-thumbs`、`.nb-thumb`、`.nb-thumb.selected`、`.nb-glyph-sm`、`.nb-nav`、`.nb-nav.prev/.next`（含 1058 媒体查询里的 `.nb-thumbs/.nb-glyph-sm/.nb-nav` 条目）。

### 3.3 JS（layout.php 内联脚本，改 `render_name_builder` 的 `<script>` 部分）

```js
function selectIn(grp, card) {
  var cs = grp.querySelectorAll('.nb-card');
  for (var i = 0; i < cs.length; i++) {
    cs[i].classList.remove('selected');
    cs[i].setAttribute('aria-checked', 'false');
  }
  card.classList.add('selected');
  card.setAttribute('aria-checked', 'true');
}
function centerSelect(wheel) {                    // 纵向版：取垂直中心最近者
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
function stepCard(wheel, dir) {                   // 键盘 / 按钮步进：上/下一张并居中
  var cs = Array.prototype.slice.call(wheel.querySelectorAll('.nb-card'));
  if (!cs.length) return;
  var sel = wheel.querySelector('.nb-card.selected');
  var i = sel ? cs.indexOf(sel) : 0, n = i + dir;
  if (n < 0 || n >= cs.length) return;
  cs[n].scrollIntoView({ block: 'center', behavior: 'smooth' });
  selectIn(wheel.closest('.nb-group'), cs[n]);
}
```

事件接线（替换原 click / scroll 两段）：

```js
sec.addEventListener('click', function (e) {
  var card = e.target.closest('.nb-card');
  if (card) {
    var grp = card.closest('.nb-group');
    selectIn(grp, card);
    card.scrollIntoView({ block: 'center', behavior: 'smooth' });
    return;
  }
  if (e.target.closest('#nbGen')) {               // 汇总改查 .nb-card.selected
    var items = [], groups = sec.querySelectorAll('.nb-group');
    for (var g = 0; g < groups.length; g++) {
      var sel = groups[g].querySelector('.nb-card.selected');
      if (sel) items.push(JSON.parse(sel.dataset.nbVal));
    }
    if (items.length) openPoster({ mode: 'combo', items: items, dv: <?= json_encode($dv) ?> });
  }
});
var wheels = sec.querySelectorAll('.nb-wheel');
for (var i = 0; i < wheels.length; i++) (function (w) {
  var tm;
  w.addEventListener('scroll', function () {
    clearTimeout(tm); tm = setTimeout(function () { centerSelect(w); }, 50);
  }, { passive: true });
  w.addEventListener('keydown', function (e) {    // 上/下键步进
    if (e.key === 'ArrowDown') { e.preventDefault(); stepCard(w, 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); stepCard(w, -1); }
  });
})(wheels[i]);
```

删除：`updatePreview()`、`.nb-nav` 分支、`centerSelect` 的 `left/width` 写法（已换成 `top/height`）。

---

## 4. 行为与边界

- **滚轮**：滑轮自身 `overflow-y:auto`，鼠标悬停其上滚轮即滑滑轮；`overscroll-behavior: contain` 让滑轮到底后**不立刻带动页面**，需移开鼠标再滚页面（避免「滑到底突然翻页」的抢手感）。
- **自由滑动 vs 对齐**：`scroll-snap-type: y proximity` 兼顾「自由滑动」和停稳后居中；若想更「滑轮」可改 `y mandatory`，更生硬。
- **单候选组**（只有 1 张卡）：无需滚动，默认居中即选中；`padding-block: 0.8×卡高` 保证它能居中对齐。
- **首/末卡**：靠 `padding-block` 与 `scroll-padding-block` 对齐，首末皆可滚到正中。
- **点击卡**：选中 + `scrollIntoView(block:'center')` 平滑居中。
- **键盘**：卡是 `<button>`，Tab 聚焦；轮内 `↑/↓` 步进（`stepCard`），Enter/Space 原生触发点击。
- **移动端**：竖向滑轮天然支持触控滑动；卡高降为 150px。
- **海报**：`#nbGen` 只读 `.nb-card.selected` 的 `data-nb-val`，与旧 `.nb-thumb.selected` 载荷一致，生成逻辑零改动。

---

## 5. 风险与调优点

| 风险 | 应对 |
|---|---|
| 嵌套纵向滚动（页面 + 滑轮）抢手 | 滑轮窗口压缩到 2.6 卡高，`overscroll-behavior: contain`；若嫌「粘」可改回 `auto`（滑轮到底即连动页面） |
| 固定卡高 vs 内容溢出（长释义 / 借字徽标） | 释义已截 22 字、`max-width:140px`；卡设 `overflow:hidden` 保底；若仍溢出可降 `--nb-card-h` 或改用 `min-height`+`proximity`（放弃绝对等高） |
| snap 与 JS 平滑滚动打架 | 用 `scrollIntoView({behavior:'smooth'})`，不手算 `scrollTop`；防抖 50ms 沿用 |
| 渐变遮罩兼容 | 同时给 `mask-image` 与 `-webkit-mask-image`；仅作视觉增强，`overflow:hidden` 已满足「超出隐藏」 |
| 滑轮「只出不进」页面难滚 | 每组高度有限（≈2.6 卡），页面主体滚动不受影响；必要时把滑轮 `height` 调小 |

---

## 6. 验收清单

- [ ] 每个字一个竖排滑轮，候选为全尺寸卡片，上下自由滑动，超出隐藏，无左右箭头。
- [ ] 滚动停稳后居中卡自动选中（accent 描边 + 略淡邻卡），预览即选中所见即所得。
- [ ] 点击任意卡 → 居中并选中；鼠标滚轮在滑轮上滑动、到底不带动页面。
- [ ] 单候选、首卡、末卡都能滚到正中；键盘 `↑/↓` 步进、Tab 聚焦正常。
- [ ] 「生成海报」产物与改动前一致（载荷、布局、借字标注）。
- [ ] 600px 以下移动布局正常；`.nb-*` 无残留死代码（旧 `.nb-thumbs/.nb-thumb/.nb-nav` 已删）。
- [ ] 无回归：其余页、`index.php` 其它区块、海报分享不受影响。
