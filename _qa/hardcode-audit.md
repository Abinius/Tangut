# 硬编码排查清单（voue.cn）

> 日期：2026-11-17 · 范围：`*.php` / `*.js`（排除 `_qa/`、`index_shards/`、`detail/`）
> 目的：找出**本应可配置/可动态、却写死在代码里**的值，评估是否 env 化 / 动态化 / 保留。
> 关联：[[festival-poster-dev-plan]] · [[voue-cn-deploy]]

## 结论速览

| 优先级 | 项 | 现状 | 建议 |
|---|---|---|---|
| P1 | 首页检字统计数字 | `2687/18292/3064` 写死 | 动态取自索引 |
| P1 | 海报背景池 | `bg1–6.webp` 写死 | env 化（见节日方案） |
| P2 | share.js 页脚文案 | 书名 + 域名写死 | 走 BRAND/SITE_URL |
| P2 | about.php 书目统计 | `3064/6133/6074` 写死 | 集中/动态 |
| P3 | `share.js?v=1` 缓存版本 | 写死，改 JS 需手动 bump | 构建期 hash 或 env |
| P3 | about.php 外链 | notofonts/tangutdata 写死 | 可 env 化（价值低） |
| — | 配色/字体族/官方备案 URL | 设计 token / 固定值 | **保留** |

## A. 应外部化 / 动态化

### A1. 首页检字入口统计数字（P1）
`index.php:263 / 273 / 278` 三处统计**写死**，与数据脱钩（数据更新后显示不更新）：

| 行 | 硬编码 | 对应页 |
|---|---|---|
| `index.php:263` | `2687 组` | 同音文献 `/tongyin/` |
| `index.php:273` | `18292 条词组` | 词组查询 `/word/` |
| `index.php:278` | `3064 条目 · 反切` | 文海文献 `/wenhai/` |

**根因**：`site_stats()`（`includes/data.php:341`）只统计 `hanzi/candidates/data_version`，**不含**词组数、文海条目数、同音组数。
**建议**：扩展 `site_stats()`（或各索引读取函数）返回 `total_words`（`index_word.json.words` 计数）、`wenhai_total_refs`、`tongyin_total_groups`，首页改为 `<?= (int) $stats['total_words'] ?> 条词组`。这样数据更新即自动同步。
**工作量**：~1–2h。

### A2. 海报背景池（P1，已有方案）
`assets/share.js:5`：
```js
var BG = ['/assets/bg/bg1.webp', … ,'/assets/bg/bg6.webp'];
```
**建议**：见 [[festival-poster-dev-plan]]——`.env` 新增 `POSTER_BG` / `POSTER_BG_FESTIVAL` / `POSTER_FESTIVAL_OVERRIDE`，PHP 注入 `window.__POSTER_BG`，`loadBg` 读取。
**工作量**：~1 天（含节日逻辑）。

### A3. share.js 页脚文案（P2）
`assets/share.js:28`（`footer()`）：
```js
x.fillText('骨勒茂才《蕃汉合时掌中珠》', W/2, H-136);
x.fillText('voue.cn', W/2, H-68);
```
**问题**：书名 `骨勒茂才《蕃汉合时掌中珠》` 与域名 `voue.cn` 写死在 JS；域名改版/书名措辞调整需改代码。
**建议**：随 A2 一起，由 PHP 注入 `window.__POSTER_BRAND={book, site}`（书名可用 `BRAND_TAGLINE` 或新键 `POSTER_BOOK`，站点用 `SITE_URL` host）。**工作量**：~0.5h。

### A4. about.php 书目统计（P2）
`about.php:73`：
```php
《文海》收约 <strong>3064</strong> 字，《音同》收 <strong>6133</strong> 字，《夏汉字典》收录约 <strong>6074</strong> 字。…龚煌城拟音最为通行。
```
**建议**：三个数字来源同 A1（文海条目/同音组/词组/字典收录），可并入扩展后的 `site_stats()`；或至少集中到一个常量/配置数组，避免散落。注意《夏汉字典》6074 目前无对应索引，若无法动态则至少挪到 `cfg()`。
**工作量**：~1h。

### A5. about.php 外部链接（P3）
`about.php:21`：`https://github.com/notofonts/tangut`（Noto Serif Tangut）；`about.php:42` 提及 `tangutdata`(MIT)。
**建议**：价值低，可 env 化为 `DATA_SOURCE_FONT_URL` / `DATA_SOURCE_DATA`；不改也可接受。**工作量**：~0.5h。

## B. 缓存版本 / 可集中

### B1. `share.js?v=1` 缓存破坏版本（P3）
`includes/layout.php:173` 与 `:321`（两处懒加载海报脚本）：
```php
s.src = '/assets/share.js?v=1';
```
**问题**：改 `share.js` 需手动同步 bump 两处 `?v=1`，易漏（漏了用户拿到旧缓存）。
**建议**：集中为 `define('ASSET_VER', '1')`（env `ASSET_VER`），两处引用同一常量；更进一步用文件 mtime/hash 自动生成。**工作量**：~0.5h。

## C. 合理硬编码 — 建议保留

| 项 | 位置 | 理由 |
|---|---|---|
| 品牌配色（acid/chrome/holo 三套 palette） | `share.js:14–16` | 海报**设计 token**，非运行配置；改动即改设计 |
| 字体族名 `F.t/F.s/F.m` | `share.js:9–10` | 字体族名，非路径（路径已在 env `TANGUT_*_FONT`） |
| CSS 品牌色（~31 处 hex） | `assets/style.css` | 设计 token（`var(--accent)` 体系），非配置 |
| 备案官方 URL `beian.miit.gov.cn` / `beian.mps.gov.cn` | `config.php:133,135` | 国家固定地址，env 已可覆盖 |
| Noto Serif Tangut 名称 | `about.php:21` | 见 A5（低价值） |
| `config.php` 各 `define()` 的默认值 | `config.php:119–143` | 有 env 覆盖，符合「env 优先 + 代码兜底」规范 |

## D. 已正确 env 化 — 无需改动

`config.php` 已将以下全部走 `cfg()`（env 优先）：`SITE_URL`、`BRAND`、`BRAND_TAGLINE`、`SITE_NAME`、`SITE_TITLE`、`SITE_DESCRIPTION`、`OG_IMAGE/OG_TYPE/OG_TITLE/OG_DESCRIPTION/TWITTER_CARD`、`TANGUT_CORE/REST/FIX/IDSOPS_FONT`、`ICP_BEIAN/ICP_URL`、`GONGAN_BEIAN/GONGAN_URL`、`FRIEND_LINKS`、`RATE_LIMIT_PER_MIN`、`DATA_VERSION`、`DISCLAIMER_ZH/EN`、`LICENSE_TEXT`、`COLOPHON_PAPER/COLOPHON_TYPE`。`data_version()` 以 manifest 为准、回退 env。

## 建议实施顺序

1. **A1**（首页统计动态化）— 数据准确性，收益最高、成本最低（~1–2h）。
2. **B1**（`ASSET_VER` 集中）— 防缓存事故（~0.5h）。
3. **A2+A3**（海报背景 env 化 + 页脚注入）— 并入 [[festival-poster-dev-plan]]（~1 天）。
4. **A4**（about 书目）— 随 A1 顺手（~1h）。
5. **A5**（外链 env）— 可选。

**总工作量（不含 A2）**：约 3–4h；含海报背景节日化另计 ~1 天。

## 相关
- [[festival-poster-dev-plan]]：海报背景 env 化 + 节日切换
- [[voue-cn-deploy]]：生产 `.env` 只追加键、不整文件覆盖
