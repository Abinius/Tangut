"""QA 单轮：豆腐块（西夏码位→字体子集覆盖）+ 超链接（全路由 HTTP 状态）。

用法：
  python _qa/qa_run.py http://127.0.0.1:8080

豆腐块判定：页面上出现的每个西夏码位必须落在
  Tangut(core) ∪ TangutRest(rest) ∪ TangutFix(fix) ∪ TangutIdsOps(idsops)
的 cmap 并集内，否则浏览器渲染为 □（永久豆腐块）。
超链接判定：枚举全站路由（导航 + 3422 个 /x-汉/ + 6090 个 /c-LFW/）逐一 GET，
  再收集页面内 <a>/<link>/<script>/<img> 引用补抓，非 200 即坏链。
输出 JSON 到 _qa/qa_report.json，退出码：有豆腐块或坏链则非零。
"""
from __future__ import annotations

import json
import os
import re
import sys
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from fontTools.ttLib import TTFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FONTS = os.path.join(ROOT, "fonts")
DETAIL = os.path.join(ROOT, "detail")
MANIFEST = os.path.join(ROOT, "index_shards", "manifest.json")

# 西夏文主块 + 部件 + 增补
TANGUT_RE = re.compile(r"[\U00017000-\U00018D8F]")

FONT_FILES = {
    "core": "tangut_core-be5a06e05246.woff2",
    "rest": "tangut_rest-cba4d927d1e4.woff2",
    "fix": "tangut_fix-bc6408ee5bc4.woff2",
    "idsops": "tangut_idsops-8b8d5c156180.woff2",
}


def cmap(path: str) -> set[int]:
    f = TTFont(path, lazy=True, fontNumber=0)
    try:
        return set(f.getBestCmap().keys())
    finally:
        f.close()


def build_coverage() -> dict:
    cov: dict[str, set[int]] = {}
    for name, fn in FONT_FILES.items():
        cov[name] = cmap(os.path.join(FONTS, fn))
    union = set().union(*cov.values())
    return {"per": cov, "union": union}


def fetch(url: str, timeout: float = 20.0):
    """返回 (status, text_or_None)。仅解析文本类响应。"""
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "tangut-qa/1.0"})
        with urllib.request.urlopen(req, timeout=timeout) as r:
            code = r.getcode()
            ctype = r.headers.get("Content-Type", "")
            body = r.read()
            if "text/html" in ctype or "xml" in ctype or "json" in ctype:
                try:
                    return code, body.decode("utf-8", "replace")
                except Exception:
                    return code, None
            return code, None
    except urllib.error.HTTPError as e:
        return e.code, None
    except Exception:
        return None, None


def tangut_cps(text: str) -> set[int]:
    return set(ord(c) for c in TANGUT_RE.findall(text or ""))


