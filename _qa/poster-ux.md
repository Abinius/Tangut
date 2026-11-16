# 海报弹窗 UX 优化 开发文档

> **状态：已实现并验证（2026-11-16）**。`share.js`（`setImg/open/close`）、`style.css`（`.poster-dlg/.poster-scroll/.poster-frame/.poster-frame img/.poster-loading/.poster-open`）、`layout.php`（`#posterLoading`）已按本文改造。`php -S` + 无头 Chrome/CDP 实测：帧 `aspect-ratio:2/3` + 黑底占位 + 「生成中…」默认可见、`img object-fit:contain` 加载后无缝贴合；视口高 700/900/1200 下帧高 540/690/690 均 ≤ 视口（宽随 2:3 收窄，矮屏封顶）；打开时 `html overflow:hidden` + dialog/scroll `overscroll-behavior:contain`（锁用户滚动、滚轮不连锁 body），海报 `position:fixed` 不随页面滚动（`getBoundingClientRect().top` 不变）；关闭后恢复滚动。截图确认视觉。`.poster-*` 无残留死类。
>
> **测试扫出并修复（同日）**：原生 `<dialog>` 在 ESC 时自行关闭，绕过 `close()` 的清理（`poster-open` 滚动锁残留、blob 未回收）→ `bind()` 加 `dlg.addEventListener('cancel', e => { e.preventDefault(); close(); })`，ESC 走完整关闭（关闭 + 解锁 + 清图均验证）。
>
> **修订（同日，容器与图片同比例）**：原 `.poster-frame` 用 `aspect-ratio:2/3` + `max-height` 封顶，但**高度封顶时宽度不跟着收** → 容器变非 2:3、图片 `object-fit:contain` 左右留白。改为 `height: min(calc(100vh - 10rem), calc(min(92vw, 460px) * 1.5))` + `aspect-ratio:2/3` + `width:auto`（高度封顶时宽度随 2:3 收窄）；`.poster-dlg` 改 `width: fit-content`（贴住海报，两侧无深色留边）。验证：560/700/900 视口下容器=图片=2:3（ratio 0.667）、`fills:true`、dlgW=frW（267/353/460）。

> 需求（2026-11-16）：
> 1. 海报生成前有加载阶段 → 图片未加载时设置默认填充色（**黑色**）。
> 2. 海报高度**屏幕自适应**（完整可见、不超高、无需滚动看全图）。
> 3. 海报出现时**上下滑动不受影响**——海报固定于视口，页面不随之滚动。
>
> 涉及文件：
> - `assets/share.js` → `TangutPoster`（`open/close/setImg`，加载态与滚动锁）
> - `assets/style.css` → `.poster-dlg/.poster-scroll/.poster-frame/.poster-frame img`（1057-1098）
> - `includes/layout.php` → `#posterDlg` 标记（111-121，加加载指示元素）

---

## 1. 现状

**标记**（layout.php 111-121）：
```
<dialog id="posterDlg" class="poster-dlg">
  <button class="ps-close" id="posterClose">×</button>
  <button class="ps-nav prev" id="posterPrev">＜</button>
  <button class="ps-nav next" id="posterNext">＞</button>
  <div class="poster-scroll"><div class="poster-frame">
    <img id="posterImg" alt="西夏文分享海报">
    <span class="poster-hint" id="posterHint">滑动切换 长按保存</span>
  </div></div>
</dialog>
```

**渲染**（share.js）：`open(d)` → `showModal()` → `render()`（`ensureFonts→loadBg→tryDraw→toURL`，异步）→ `setImg(u)` 才设 `img.src`。加载期 `img` 无 src。

**CSS**：
- `.poster-dlg`：`position: relative`（⚠️ 非 fixed）、`max-width:min(92vw,460px)`、`max-height:calc(100vh-6rem)`、圆角、`background:#0E1014`。
- `.poster-scroll`：`flex:1; overflow-y:auto`（矮屏内部滚动）。
- `.poster-frame`：`background:#0A0A0A; overflow:hidden`（本就是黑色，但无尺寸）。
- `.poster-frame img`：`display:block; width:100%; height:auto`（固定 2:3）。
- `.ps-close`：`bottom:-46px; left:50%`；`.ps-nav.prev/next`：`left/right:-46px`。

## 2. 问题与根因

| 问题 | 根因 |
|---|---|
| 加载期无填充、塌陷跳变 | `img` 无 src → `height:auto`=0 → `.poster-frame` 塌成细条，图加载后才撑开 |
| 高度不随屏 | 海报固定 2:3，`max-height` 只给 dialog；矮屏时超高需内部滚动/溢出 |
| 页面滚动带动海报 | `.poster-dlg` 是 `position:relative`，非 `fixed`；弹窗上滚轮事件连锁滚动 body |

## 3. 目标设计

### 3.1 加载态（R1）
- `.poster-frame` 加 `aspect-ratio: 2/3` → 加载期即为**黑色 2:3 海报形占位**（背景 `#0A0A0A`），布局稳定，图加载后无跳变。
- 帧内加加载指示 `.poster-loading`（黑底 + 居中「生成中…」/ 转圈），`img.onload/onerror` 时隐藏。
- `img` 改 `width:100%; height:100%; object-fit:contain`（海报 2:3 与帧同比例 → 精确贴合）。

