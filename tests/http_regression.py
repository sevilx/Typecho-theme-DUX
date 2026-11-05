#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""DUX for Typecho - read-only HTTP regression suite.

Tests every route and static asset of a running Typecho site. It only issues
GET requests, so it is safe to run against production.

Usage:
    python tests/http_regression.py --base https://www.xiongjinghang.com
    python tests/http_regression.py --base http://127.0.0.1:8901 --out report.json

Exit code 0 = all pages and assets healthy, 1 = at least one failure.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import time
from urllib.parse import urljoin, urlparse, urldefrag

import requests

TIMEOUT = 20
UA = "DUX-Regression/1.0 (+https://github.com/sevilx/Typecho-theme-DUX)"

# Shapes PHP/Typecho actually emits into HTML. <pre>/<code>/<script> blocks
# are stripped first: a tech blog body legitimately contains words such as
# "Warning:" or "Fatal error".
PHP_ERROR_PATTERNS = [
    r"<b>\s*(?:Fatal error|Warning|Notice|Deprecated|Parse error|Recoverable fatal error)\s*</b>",
    r"Fatal error:\s*Uncaught",
    r"PHP\s+(?:Fatal error|Warning|Notice|Deprecated):",
    r"Uncaught\s+(?:\\?\w+\\)*\w+(?:Error|Exception)",
    r"Stack trace:\s*#0",
    r"SQLSTATE\[[A-Z0-9]+\]",
    r"must not be accessed before initialization",
    r"Call to undefined (?:method|function)\s",
    r"Declaration of .+ must be compatible",
    r"Call to a member function .+ on null",
]

ASSET_EXT = re.compile(
    r"\.(?:css|js|png|jpe?g|gif|ico|svg|woff2?|ttf|eot|mp4|webp|zip|pdf)(?:\?|$)", re.I
)

# Paths that are always probed even when the homepage does not link to them.
FIXED_PROBES = ("/", "/page/2/", "/feed/", "/search/typecho/", "/__dux_404_probe__/")

# 后台、外链跳转、动作接口不输出主题布局（没有 #footer / window.jsui），
# 不能用主题完整性去判定。尤其 /go/<base64> 会 301 到任意站外甚至内网地址，
# 盲目跟随会让只读回归变成"主动访问第三方/内网"，既误报又不安全。
NON_THEME_PATHS = re.compile(r"^/(?:admin|go|action|install|xmlrpc)(?:/|$)", re.I)
REDIRECT_CODES = (301, 302, 303, 307, 308)


def strip_noise(html: str) -> str:
    html = re.sub(r"<pre\b[^>]*>.*?</pre\s*>", " ", html, flags=re.I | re.S)
    html = re.sub(r"<code\b[^>]*>.*?</code\s*>", " ", html, flags=re.I | re.S)
    html = re.sub(r"<script\b[^>]*>.*?</script\s*>", " ", html, flags=re.I | re.S)
    return html


def scan_errors(html: str) -> list[str]:
    text = strip_noise(html)
    return [p for p in PHP_ERROR_PATTERNS if re.search(p, text, re.I | re.S)]


def make_session(base: str) -> requests.Session:
    for trust_env in (False, True):
        s = requests.Session()
        s.trust_env = trust_env
        s.headers.update({"User-Agent": UA, "Accept": "text/html,*/*;q=0.8"})
        try:
            r = s.get(base + "/", timeout=10)
            if r.status_code < 500:
                return s
        except Exception:
            pass
        s.close()
    raise RuntimeError("cannot reach %s (direct and proxy both failed)" % base)


def same_host(url: str, base: str) -> bool:
    return urlparse(url).netloc == urlparse(base).netloc


