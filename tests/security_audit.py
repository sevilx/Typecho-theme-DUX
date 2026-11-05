#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
DUX for Typecho —— 只读安全审计

用途：在提交 / 上线前，检查主题目录里有没有「夹带私货」或常见安全隐患。
特点：纯标准库、不联网、不修改任何文件，可离线运行、可进 CI。

检查项：
  1. 非 PHP 文件里混入 PHP 标签（图片马 / JS 后门）
  2. 图片 / 字体文件尾部夹带可执行文本
  3. PHP 模板里的动态执行、命令执行、反序列化等危险调用
  4. 外部 URL 域名清单（区分「运行时会发起请求」与「仅注释 / 文档」）
  5. 输出型 HTML 里的固定长十六进制指纹（隐藏授权 / 追踪标记）
  6. target="_blank" 缺 noopener（tabnabbing）
  7. href/src 属性直接输出后台设置值，且未做协议白名单
  8. 全量文件 sha256 清单，用于两次发布之间的完整性比对

用法：
  python tests/security_audit.py                 # 打印摘要
  python tests/security_audit.py --json out.json # 写出结构化结果
  python tests/security_audit.py --strict        # 有 HIGH 问题时退出码 1（CI 用）
"""

import os
import re
import sys
import json
import hashlib

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP_DIRS = {'.git', '__pycache__', '.idea', '.vscode'}
TEXT_EXT = {'.php', '.js', '.css', '.html', '.htm', '.md', '.json', '.txt',
            '.less', '.scss', '.map', '.svg', '.xml', '.yml', '.yaml', '.py'}
IMG_EXT = {'.png', '.jpg', '.jpeg', '.gif', '.ico', '.webp', '.bmp',
           '.otf', '.eot', '.ttf', '.woff', '.woff2'}

# 这些域名只出现在版权注释、README 或后台可选的自定义 HTML 中，
# 主题本身不会主动向它们发起请求。
DOC_HOSTS = {
    'github.com', 'www.typecho.org', 'typecho.org', 'fontawesome.io',
    'getbootstrap.com', 'www.opensource.org', 'opensource.org',
    '312development.com', 'hammerjs.github.io', 'jsviews.com',
    'coding.net', 'example.com', 'www.yourblog.com', 'yourblog.qiniudn.com',
    'themebetter.com', 'www.fontawesome.com.cn', 'www.xiongjinghang.com',
    '127.0.0.1', 'beian.miit.gov.cn',
}
# 运行时确实会请求、但属于主题声明过的功能（头像镜像可配置）
RUNTIME_ALLOWED_HOSTS = {'cravatar.cn', 'cn.gravatar.com'}

# 压缩后的第三方库里出现 eval / new Function 属正常实现，不计入告警
VENDOR_PREFIXES = ('css/', 'js/libs/', 'js/loader.js', 'font-awesome/')

DANGEROUS_PHP = re.compile(
    r'\b(eval|assert|create_function|shell_exec|passthru|proc_open|popen|pcntl_exec|'
    r'system|exec|unserialize|base64_decode|gzinflate|gzuncompress|str_rot13|'
    r'convert_uudecode|call_user_func|call_user_func_array|file_put_contents|'
    r'move_uploaded_file|readfile|fsockopen|curl_exec)\s*\(', re.I)
PHP_TAG = re.compile(r'<\?php|<\?=|<\?\s', re.I)
URL_RE = re.compile(r'https?://[^\s"\'<>()\\]+', re.I)
HEX_TOKEN = re.compile(r'(?<![0-9a-fA-F])[0-9a-fA-F]{32,64}(?![0-9a-fA-F])')
EXEC_MARKERS = re.compile(r'<\?php|<script|eval\s*\(|system\s*\(|base64_decode|shell_exec', re.I)
BLANK_TARGET = re.compile(r'<[^>]*target=["\']_blank["\'][^>]*>', re.I)
RAW_OPTION_ATTR = re.compile(
    r'(?:href|src|action)=["\'][^"\']*(?:\$this->options->|\$options->)[A-Za-z_]\w*\s*\(\s*\)', re.I)
# 只有「管理员自行填写」的字段才需要协议白名单；siteUrl / title 等核心字段由 Typecho 生成
CUSTOM_OPTION_FIELD = re.compile(
    r'->(?:cdn_add|icoUrl|logoUrl|src_add|default_thumb|avatarMirror|sidebarAD|slidercode)\s*\(\s*\)', re.I)
OUTPUT_URL_ATTR = re.compile(
    r'(?:href|src|poster|action|content|data-src|srcset)\s*=\s*["\']?[^"\'>]*https?://'
    r'|<\s*(?:script|link|img|source|iframe|video)[^>]*https?://', re.I)


def rel(path):
    return os.path.relpath(path, ROOT).replace(os.sep, '/')


def is_vendor(rp):
    return any(rp.startswith(p) or ('/' + p) in rp for p in VENDOR_PREFIXES)


def host_of(url):
    m = re.match(r'https?://([^/:?#]+)', url, re.I)
    return (m.group(1) or '').lower() if m else ''


def sha256(path):
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        for chunk in iter(lambda: f.read(65536), b''):
            h.update(chunk)
    return h.hexdigest()


def main():
    argv = sys.argv[1:]
    json_out = None
    if '--json' in argv:
        i = argv.index('--json')
        json_out = argv[i + 1] if i + 1 < len(argv) else 'audit.json'
    strict = '--strict' in argv

    findings = []       # {level, rule, file, line, detail}
    urls = []           # {file, line, url, host, kind}
    hashes = {}
    file_count = 0

    def add(level, rule, rp, line, detail):
        findings.append({'level': level, 'rule': rule, 'file': rp,
                         'line': line, 'detail': detail})

    for base, dirs, files in os.walk(ROOT):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for name in files:
            path = os.path.join(base, name)
            rp = rel(path)
            ext = os.path.splitext(name)[1].lower()
            file_count += 1
            try:
                hashes[rp] = sha256(path)
            except OSError:
                pass

            if ext in IMG_EXT or ext in {'.otf', '.eot', '.ttf', '.woff', '.woff2'}:
                # 图片 / 字体尾部夹带：读末尾 4KB，找可执行文本标记
                try:
                    with open(path, 'rb') as f:
                        f.seek(0, os.SEEK_END)
                        size = f.tell()
                        f.seek(max(0, size - 4096))
                        tail = f.read().decode('latin-1', 'replace')
                    for marker in EXEC_MARKERS.finditer(tail):
                        add('HIGH', 'embedded-code-in-binary', rp, 0,
                            '文件尾部发现可执行标记: %s' % marker.group(0)[:40])
                        break
                except OSError:
                    pass
                continue

            if ext not in TEXT_EXT and not name.startswith('.'):
                try:
                    with open(path, 'rb') as f:
                        head = f.read(8192).decode('latin-1', 'replace')
                    if PHP_TAG.search(head):
                        add('HIGH', 'php-tag-in-non-php', rp, 0, '非 PHP 文件含 PHP 起始标签')
                except OSError:
                    pass
                continue

            try:
                text = open(path, 'r', encoding='utf-8', errors='replace').read()
            except OSError:
                continue
            lines = text.split('\n')

            for n, line in enumerate(lines, 1):
                # 1. 非 PHP 文件混入 PHP 标签
                if ext != '.php' and PHP_TAG.search(line):
                    add('HIGH', 'php-tag-in-non-php', rp, n, line.strip()[:120])

                # 2. 外部 URL 归类：只有真正写进 HTML 属性的才会让访客发起请求
                in_output = bool(OUTPUT_URL_ATTR.search(line))
                for m in URL_RE.finditer(line):
                    url = m.group(0).rstrip('.,;)]}')
                    h = host_of(url)
                    if not h:
                        continue
                    runtime = in_output and (
                        ext in ('.php', '.html', '.htm')
                        or rp in ('js/main.js', 'js/comment.js'))
                    kind = 'runtime' if runtime else 'doc/vendor'
                    urls.append({'file': rp, 'line': n, 'url': url,
                                 'host': h, 'kind': kind})
                    if runtime and h not in RUNTIME_ALLOWED_HOSTS and h not in DOC_HOSTS:
                        add('MEDIUM', 'unreviewed-runtime-host', rp, n,
                            '运行时外链域名未经白名单: %s' % h)

                # 3. 危险 PHP 调用（排除纯注释行与第三方库）
                if ext == '.php' and not is_vendor(rp):
                    stripped = line.strip()
                    if stripped.startswith('*') or stripped.startswith('//') or stripped.startswith('/*'):
                        continue
                    for m in DANGEROUS_PHP.finditer(line):
                        add('HIGH', 'dangerous-php-call', rp, n,
                            '%s() — 需人工确认' % m.group(1))

                # 4. 隐藏的固定长指纹
                if ext in ('.php', '.html', '.htm') and not is_vendor(rp):
                    for m in HEX_TOKEN.finditer(line):
                        add('MEDIUM', 'hidden-hex-fingerprint', rp, n,
                            '输出固定十六进制标记 %s…' % m.group(0)[:12])

                # 5. target=_blank 缺 noopener（忽略 HTML 注释与代码注释行）
                comment_line = ('<!--' in line or line.lstrip().startswith('*')
                                or line.lstrip().startswith('//'))
                for m in BLANK_TARGET.finditer(line):
                    if not comment_line and 'noopener' not in m.group(0).lower():
                        add('LOW', 'blank-target-without-noopener', rp, n,
                            m.group(0)[:120])

                # 6. 管理员自定义 URL 字段直出 href/src，未做协议白名单
                if ext == '.php' and not comment_line:
                    if RAW_OPTION_ATTR.search(line) and CUSTOM_OPTION_FIELD.search(line) \
                            and 'duxSafeUrl' not in line:
                        add('MEDIUM', 'raw-option-in-url-attr', rp, n,
                            'href/src 直接输出自定义后台配置，未做 http(s) 白名单')
                    # 推荐位链接同理：只转义不等于只放行安全协议
                    if 'href="' in line and 'duxEsc($sad1[' in line and 'duxSafeUrl' not in line:
                        add('MEDIUM', 'unsafe-href-protocol', rp, n,
                            '推荐位 href 仅做 HTML 转义，javascript:/data: 仍可注入')

    # 汇总
    levels = {'HIGH': 0, 'MEDIUM': 0, 'LOW': 0}
    for f in findings:
        levels[f['level']] = levels.get(f['level'], 0) + 1
    runtime_hosts = sorted({u['host'] for u in urls if u['kind'] == 'runtime'})
    all_hosts = sorted({u['host'] for u in urls})

    report = {
        'root': ROOT.replace(os.sep, '/'),
        'files_scanned': file_count,
        'levels': levels,
        'runtime_hosts': runtime_hosts,
        'all_hosts': all_hosts,
        'findings': findings,
        'urls': urls,
        'sha256': hashes,
    }

    print('=' * 68)
    print('DUX 主题只读安全审计  |  扫描文件 %d 个' % file_count)
    print('=' * 68)
    print('HIGH %d   MEDIUM %d   LOW %d' % (levels['HIGH'], levels['MEDIUM'], levels['LOW']))
    print('运行时外链域名: %s' % (', '.join(runtime_hosts) or '（无）'))
    print('-' * 68)
    for f in findings:
        print('[%-6s] %-30s %s:%s  %s' % (
            f['level'], f['rule'], f['file'], f['line'], f['detail']))
    if not findings:
        print('未发现可疑项。')
    print('-' * 68)

    if json_out:
        with open(json_out, 'w', encoding='utf-8') as f:
            json.dump(report, f, ensure_ascii=False, indent=2)
        print('结构化报告: %s' % json_out)

    return 1 if strict and levels['HIGH'] else 0


if __name__ == '__main__':
    sys.exit(main())