标记（layout.php，`poster-frame` 内加一行）：
```html
<div class="poster-frame">
  <img id="posterImg" alt="西夏文分享海报">
  <span class="poster-loading" id="posterLoading" aria-hidden="true">生成中…</span>
  <span class="poster-hint" id="posterHint">滑动切换 长按保存</span>
</div>
```

### 3.2 高度屏幕自适应（R2）
- 帧尺寸「能同时塞进 `max-width` 与 `max-height` 的最大 2:3 框」（CSS `aspect-ratio` 传递尺寸），高度封顶 `calc(100vh - 10rem)`（给关闭钮 `-46px` 与上下留白）。
- dialog 由 `width:auto` 收缩为帧宽（`display:flex; flex-direction:column`），整体居中。

CSS 关键：
```css
.poster-dlg {
  position: fixed; inset: 0; margin: auto;          /* 视口固定 + 居中（改掉 position:relative） */
  width: min(92vw, 460px); max-width: min(92vw, 460px);
  max-height: calc(100vh - 6rem);
  overscroll-behavior: contain;                     /* 滚轮不连锁滚 body */
}
/* 注意：display:flex 只放 .poster-dlg[open]，勿放基础规则——否则关闭态弹窗也显示 */
.poster-dlg[open] { display: flex; flex-direction: column; }
.poster-frame {
  position: relative; aspect-ratio: 2 / 3;          /* 黑底 2:3 占位 */
  width: auto; height: auto; max-width: 100%; max-height: calc(100vh - 10rem);
  margin: 0 auto; background: #0A0A0A; border-radius: 16px; overflow: hidden;
}
.poster-frame img { display: block; width: 100%; height: 100%; object-fit: contain; }
.poster-loading {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  background: #0A0A0A; color: #8a8f98; font-family: var(--mono); font-size: 12px; letter-spacing: 0.2em;
}
.poster-loading.hidden { display: none; }
```
> `aspect-ratio` + `max-width`/`max-height` 双约束时，浏览器取「满足两约束的最大 2:3 尺寸」→ 高随屏封顶、宽随高按 2:3 收窄，海报完整可见。

### 3.3 上下滑动不受影响（R3）
- `.poster-dlg` 改 `position: fixed; inset: 0; margin: auto`（见上）→ 页面滚动不再带动海报。
- 打开时锁 body 滚动：`open()` 加 `document.documentElement.classList.add('poster-open')`，`close()` 移除；CSS `.poster-open { overflow: hidden; }`。
- `.poster-scroll` 保留 `overflow-y:auto` + `overscroll-behavior:contain` 作矮屏兜底，且滚动不越界。

JS（share.js）：
```js
function setImg(u){
  var img=document.getElementById('posterImg'); if(!img) return;
  var ld=document.getElementById('posterLoading');
  if(ld)ld.classList.remove('hidden');
  if(st.lastURL&&st.lastURL.indexOf('blob:')===0)URL.revokeObjectURL(st.lastURL);
  st.lastURL=u; img.src=u;
  img.onload=img.onerror=function(){ if(ld)ld.classList.add('hidden'); };
}
function open(d){ /* ...现有... */ document.documentElement.classList.add('poster-open'); render().then(setImg).catch(function(){var ld=document.getElementById('posterLoading');if(ld)ld.classList.add('hidden');}); }
function close(){ /* ...现有... */ document.documentElement.classList.remove('poster-open'); }
```
> `close()` 现有逻辑把 `img.src=''`；加载指示在 close 时复位（下次 open 由 `setImg` 先 `.remove('hidden')` 重新显示）。

## 4. 边界与风险

| 项 | 处理 |
|---|---|
| 加载失败（字体/背景图/画布异常） | `render().catch` 隐藏加载指示，避免永久转圈 |
| 矮屏/横屏（高度 < ~600px） | `max-height` 封顶后帧收窄；仍超高由 `.poster-scroll` 兜底内部滚动（`overscroll-behavior:contain`） |
| 关闭按钮在 `bottom:-46px` | `max-height:calc(100vh-10rem)` 留出 ~160px 上下余量，不会出屏 |
| 非 `showModal` 浏览器 | `.poster-fallback` 已 `position:fixed; inset:0; margin:auto`，保持 |
| `aspect-ratio` 兼容 | 现代浏览器均支持；老浏览器退化为无高度（黑底塌陷同现状，不更糟） |
| body 滚动锁与 `::backdrop` | `poster-open` 仅锁根滚动，`dialog[open]` 顶部层不受影响；关闭即恢复 |
| 移动端长按保存 | `img` 尺寸变化不影响 `-webkit-touch-callout`/`user-select` 设置 |

## 5. 验收清单

- [ ] 打开海报：先显示**黑色 2:3 占位 + 「生成中…」**，图加载后无缝替换，无塌陷/跳变；加载失败不无限转圈。
- [ ] 海报高度**不超屏**：任意窗口高度下完整可见（矮屏收窄高度，宽随 2:3 收），无需页面滚动看全图。
- [ ] 海报出现时**页面滚动被锁**：滚轮/滚动条/方向键不滚 body；海报固定在视口不随页面移动。
- [ ] 海报内（矮屏）可内部滚动且滚到边界不带动页面。
- [ ] 关闭后恢复页面滚动；重复打开正常；样式切换（‹› / 左右滑动）与「借」字标注不回归。
- [ ] 桌面/移动端、`.poster-fallback` 路径、无 JS 异常。
