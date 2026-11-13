# 蕃汉 TANGUT Lab · PHP 动态站

通过 `.env` 配置站点信息（标题/描述/域名/备案等），改一处刷新即全站生效，无需重新构建。

## 目录结构

```
php-site/
├── .env                  ← 站点配置（改这里！）
├── .env.example          ← 配置模板
├── index.php             ← 查询首页（/?q=青）
├── x.php                 ← 汉字详情页（/x-青/ 或 /x.php?h=青）
├── about.php             ← 关于页（/about）
├── sitemap.php           ← 动态 sitemap（/sitemap.xml）
├── robots.txt            ← robots + sitemap 指名
├── router.php            ← PHP 内置服务器路由（本地测试用）
├── .htaccess             ← Apache 伪静态 + 缓存 + MIME
├── includes/
│   ├── config.php        ← 读 .env，提供 cfg()/SITE_* 常量
│   ├── data.php          ← 读分片，查询 lookup_hanzi/lookup_text
│   └── layout.php        ← 统一头部/页脚/候选卡片（配置驱动）
├── assets/style.css      ← 全站样式（从原静态站抽取，两级字体）
├── fonts/                ← 西夏字体两级子集（tangut_core-*.woff2 首屏 + tangut_rest-*.woff2 懒加载）
│                          └─ tangut_mvp.woff2 为子集构建源（非对外引用）
├── images/               ← hero 文石图
├── index_shards/         ← 索引分片 + manifest + stats.json
├── favicon.* og-image.png llms.txt
└── README.md
```

## 部署（三步）

### 1. 上传解压

把 `tangutlab-php-prod.zip` 解压到站点根目录（如 `/www/wwwroot/tangutlab`）。

### 2. 改 `.env`

```ini
SITE_URL=https://你的域名
SITE_NAME=蕃汉 TANGUT Lab
SITE_TITLE=蕃汉 TANGUT Lab — 汉字→西夏文查询
SITE_DESCRIPTION=免费公益工具：输入任意汉字，查看西夏文候选、拟音与义项依据。
DATA_VERSION=2026.11.0
# 分享图（留空则用默认 /og-image.png）
OG_IMAGE=/og-image.png
# 工信部备案（无则留空，页脚自动隐藏）
ICP_BEIAN=京ICP备xxxxxxxx号-1
ICP_URL=https://beian.miit.gov.cn
# 公安备案（无则留空）
GONGAN_BEIAN=京公网安备 xxxxxxxxxxxx号
GONGAN_URL=https://beian.mps.gov.cn
```

改完**刷新页面即生效**，无需重启、无需构建。

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
```

> ⚠️ **`router.php` 仅限本地测试。** `php -S` 会直接返回磁盘上存在的文件（含 `.env`），禁止用于对外服务；生产环境必须用 Apache/Nginx + `.htaccess`（或上述 Nginx 配置），由服务器层拒绝 `.env` 访问。

## 与原静态站的区别

| 项 | 静态站（HTML） | PHP 站（本包） |
|---|---|---|
| 配置 | 构建时烘焙进 HTML，改配置需重新构建 | `.env` 实时读取，改完刷新即生效 |
| 页面 | 3424 个预生成 HTML | 3 个 PHP 模板动态生成 |
| 查询 | 浏览器加载分片 JS 本地查找 | 服务端 PHP 查分片渲染 |
| 部署 | 任意静态服务器 | 需 PHP 7.4+ |

## 要求

- PHP **7.4+**（推荐 8.x）
- 无需数据库
- 数据/字体已随包附带（`index_shards/` `fonts/`）
