/*
 * DUX Elegance —— 无依赖交互增强
 *
 * 与 RequireJS / jQuery 完全解耦：本文件用 defer 独立加载，
 * 即使 loader.js 或 main.js 加载失败，深色模式、阅读进度、代码复制仍可用。
 * 所有 DOM 操作都做存在性判断，异常只影响增强项，不影响主题原功能。
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var metaMode = document.querySelector('meta[name="dux-theme-mode"]');
    var mode = (metaMode && metaMode.content) || 'auto';
    var STORAGE_KEY = 'dux-theme';

    function readSaved() {
        try { return window.localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
    }
    function saveTheme(value) {
        try { window.localStorage.setItem(STORAGE_KEY, value); } catch (e) { /* 隐私模式忽略 */ }
    }
    function metaColor(theme) {
        var tag = document.querySelector('meta[name="theme-color"]');
        if (tag) { tag.setAttribute('content', theme === 'dark' ? '#11151c' : '#ffffff'); }
    }

    /* ------------------------------------------------------------------
     * 1. 深色 / 浅色切换
     * ---------------------------------------------------------------- */
    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme;
        metaColor(theme);
        var label = theme === 'dark' ? '切换到浅色模式' : '切换到深色模式';
        Array.prototype.forEach.call(document.querySelectorAll('.dux-theme-toggle'), function (btn) {
            var icon = btn.querySelector('.fa');
            if (icon) {
                icon.className = theme === 'dark' ? 'fa fa-sun-o' : 'fa fa-moon-o';
            }
            btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            btn.setAttribute('title', label);
            btn.setAttribute('aria-label', label);
        });
        if (typeof window.CustomEvent === 'function') {
            document.dispatchEvent(new CustomEvent('dux:themechange', { detail: { theme: theme } }));
        }
    }

    // header 里的首屏脚本已经写过 data-theme，这里只同步按钮与 meta
    applyTheme(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

    function onToggleClick(e) {
        e.preventDefault();
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        saveTheme(next);
        applyTheme(next);
    }

    // WeakSet 不会被 main.js 克隆导航时复制到新节点，克隆出来的按钮也能重新绑定
    var boundToggles = (typeof WeakSet === 'function') ? new WeakSet() : null;
    function bindToggles() {
        var buttons = document.querySelectorAll('.dux-theme-toggle');
        Array.prototype.forEach.call(buttons, function (btn) {
            if (boundToggles) {
                if (boundToggles.has(btn)) { return; }
                boundToggles.add(btn);
            } else {
                if (btn.getAttribute('data-dux-bound')) { return; }
                btn.setAttribute('data-dux-bound', '1');
            }
            btn.addEventListener('click', onToggleClick);
        });
    }
    bindToggles();
    // 移动导航是 main.js 克隆出来的，晚于本文件执行，需要再补绑一次
    document.addEventListener('DOMContentLoaded', bindToggles);
    window.addEventListener('load', bindToggles);

    // 跟随系统变化（只在用户没有手动选过时生效）
    if (mode === 'auto' && window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function (event) {
            if (!readSaved()) { applyTheme(event.matches ? 'dark' : 'light'); }
        };
        if (mq.addEventListener) { mq.addEventListener('change', onChange); }
        else if (mq.addListener) { mq.addListener(onChange); }
    }

    /* ------------------------------------------------------------------
     * 2. 阅读进度条
     * ---------------------------------------------------------------- */
    function initProgress() {
        if (!document.querySelector('.article-content')) { return; }
        var bar = document.createElement('div');
        bar.id = 'dux-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);

        var raf = window.requestAnimationFrame
            ? window.requestAnimationFrame.bind(window)
            : function (cb) { return window.setTimeout(cb, 16); };
        var ticking = false;
        var update = function () {
            var doc = document.documentElement;
            var scrollable = (doc.scrollHeight || 0) - (window.innerHeight || 0);
            var ratio = scrollable > 0
                ? Math.min(1, Math.max(0, (window.pageYOffset || doc.scrollTop || 0) / scrollable))
                : 0;
            bar.style.transform = 'scaleX(' + ratio.toFixed(4) + ')';
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; raf(update); }
        }, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    }

    /* ------------------------------------------------------------------
     * 3. 原生懒加载兜底 + 图片加载失败占位
     * ---------------------------------------------------------------- */
    function initImages() {
        var nativeLazy = 'loading' in HTMLImageElement.prototype;
        var images = document.querySelectorAll('.article-content img, .excerpt img');

        Array.prototype.forEach.call(images, function (img) {
            if (!img.getAttribute('loading')) { img.setAttribute('loading', 'lazy'); }
            if (!img.getAttribute('decoding')) { img.setAttribute('decoding', 'async'); }
        });

        if (!nativeLazy && 'IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) { return; }
                    var img = entry.target;
                    var src = img.getAttribute('data-src');
                    if (src) { img.src = src; img.removeAttribute('data-src'); }
                    observer.unobserve(img);
                });
            }, { rootMargin: '300px 0px' });
            Array.prototype.forEach.call(images, function (img) {
                if (img.getAttribute('data-src')) { observer.observe(img); }
            });
        }

        // 缩略图 404 时用主题内置占位图，避免首屏破图
        document.addEventListener('error', function (e) {
            var el = e.target;
            if (!el || el.tagName !== 'IMG' || !el.classList.contains('thumb')) { return; }
            if (el.getAttribute('data-dux-fallback')) { return; }
            el.setAttribute('data-dux-fallback', '1');
            var base = (window.jsui && window.jsui.uri) || '';
            el.src = base + '/img/thumbnail.png';
        }, true);
    }

    /* ------------------------------------------------------------------
     * 4. 代码块一键复制
     * ---------------------------------------------------------------- */
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
            } catch (err) { reject(err); }
            document.body.removeChild(area);
        });
    }

    function initCodeCopy() {
        var blocks = document.querySelectorAll('.article-content pre');
        Array.prototype.forEach.call(blocks, function (pre) {
            var wrapper = document.createElement('div');
            wrapper.className = 'dux-code-wrap';
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(pre);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dux-copy';
            btn.textContent = '复制';
            btn.setAttribute('aria-label', '复制代码');
            wrapper.appendChild(btn);

            btn.addEventListener('click', function () {
                var text = pre.innerText || pre.textContent || '';
                copyText(text).then(function () {
                    btn.textContent = '已复制';
                    btn.classList.add('is-done');
                }).catch(function () {
                    btn.textContent = '复制失败';
                });
                window.setTimeout(function () {
                    btn.textContent = '复制';
                    btn.classList.remove('is-done');
                }, 1800);
            });
        });
    }

    /* ------------------------------------------------------------------
     * 5. 外链安全兜底 / 移动菜单状态
     * ---------------------------------------------------------------- */
    function initLinks() {
        // skip link 的落点：模板已显式输出 id，这里给自定义页面 / 404 兜底
        var main = document.querySelector('.content') || document.querySelector('section.container');
        if (main && !main.id) {
            main.id = 'dux-main';
            main.setAttribute('tabindex', '-1');
        }

        // 折叠菜单的触发项是 href="#" 的链接，必须阻止默认跳转并同步无障碍状态
        Array.prototype.forEach.call(document.querySelectorAll('.nav-has-sub > a[href="#"]'), function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                a.setAttribute('aria-expanded', a.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
            });
        });

        var searchToggle = document.querySelector('.search-show');
        if (searchToggle) {
            searchToggle.addEventListener('click', function () {
                window.setTimeout(function () {
                    searchToggle.setAttribute(
                        'aria-expanded',
                        document.body.classList.contains('search-on') ? 'true' : 'false'
                    );
                }, 0);
            });
        }

        Array.prototype.forEach.call(document.querySelectorAll('a[target="_blank"]'), function (a) {
            var rel = ' ' + (a.getAttribute('rel') || '') + ' ';
            var missing = [];
            if (rel.indexOf(' noopener ') < 0) { missing.push('noopener'); }
            if (rel.indexOf(' noreferrer ') < 0) { missing.push('noreferrer'); }
            if (missing.length) { a.setAttribute('rel', (a.getAttribute('rel') || '').trim() + ' ' + missing.join(' ')); }
        });

        // 正文里的站外链接统一 nofollow，避免评论/投稿内容稀释权重
        var host = window.location.host;
        Array.prototype.forEach.call(document.querySelectorAll('.article-content a[href^="http"]'), function (a) {
            if (a.host !== host && !a.getAttribute('rel')) {
                a.setAttribute('rel', 'external nofollow noopener');
            }
        });

        var navToggle = document.querySelector('.m-icon-nav');
        if (navToggle) {
            navToggle.addEventListener('click', function () {
                navToggle.setAttribute('aria-expanded', 'true');
            });
            var mask = document.querySelector('.m-mask');
            if (mask) {
                mask.addEventListener('click', function () {
                    navToggle.setAttribute('aria-expanded', 'false');
                });
            }
        }
    }

    /* ------------------------------------------------------------------
     * 启动（DOM 未就绪也能安全调用：脚本带 defer，通常已在 DOMContentLoaded 前）
     * ---------------------------------------------------------------- */
    function boot() {
        try { initProgress(); } catch (e) {}
        try { initImages(); } catch (e) {}
        try { initCodeCopy(); } catch (e) {}
        try { initLinks(); } catch (e) {}
        try { bindToggles(); } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
