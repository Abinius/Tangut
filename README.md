# Tangut Dictionary · 蕃汉合时掌中珠 · PHP 动态站

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![Data](https://img.shields.io/badge/data-2026.11.0-7734db)](https://voue.cn/data/)
[![Site](https://img.shields.io/badge/site-voue.cn-3498db)](https://voue.cn/)

输入任意汉字，查看西夏文候选字形、拟音与义项依据。通过 `.env` 配置站点信息（品牌/标题/备案/友链/colophon 等），改一处刷新即全站生效，无需重新构建。

## 目录结构

```
php-site/
├── .env                  ← 站点配置（改这里！）
├── .env.example          ← 配置模板
├── index.php             ← 查询首页（/?q=青）
├── x.php                 ← 汉字详情页（/x-青/）
├── c.php                 ← 字头详情页（/c-0257/，75 字段六组分区）
├── about.php             ← 关于页（/about）
├── api.php               ← REST API 只读 JSON（/api/v1/*）
├── sitemap.php           ← 动态 sitemap（/sitemap.xml）
├── router.php            ← PHP 内置服务器路由（本地测试用）
├── radical.php           ← 部首检字（/radical/{unicode|xh|eyh|xwd}/）
├── stroke.php            ← 笔画检字（/stroke/{n}/）
├── rhyme.php             ← 声韵检字（/rhyme/{tongyin|wenhai|yunlei}/）
├── word.php              ← 词组查询（/word/h-{汉字}/）
├── wenhai.php            ← 文海文献（/wenhai/{ref}/）
├── tongyin.php           ← 同音对照（/tongyin/{group}/）
├── lookup.php            ← 四角/仓颉/IDS 检字（/sijiao|cangjie|ids/{v}/）
├── .htaccess             ← Apache 伪静态 + 缓存 + MIME
├── includes/
│   ├── config.php        ← 读 .env，提供 cfg()/常量/friend_links()
│   ├── data.php          ← 读分片，查询 lookup_hanzi/find_detail
│   ├── dict.php          ← 字头详情 75 字段渲染
│   ├── indexes.php       ← 各检字索引读取 + 候选列表渲染
│   ├── layout.php        ← 统一头部/页脚/候选卡/system_icon/backtop
│   ├── radical_all.php   ← 部首字形 SVG 矢量
│   └── radical_svg.php   ← 首页部首 SVG
├── assets/style.css      ← 全站样式（两级字体 + 响应式）
├── fonts/                ← 西夏字体四级子集（core 首屏 + rest/fix/idsops 懒加载）
├── index_shards/         ← 索引分片 + manifest + stats.json + 各检字索引
├── detail/               ← 6090 个字头详情 JSON（LFW → 75 字段）
├── data/                 ← 开放数据包
├── _qa/                  ← QA 脚本（qa_run.py 豆腐块/超链接检查）+ 快照
├── favicon.* og-image.png llms.txt
└── README.md
```

## 部署（三步）

### 1. 上传解压

把 `tangutlab-php-prod.zip` 解压到站点根目录（如 `/www/wwwroot/voue.cn`）。

### 2. 改 `.env`

```ini
DATA_VERSION=2026.11.0
SITE_URL=https://你的域名
RATE_LIMIT_PER_MIN=60

# 品牌（hero 用）
BRAND=Tangut Dictionary
BRAND_TAGLINE=蕃汉合时掌中珠|消失的古文字|煮梦纪

# SEO / 分享（<title>/og:title 用 SITE_TITLE）
SITE_NAME=The Lost Script of Western Xia
SITE_TITLE=TANGUT — 汉字→西夏文查询
SITE_DESCRIPTION=免费公益工具：输入任意汉字，查看西夏文候选、拟音与义项依据。
OG_IMAGE=/og-image.png

# 备案（无则留空，页脚自动隐藏）
ICP_BEIAN=京ICP备xxxxxxxx号-1
ICP_URL=https://beian.miit.gov.cn
GONGAN_BEIAN=
GONGAN_URL=https://beian.mps.gov.cn

# 页脚 colophon（PRESS/EDITION 自动取域名/版本，此处配 PAPER 与 TYPE）
COLOPHON_PAPER=WARM WHITE #FAFAF8
COLOPHON_TYPE=LIGHT GROTESK + NOTO SERIF TANGUT

# 友链（页脚「友链：」行；站名|URL，逗号分隔；留空则隐藏）
FRIEND_LINKS=Tangut Data|https://github.com/tangutdata
```

### 3. 选服务器类型

#### Apache / 宝塔（最简单）
`.htaccess` 已含伪静态，确保 Apache 开启 `mod_rewrite`，宝塔站点设置勾选「加载 rewrite」。

#### Nginx（手动配置）

```nginx
server {
    listen 80;
    server_name 你的域名;
    root /www/wwwroot/tangutlab;
    index index.php;

    # /x-青/ → x.php
    location ~ ^/x-([^/]+)/?$ {
        try_files $uri $uri/ /x.php?h=$1;
    }
    location ~ ^/about/?$ {
        try_files $uri $uri/ /about.php;
    }
    location = /sitemap.xml {
        try_files $uri $uri/ /sitemap.php;
    }

    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-74.sock;  # 改成你的 php-fpm
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 字体 MIME + 长缓存
    location /fonts/ {
        types { } default_type font/woff2;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
    location ~* \.(woff2|webp|png|ico|css)$ {
        add_header Cache-Control "public, max-age=604800";
    }

    # 禁止访问 .env
    location ~ /\.env { deny all; }
}
```

## 本地测试

```bash
cd php-site
php -S 127.0.0.1:8080 router.php
# 浏览器打开 http://127.0.0.1:8080/
# 查询：http://127.0.0.1:8080/?q=青
# 详情：http://127.0.0.1:8080/x-青/
# 关于：http://127.0.0.1:8080/about
# sitemap：http://127.0.0.1:8080/sitemap.xml
# Agent API：http://127.0.0.1:8080/api/v1/lookup?char=青
# 开放数据包：http://127.0.0.1:8080/data/tangutlab_data_2026.11.0.json
```

> ⚠️ **`router.php` 仅限本地测试。** `php -S` 会直接返回磁盘上存在的文件；`router.php` 已拦截 `.env` 返回 403，但生产环境仍必须用 Apache/Nginx + `.htaccess`（或上述 Nginx 配置）由服务器层拒绝 `.env` 访问，且禁止将 `php -S` 绑定到 `0.0.0.0` 对外服务。

> **API 安全说明：** `Access-Control-Allow-Origin: *` 是公开只读 API（PRD A-06）的显式设计，允许任意源跨域只读查询；API 与全站响应已加 `X-Content-Type-Options: nosniff`、`X-Frame-Options: SAMEORIGIN`、`Referrer-Policy: strict-origin-when-cross-origin`，JSON 输出做 `JSON_HEX_*` 转义。限流默认 60 次/分/IP（`.env` 的 `RATE_LIMIT_PER_MIN`，设 `0` 关闭）。

## 与原静态站的区别

| 项 | 静态站（HTML） | PHP 站（本包） |
|---|---|---|
| 配置 | 构建时烘焙进 HTML，改配置需重新构建 | `.env` 实时读取，改完刷新即生效 |
| 页面 | 3424 个预生成 HTML | 14 个 PHP 模板动态生成（查询/详情/检字/文献） |
| 检字 | 无 | 部首/笔画/声韵/四角/仓颉/IDS 六体系 |
| 查询 | 浏览器加载分片 JS 本地查找 | 服务端 PHP 查分片渲染 |
| 部署 | 任意静态服务器 | 需 PHP 7.4+ |

## 要求

- PHP **7.4+**（推荐 8.x）
- 无需数据库
- 数据/字体已随包附带（`index_shards/` `detail/` `fonts/`）