def discover_routes(session: requests.Session, base: str) -> tuple[list[str], str]:
    routes: list[str] = []
    seen: set[str] = set()

    def add(path: str) -> None:
        if path not in seen:
            seen.add(path)
            routes.append(path)

    for p in FIXED_PROBES:
        add(p)

    html = ""
    try:
        html = session.get(base + "/", timeout=TIMEOUT).text
    except Exception as exc:
        print("homepage fetch failed:", exc, file=sys.stderr)

    for href in re.findall(r"""<a\b[^>]*\bhref\s*=\s*["']([^"']+)["']""", html, re.I):
        href, _ = urldefrag(href.strip())
        if not href or href.startswith(("javascript:", "mailto:", "tel:", "#")):
            continue
        url = urljoin(base, href)
        if not same_host(url, base):
            continue
        parsed = urlparse(url)
        path = parsed.path or "/"
        if ASSET_EXT.search(path):
            continue
        if parsed.query:
            path += "?" + parsed.query
        add(path)

    return routes[:60], html


def collect_assets(html: str, base: str) -> list[str]:
    """Only <link>/<script>/<img>/<source> resources, never <a href> pages."""
    urls: set[str] = set()
    tags = re.findall(r"<(?:link|script|img|source)\b[^>]*>", html, re.I)
    for tag in tags:
        for value in re.findall(r"""(?:src|data-src|href)\s*=\s*["']([^"']+)["']""", tag, re.I):
            value = value.strip()
            if not value or value.startswith(("data:", "javascript:", "#", "mailto:")):
                continue
            url = urljoin(base, value)
            if same_host(url, base):
                urls.add(url)
    return sorted(urls)


def check_page(session: requests.Session, url: str) -> dict:
    t0 = time.perf_counter()
    origin_host = urlparse(url).netloc
    try:
        r = session.get(url, timeout=TIMEOUT, allow_redirects=False)
    except Exception as exc:
        return {"url": url, "error": str(exc), "ok": False}

    # 只跟随同站跳转；站外/内网目标只记录 Location，绝不真正发起请求
    hops = 0
    while r.status_code in REDIRECT_CODES and hops < 3:
        target = urljoin(url, r.headers.get("Location", ""))
        if urlparse(target).netloc != origin_host:
            return {
                "url": url,
                "status": r.status_code,
                "ms": round((time.perf_counter() - t0) * 1000, 1),
                "bytes": len(r.content),
                "ok": True,
                "skipped": "external redirect (not followed)",
                "redirect_to": target,
            }
        r = session.get(target, timeout=TIMEOUT, allow_redirects=False)
        hops += 1

    html = r.text
    ctype = r.headers.get("Content-Type", "")
    is_html = "html" in ctype.lower() or html.lstrip().lower().startswith("<!doctype")
    title = re.search(r"<title[^>]*>(.*?)</title>", html, re.I | re.S)

    result = {
        "url": url,
        "status": r.status_code,
        "ms": round((time.perf_counter() - t0) * 1000, 1),
        "bytes": len(r.content),
        "ctype": ctype,
        "is_html": is_html,
        "empty_body": len(r.content) == 0,
        "title": re.sub(r"\s+", " ", title.group(1)).strip() if title else "",
        "html_closed": bool(re.search(r"</html\s*>", html, re.I)),
        "has_footer": 'id="footer"' in html,
        "has_jsui": "window.jsui" in html,
        "php_errors": scan_errors(html),
    }
    if NON_THEME_PATHS.search(urlparse(url).path):
        result["ok"] = 200 <= r.status_code < 400 and not result["php_errors"]
        result["note"] = "non-theme route: layout checks skipped"
    elif r.status_code == 404:
        # A 404 is expected for the probe; record an empty body as a warning.
        result["ok"] = True
        result["warning"] = "404 body is empty" if result["empty_body"] else ""
    elif is_html:
        result["ok"] = (
            r.status_code == 200
            and not result["php_errors"]
            and result["html_closed"]
            and result["has_footer"]
        )
    else:
        # RSS / Atom / other non-HTML output: only status and PHP errors matter.
        result["ok"] = r.status_code == 200 and not result["php_errors"] and len(r.content) > 0
    return result


