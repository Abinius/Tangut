# 翻译功能（长文互译）开发文档

> 需求（2026-11-16）：搜索框汉字 ≤10 走现有起名/海报；**>10 个汉字走翻译功能，不走海报分享**。翻译页把整句汉字逐字译为西夏文，每个西夏文可点击弹窗查看候选、上下滚动选择替换。
> 状态：**已实现并验证（2026-11-16）**。`router.php` 加 `/translate` 路由；`index.php` 输入截断 16→100、纯汉字 >10 重定向；新增 `translate.php`（源文 + 译文按钮行 + `trDlg` 候选弹窗，复用 `.nb-card` 滑轮）+ `style.css` 的 `.translate/.tr-*` 样式。`php -S` + 无头 Chrome/CDP 实测：16 汉字 302 重定向、译文 16 字全渲染、借字角标、点字弹窗 20 候选、滚动居中选中、点选替换字形、ESC/×/背板关闭、锁滚动解锁、混合输入标点透传、移动端 5 列、无控制台错误。

---

## §1 产品需求

### 1.1 分流规则（搜索框）
| 汉字数 | 路径 | 说明 |
|---|---|---|
| 1 | 现有单字结果页 | 不变 |
| 2–10 | 现有起名构建器 + 海报分享 | 不变（仅明确上限 10） |
| **>10** | **翻译功能页**（新） | 不走海报；逐字互译 + 候选弹窗 |
| 含非汉字 | 现有英文/词组/码位分发 | 不变 |

> 输入上限放宽到 **100 字**（翻译需要容纳短句）；超 100 截断。纯汉字且 >10 时重定向到 `/translate?q=…`。

### 1.2 翻译页核心交互
1. **源文展示**：用户输入的汉字串（原文）。
2. **译文展示**：逐字取每个汉字的**首选西夏文候选**，横向排成西夏文译文（从左到右，符合页面现有方向）。无对应字以 `○` 占位。
3. **点击替换**：译文中任一西夏文 → 点击弹窗，显示该位置所有候选西夏字（含借字回退），**纵向滚动滑轮**（居中选中），点选即替换该位置译文。
4. **候选弹窗**：复用起名构建器的竖排滑轮交互（`.nb-wheel`/`.nb-card`：单列、上下滚动、居中选中、`↑/↓` 步进）。
5. **分享**：翻译结果**不走海报分享**（需求明确）；可提供「复制译文」文本（可选，二期）。

### 1.3 候选数据来源
复用 `lookup_text($q)`（`includes/data.php:71`）：每个汉字 → `['char', 'candidates'[]]`，候选含 `tangut/gong/gloss_zh/tier/lfw_id`；无直接候选时走 `pinyin_fallback`（借字，同起名构建器）。

### 1.4 非目标
- 不做整句语义翻译（无 NLP）；仅逐字映射 + 用户手选候选。
- 不接入海报生成（`TangutPoster` 不在翻译页加载）。
- 不改起名构建器（≤10 字路径不变）。

---

## §2 开发设计

### 2.1 路由与分流
**router.php** 加路由：
```php
// /translate?q=汉字串 → translate.php
if ($uri === '/translate' || preg_match('#^/translate/?$#', $uri)) {
    require __DIR__ . '/translate.php';
    return true;
}
```
**index.php 分流**（改 `$nameBuilder` 判断附近）：
```php
$hanziCount = preg_match_all('/\p{Han}/u', $q);
if ($hanziCount > 10) {
    header('Location: /translate?q=' . rawurlencode($q));
    exit;
}
```
> 放在 `search_target` 之前（汉字长文优先走翻译，不被英文/词组分发拦截）。输入截断改为 100：`mb_substr(..., 0, 100, 'UTF-8')`。

### 2.2 translate.php 页面结构
```php
<?php
require_once __DIR__ . '/includes/layout.php';
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 100, 'UTF-8');
$items = lookup_text($q);          // 每字候选
page_start('翻译 · ' . h($q), …);
?>
<section class="translate">
  <div class="seclabel"><span>翻译</span><span class="rule"></span></div>
  <div class="tr-result" id="trResult">
    <?php foreach ($items as $i => $it):
      $top = $it['candidates'][0] ?? null;
      $fb = $top ? null : (pinyin_fallback($it['char'], 4)[0] ?? null);
      $c = $top ?: $fb;
    ?>
      <button type="button" class="tr-glyph<?= $fb ? ' is-borrow' : '' ?>"
              data-tr-i="<?= $i ?>"
              data-tr-cands='<?= h(json_encode($it['candidates'] ?: pinyin_fallback($it['char'], 4), JSON_UNESCAPED_UNICODE)) ?>'>
        <span lang="txg"><?= h($c['tangut'] ?? '○') ?></span>
      </button>
    <?php endforeach; ?>
  </div>
  <p class="tr-hint">点击任一西夏文查看候选</p>
</section>
```
> 每个字位是 `<button class="tr-glyph">`，载荷 `data-tr-cands` 含该字全部候选（含借字）。

