# 分享海报节日背景切换 — 开发文档与开发计划

> 日期：2026-11-17 · 状态：**已实施（commit 见 git log）** · 预估：约 1 天（0.5–1 天）
> 项目：voue.cn 西夏文词典 · 相关：[[voue-cn-deploy]]

## 0. 实施说明（相对本方案的两处实现偏差）

1. **注入形状**：`window.__POSTER_BG` 直接就是池对象 `{default:[..], festival:{spring:[..],..}, active}`，
   无 `.pool` 包装（`share.js` 的 `pool()` 直接读 `P.default` / `P.festival[P.active]` / `P.active`）。
2. **节日池来源**：不用平铺的 `POSTER_BG_FESTIVAL` 列表（27 张图太长），改为 `POSTER_BG_FESTIVAL_DIR=/assets/bg/festival`
   目录下按 `spring|dragon|midautumn` 子目录**自动 glob `*.webp`**。图片成品在 `assets/bg/festival/{spring,dragon,midautumn}/N.webp`（1280×1920，2:3）。

其余（`festival_active()` 农历表 + `POSTER_FESTIVAL_OVERRIDE` 覆盖 + share.js 回退硬编码池）按 §3–§5 实施。


## 1. 背景与目标

分享海报（`assets/share.js`）目前从硬编码数组随机取一张背景图。目标：让海报背景能按**中国传统节日**（春节/端午/中秋）自动切换，并支持**通过后台临时切换**（服务器翻 `.env` 即生效，无需重部署/改代码）。

**目标**：
- 节日窗口内海报自动使用「节日背景池」。
- 管理员可通过 `.env` 的 `POSTER_FESTIVAL_OVERRIDE` 键临时强制某节日主题（后台临时切换）。
- 缺图/未配置时安全降级为现有默认 6 张背景，不白屏。

## 2. 现状分析

| 项 | 现状 | 位置 |
|---|---|---|
| 海报背景池 | 硬编码 `BG=['/assets/bg/bg1.webp'…bg6.webp']` | `assets/share.js:5` |
| 随机选取 | `BG[Math.floor(Math.random()*BG.length)]` | `assets/share.js:7`（`loadBg`） |
| 生成管线 | `render(): ensureFonts().then(loadBg).then(tryDraw).then(toURL)` | `assets/share.js:37` |
| 缺图降级 | `im.onerror → bgImg=null`（回退纯色） | `assets/share.js:7` |
| env 读取 | `cfg(string $key, string $default='')` | `includes/config.php:31` |
| env→JS 注入 | 已有先例：`json_encode(explode('|', BRAND_TAGLINE))` 注入 `<script>` | `index.php:56` |
| 鉴权/后台 | 站点无任何 auth/admin | — |

## 3. 方案设计

**主方案 B（自动按农历切换）+ A（env 覆盖键做临时切换）**，不做带鉴权的后台面板（性价比低）。

- **自动检测**：PHP 判断「今天是否在节日窗口」→ 命中则用节日池。
- **临时切换**：`POSTER_FESTIVAL_OVERRIDE` 覆盖自动检测，服务器改一行 `.env` 即时生效。
- **注入位置**：配置由 PHP 注入页面 `<script>`（非写死 JS），避免 `share.js` 被浏览器缓存（`?v=1`）拖旧。

### 3.1 节日窗口

每个节日取 **节前 3 天 ～ 节后 3 天**（共 7 天）为窗口；窗口内命中即激活该节日主题。若多节日窗口重叠，按优先级（春节 > 中秋 > 端午，或取最近者，实现时定）。

### 3.2 农历日期表（预置 2026–2030，每年加一行）

| 年 | 春节（正月初一） | 端午（五月初五） | 中秋（八月十五） |
|---|---|---|---|
| 2026 | 2026-02-17 | 2026-06-19 | 2026-09-25 |
| 2027 | 2027-02-06 | 2027-06-09 | 2027-09-15 |
| 2028 | 2028-01-26 | 2028-05-28 | 2028-10-03 |
| 2029 | 2029-02-13 | 2029-06-16 | 2029-09-22 |
| 2030 | 2030-02-03 | 2030-06-05 | 2030-09-11 |