def check_asset(session: requests.Session, url: str) -> dict:
    t0 = time.perf_counter()
    try:
        r = session.get(url, timeout=TIMEOUT, stream=True)
        status, size = r.status_code, int(r.headers.get("Content-Length") or 0)
        r.close()
    except Exception as exc:
        return {"url": url, "status": 0, "error": str(exc), "ok": False}
    return {
        "url": url,
        "status": status,
        "ms": round((time.perf_counter() - t0) * 1000, 1),
        "size": size,
        "ok": 200 <= status < 400,
    }


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="https://www.xiongjinghang.com")
    ap.add_argument("--out", default="report.json")
    args = ap.parse_args()
    base = args.base.rstrip("/")

    session = make_session(base)
    routes, home_html = discover_routes(session, base)
    print("base: %s | discovered %d routes" % (base, len(routes)))

    pages = [check_page(session, base + p) for p in routes]
    assets = collect_assets(home_html, base)
    # also inspect assets referenced by the first post page
    post_urls = [p["url"] for p in pages if "/archives/" in p["url"] and p.get("status") == 200]
    for u in post_urls[:2]:
        try:
            assets = sorted(set(assets) | set(collect_assets(session.get(u, timeout=TIMEOUT).text, base)))
        except Exception:
            pass
    asset_results = [check_asset(session, u) for u in assets]

    bad_pages = [p for p in pages if not p.get("ok")]
    bad_assets = [a for a in asset_results if not a.get("ok")]
    warnings = [p for p in pages if p.get("warning")]
    times = sorted(p["ms"] for p in pages if "ms" in p)

    print("\n%-5s %-5s %-8s %-8s %s" % ("", "CODE", "MS", "BYTES", "URL"))
    for p in pages:
        note = ""
        if p.get("php_errors"):
            note = "  << PHP: " + "; ".join(p["php_errors"])[:110]
        elif p.get("warning"):
            note = "  << WARNING: " + p["warning"]
        elif p.get("skipped"):
            note = "  << SKIP: " + p["skipped"] + " -> " + p.get("redirect_to", "")
        elif p.get("note"):
            note = "  << " + p["note"]
        elif not p.get("ok"):
            note = "  << incomplete HTML (missing </html> or #footer)"
        print("%-5s %-5s %-8s %-8s %s%s" % (
            "OK" if p.get("ok") else "FAIL", p.get("status", "-"), p.get("ms", "-"),
            p.get("bytes", "-"), p.get("url", ""), note,
        ))

    print("\n--- assets (%d) ---" % len(asset_results))
    for a in asset_results:
        if not a.get("ok"):
            print("%-5s %-5s %-8s %s" % ("FAIL", a.get("status", "-"), a.get("size", "-"), a.get("url", "")))

    summary = {
        "base": base,
        "routes": len(pages),
        "pages_failed": len(bad_pages),
        "assets": len(asset_results),
        "assets_failed": len(bad_assets),
        "warnings": [p["url"] + ": " + p["warning"] for p in warnings],
        "external_redirects": [
            {"from": p["url"], "to": p["redirect_to"]}
            for p in pages if p.get("redirect_to")
        ],
        "latency_ms_avg": round(sum(times) / len(times), 1) if times else None,
        "latency_ms_p95": times[min(len(times) - 1, int(len(times) * 0.95))] if times else None,
        "latency_ms_max": max(times) if times else None,
    }
    print("\n--- summary ---")
    print(json.dumps(summary, ensure_ascii=False, indent=2))

    with open(args.out, "w", encoding="utf-8") as f:
        json.dump({"summary": summary, "pages": pages, "assets": asset_results}, f, ensure_ascii=False, indent=2)

    return 1 if bad_pages or bad_assets else 0


if __name__ == "__main__":
    sys.exit(main())