### 2.3 候选弹窗（复用竖排滑轮）
复用 `posterDlg` 的 `<dialog>` 模式，新建 `trDlg`：
```html
<dialog id="trDlg" class="tr-dlg">
  <button class="ps-close" id="trClose" aria-label="关闭">×</button>
  <div class="tr-wheel" role="radiogroup" aria-label="候选西夏字">
    <!-- 动态填充 .nb-card（复用样式） -->
  </div>
</dialog>
```
JS（translate.php 内联或 assets/tr.js）：
- 点 `.tr-glyph` → 读 `data-tr-cands` → 动态生成 `.nb-card` 列表填入 `.tr-wheel` → `trDlg.showModal()` + 锁滚动（同海报）。
- 滚轮 `scroll` → `centerSelect`（单列版，已存在于 layout.php，可抽公共或重写轻量版）。
- 点 `.nb-card` → 替换对应 `.tr-glyph` 的字形 → 关闭弹窗。
- ESC/×/背板关闭（复用 `cancel` 监听）。

### 2.4 CSS（assets/style.css）
- `.translate`：容器（复用 `.index` 磨砂玻璃风）。
- `.tr-result`：`display:grid; grid-template-columns:repeat(auto-fill,minmax(58px,1fr))`，每字一张对照卡（上原文汉字、下西夏文），换行成网格对齐。
- `.tr-glyph`：单字按钮，`font-family:var(--tangut); font-size:2.8rem; background:none; border:1px solid transparent; border-radius:10px; padding:0.3rem 0.5rem; cursor:pointer`；hover/`:focus` 描边；`.is-borrow` 加借字角标色。
- `.tr-dlg`：复用 `.poster-dlg` 的 `position:fixed; inset:0; margin:auto; width:fit-content`；内含 `.tr-wheel`（单列竖排滑轮，复用 `.nb-wheel`/`.nb-card` 样式，窗口 2.6×卡高）。
- `.tr-hint`：提示文字。

### 2.5 边界与风险
| 项 | 处理 |
|---|---|
| 超长输入（>100） | `mb_substr` 截 100；翻译页提示「已截断至 100 字」 |
| 含标点/数字 | `\p{Han}` 计数只算汉字；非汉字字位直接透传原文（不译） |
| 无候选字 | `○` 占位 + 借字回退（pinyin_fallback），角标「借」 |
| 弹窗与海报弹窗冲突 | 独立 `trDlg`，不加载 `share.js`；锁滚动用独立类 `tr-open` |
| 候选弹窗复用滑轮代码 | 单列 `centerSelect`/`stepCard` 在 layout.php 已有；translate.php 内联轻量版避免跨文件依赖 |
| 移动端 | `.tr-result` 横向换行；弹窗滑轮手机 2 列候选可选（或保持单列滚动） |
| SEO/分享 | `/translate?q=…` 可被搜索引擎收录；og 标题用原文 |

---

## §3 开发计划

**范围**：新增 `translate.php` + router 路由 + index.php 分流 + CSS + 弹窗 JS；复用 `lookup_text`/`pinyin_fallback`/`.nb-wheel`/`.nb-card`/`.poster-dlg` 模式。不改起名构建器、不改海报。

**步骤**
1. **router.php**：加 `/translate` 路由。
2. **index.php**：输入截断 16→100；纯汉字 >10 时 `Location: /translate?q=…`（在 `search_target` 前）。
3. **translate.php**：页面骨架 + 源文 + 译文按钮行（每字 `data-tr-cands` 载荷）+ `trDlg` 弹窗标记 + 内联 JS（点开弹窗、滑轮选中、替换字位、ESC/背板关闭）。
4. **style.css**：`.translate/.tr-result/.tr-glyph/.tr-dlg/.tr-wheel/.tr-hint`；复用 `.nb-wheel/.nb-card/.ps-close`。
5. **本地验证**：`php -S` + 无头 Chrome/CDP——
   - ≤10 汉字仍走起名/海报（不回归）；
   - >10 汉字重定向到 `/translate`；
   - 译文逐字渲染、无候选 `○` + 借字；
   - 点任一西夏文 → 弹窗滑轮、上下滚动居中选中、点选替换、ESC/背板/× 关闭；
   - 移动端布局；无控制台错误、无 PHP 告警。
6. **收尾**：`php -l translate.php`、`node --check` 内联 JS；无回归（起名/海报/搜索/各页）；本文档标已实现。

## §4 验收清单
- [x] 搜索框 2–10 汉字：走起名构建器 + 海报（现有行为不回归）。
- [x] 搜索框 >10 汉字：重定向 `/translate?q=…`，不走海报。
- [x] 翻译页：源文 + 译文横向排列，逐字首选西夏文，无候选 `○`/借字标注。
- [x] 点任一西夏文：弹窗显示该字候选，纵向滑轮上下滚动、居中选中、`↑/↓` 步进。
- [x] 选候选 → 替换该位置译文；ESC/×/背板关闭弹窗；弹窗打开时锁页面滚动。
- [x] 含标点/数字的混合输入：非汉字透传、不计入汉字数分流。
- [x] 移动端布局正常；起名构建器、海报、搜索、各详情页无回归。
- [x] 无控制台错误、无 PHP 告警。