> 每年新增一行即可；表外年份自动退化为默认池（安全降级）。实施时用程序核对日期后再固化。

## 4. 配置设计（`.env` 新增键）

```ini
# 海报背景：默认池（未配置时回退 share.js 硬编码 6 张）
POSTER_BG=/assets/bg/bg1.webp|/assets/bg/bg2.webp|/assets/bg/bg3.webp|/assets/bg/bg4.webp|/assets/bg/bg5.webp|/assets/bg/bg6.webp

# 节日背景池（节日窗口内启用）
POSTER_BG_FESTIVAL=/assets/bg/festival_spring.webp|/assets/bg/festival_autumn.webp

# 后台临时切换：none|spring|dragon|midautumn（覆盖自动检测；空 = 自动）
POSTER_FESTIVAL_OVERRIDE=
```

> 生产 `.env` 只追加这些键（`grep -q || printf >> `），绝不整文件覆盖（见 [[voue-cn-deploy]]）。

## 5. 代码改动点

| 文件 | 改动 |
|---|---|
| `includes/config.php` | 新增 `festival_active(): ?string`（读 override → 命中返回节日键；否则按日期表判断）与 `poster_bg_pool(): array`（返回默认/节日池） |
| `includes/layout.php` | `page_start` 注入 `<script>window.__POSTER_BG={...}</script>`（默认池/节日池/active 键） |
| `assets/share.js` | `loadBg` 优先读 `window.__POSTER_BG`：`active` 命中用节日池，否则默认池；均无则回退硬编码 `BG`；缺图走现有 `onerror→null` 降级 |
| `.env.example` | 补 3 个新键示例 |
| `README.md` | 配置说明段补一笔 |

## 6. 开发计划

| 步骤 | 内容 | 工作量 |
|---|---|---|
| 1 | config.php：`festival_active()` + 农历表 + `poster_bg_pool()` | 2h |
| 2 | layout.php：注入 `window.__POSTER_BG` | 0.5h |
| 3 | share.js：`loadBg` 读注入池 + 回退 | 1h |
| 4 | 本地测试：override 各值、日期窗口、缺图降级、桌面+移动截图 | 1.5h |
| 5 | `.env.example`/README 更新 | 0.5h |
| 6 | 部署：增量包 + 生产验证（override 翻转验证 + 日期窗口命中） | 1h |

## 7. 测试计划

- **override**：`POSTER_FESTIVAL_OVERRIDE=spring|dragon|midautumn|none` 各值 → 海报用对应池/默认池。
- **自动检测**：把系统日期 mock 到节日窗口内（或临时改表）→ 激活节日池；窗口外 → 默认池。
- **降级**：节日池图片缺失 → `bgImg=null` → 纯色背景，不白屏、无控制台错误。
- **缓存**：`share.js` 带 `?v=1`，配置经页面注入 → 改 override 后刷新即变（不被缓存拖旧）。
- **回归**：首页/翻译/词组分页、海报打开/风格切换正常；桌面 1200 + 移动 375 无溢出、无控制台错误。

## 8. 部署说明

- 增量包（仅 `includes/config.php`、`includes/layout.php`、`assets/share.js`、`.env.example`、`README.md`）。
- 服务器 `.env` 追加 3 键（`grep -q || printf >>`），不覆盖生产专有值。
- PHP 改动即时生效，无需重启；nginx 未动无需 reload。
- 新增节日背景图需先上传到 `assets/bg/`（`festival_spring.webp` 等），属新增美术资源。

## 9. 风险与降级

| 风险 | 缓解 |
|---|---|
| 农历表错/年份超出 | 表外年份自动回退默认池；实施时程序核对日期 |
| 节日图缺失 | share.js 现有 `onerror→bgImg=null` 降级纯色 |
| 配置被缓存拖旧 | 配置走页面注入（非 JS 文件内） |
| 后台面板成本 | 不做鉴权面板，仅 env 翻键（`POSTER_FESTIVAL_OVERRIDE`） |

## 相关
- 可行性评估：分享海报背景整合 env（春节/端午/中秋 + 后台临时切换）—— 本会话讨论
- 部署流程：[[voue-cn-deploy]]