def main() -> int:
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080").rstrip("/")
    coverage = build_coverage()
    union = coverage["union"]

    # ── 数据级豆腐块：detail/*.json 全文的西夏码位 ──
    detail_files = sorted(
        f for f in os.listdir(DETAIL) if f.endswith(".json")
    )
    data_used: set[int] = set()
    for fn in detail_files:
        try:
            data_used |= tangut_cps(open(os.path.join(DETAIL, fn), encoding="utf-8").read())
        except Exception:
            pass

    # ── 路由枚举 ──
    nav = ["/", "/about", "/radical/", "/stroke/", "/rhyme/", "/word/", "/wenhai/", "/tongyin/", "/sitemap.xml"]
    manifest = json.load(open(MANIFEST, encoding="utf-8"))
    hanzi = list(manifest["hanzi_to_shard"].keys())
    x_routes = ["/x-" + urllib.parse.quote(h, safe="") + "/" for h in hanzi]
    c_routes = ["/c-" + fn[:-5] + "/" for fn in detail_files]
    routes = nav + x_routes + c_routes

    # ── 抓取所有路由 ──
    results: dict[str, tuple] = {}
    with ThreadPoolExecutor(max_workers=16) as ex:
        futs = {ex.submit(fetch, base + r): r for r in routes}
        for fut in as_completed(futs):
            results[futs[fut]] = fut.result()

    broken = [(r, s) for r, s in results.items() if s[0] != 200]
    pages = {r: (s, t) for r, (s, t) in results.items() if s == 200 and t is not None}

    # ── 渲染级豆腐块：每页文本码位 → 并集覆盖 ──
    page_uncovered: dict[str, list[str]] = {}
    page_not_core: dict[str, list[str]] = {}
    rendered_used: set[int] = set()
    for r, (s, t) in pages.items():
        cps = tangut_cps(t)
        rendered_used |= cps
        miss = cps - union
        notcore = cps - coverage["per"]["core"]
        if miss:
            page_uncovered[r] = ["U+%05X" % c for c in sorted(miss)]
        if notcore:
            page_not_core[r] = ["U+%05X" % c for c in sorted(notcore)]

    data_missing = data_used - union

    # ── 超链接：页面内引用补抓 ──
    href_re = re.compile(
        r'(?:href|src)=["\']([^"\']+)["\']', re.I
    )
    meta_content_re = re.compile(r'content=["\']([^"\']+)["\']', re.I)
    srcset_re = re.compile(r'srcset=["\']([^"\']+)["\']', re.I)
    found_refs: set[str] = set()
    for r, (s, t) in pages.items():
        for m in href_re.finditer(t):
            u = m.group(1)
            if u.startswith("#") or u.startswith("data:") or u.startswith("mailto:"):
                continue
            found_refs.add(u)
        for m in meta_content_re.finditer(t):
            u = m.group(1)
            if u.startswith("/") or u.startswith("http"):
                found_refs.add(u)
        for m in srcset_re.finditer(t):
            for part in m.group(1).split(","):
                u = part.strip().split(" ")[0]
                if u and not u.startswith("data:"):
                    found_refs.add(u)

    # 解析为绝对路径（同源）；外部 http(s) 单独列出，不做本地抓取
    ref_paths: set[str] = set()
    external_refs: set[str] = set()
    for u in found_refs:
        if u.startswith("//"):
            external_refs.add("https:" + u)
            continue
        if u.startswith("http://") or u.startswith("https://"):
            if u.startswith(base):
                ref_paths.add(urllib.parse.urlparse(u).path)
            else:
                external_refs.add(u)
            continue
        p = u.split("#")[0].split("?")[0]
        if p and p.startswith("/"):
            ref_paths.add(p)
        elif p:
            ref_paths.add("/" + p)

    extra = sorted(ref_paths - set(results.keys()))
    if extra:
        with ThreadPoolExecutor(max_workers=16) as ex:
            futs = {ex.submit(fetch, base + u): u for u in extra}
            for fut in as_completed(futs):
                results[futs[fut]] = fut.result()

    ref_broken = [(u, results[u][0]) for u in sorted(ref_paths) if results.get(u, (None,))[0] != 200]
    ref_missing = [u for u in sorted(ref_paths) if u not in results]

    # ── 汇总（精简报告：判定 + 计数 + 小样本，避免冗长列表撑爆产物） ──
    def summarize(items: list[str]) -> dict:
        return {"count": len(items), "sample": items[:10]}

    per_sizes = {k: len(v) for k, v in coverage["per"].items()}
    report = {
        "base": base,
        "routes_checked": len(routes),
        "links_extra_refs": len(extra),
        "font_stack": per_sizes,
        "font_union": len(union),
        "tofu": {
            "data_codepoints_used": len(data_used),
            "data_missing": sorted("U+%05X" % c for c in data_missing),
            "rendered_codepoints_used": len(rendered_used),
            "rendered_missing": sorted(
                "U+%05X" % c for c in (rendered_used - union)
            ),
            "pages_with_missing": page_uncovered,
            "pages_not_core": summarize(sorted(page_not_core.keys())),
        },
        "links": {
            "broken_routes": [[r, s] for r, s in broken],
            "broken_refs": [[u, s] for u, s in ref_broken],
            "unresolved_refs": ref_missing,
            "external_refs": summarize(sorted(external_refs)),
        },
    }
    out = os.path.join(ROOT, "_qa", "qa_report.json")
    with open(out, "w", encoding="utf-8") as fh:
        json.dump(report, fh, ensure_ascii=False, indent=1)

    print(json.dumps(report, ensure_ascii=False, indent=1))
    ok = not report["tofu"]["data_missing"] and not report["tofu"]["rendered_missing"] \
        and not report["links"]["broken_routes"] and not report["links"]["broken_refs"] \
        and not report["links"]["unresolved_refs"]
    print("\n=== PASS" if ok else "\n=== FAIL")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.exit(main())
