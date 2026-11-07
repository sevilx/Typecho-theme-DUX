<?php if(!defined( '__TYPECHO_ROOT_DIR__'))exit;?>
<?php
$duxElegance = duxEleganceEnabled();
$duxMode     = $duxElegance ? duxThemeMode() : 'disable';
$duxDesc     = trim((string) $this->options->description);
$duxThemeUrl = rtrim((string) $this->options->themeUrl, '/');
$duxLogoTags = $this->is('index') ? 'h1' : 'div';
?>
<!DOCTYPE HTML>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <!-- 允许用户缩放：原版 user-scalable=0 / maximum-scale=1 会让视力障碍用户无法放大阅读 -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="dux-theme-mode" content="<?php echo duxEsc($duxMode); ?>">
    <meta name="apple-mobile-web-app-title" content="<?php $this->options->title();?>">
    <meta http-equiv="Cache-Control" content="no-siteapp">
    <meta http-equiv="Cache-Control" content="no-transform">
<?php
// 基线 CSP：必须位于所有 script/link 之前。meta 版不支持 frame-ancestors / report-uri，
// 服务器侧响应头仍应单独配置。内联统计代码与主题首屏脚本决定了暂时保留 unsafe-inline。
$duxCspOn = (string) ($this->options->contentSecurityPolicy ?? '') !== 'disable';
if ($duxCspOn):
    // 站长可补充自有脚本来源；严格校验防止把 ; / 空格 / 路径注入成新的 CSP 指令。
    $duxCspExtra = array();
    foreach (preg_split('/\s+/', trim((string) ($this->options->cspExtraSources ?? ''))) as $duxCspSrc) {
        if ($duxCspSrc !== '' && preg_match('~^https://[A-Za-z0-9.-]+$~', $duxCspSrc)) {
            $duxCspExtra[] = $duxCspSrc;
        }
    }
    $duxCspScripts = array(
        "'self'", "'unsafe-inline'", "'unsafe-eval'",
        'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://lib.baomitu.com',
        'https://static.geetest.com', 'https://hcaptcha.com',
        'https://challenges.cloudflare.com', 'https://recaptcha.net', 'https://www.recaptcha.net',
        'https://www.google-analytics.com', 'https://www.googletagmanager.com', 'https://hm.baidu.com',
    );
    $duxCspStyles = array(
        "'self'", "'unsafe-inline'", 'https://fonts.googleapis.com',
        'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com',
    );
    $duxCsp = array(
        "default-src 'self'",
        'script-src ' . implode(' ', array_merge($duxCspScripts, $duxCspExtra)),
        'style-src ' . implode(' ', $duxCspStyles),
        "img-src 'self' data: blob: https:",
        "font-src 'self' data: https://fonts.gstatic.com",
        "connect-src 'self' https:",
        "media-src 'self' data: blob: https:",
        "frame-src 'self' https:",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    );
?>
    <meta http-equiv="Content-Security-Policy" content="<?php echo duxEsc(implode('; ', $duxCsp)); ?>">
<?php endif; ?>
    <script>
        /* 首屏同步写入主题，避免深色模式闪白；脚本失败时 CSS 仍会跟随系统偏好 */
        (function () {
            var m = document.querySelector('meta[name="dux-theme-mode"]');
            var mode = (m && m.content) || 'auto', saved = null;
            try { saved = localStorage.getItem('dux-theme'); } catch (e) {}
            var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            // 增强关闭 / 深色策略 disable 时完全不写 data-theme，回到原版外观
            if (mode === 'disable') { return; }
            var t = saved || (mode === 'dark' || (mode === 'auto' && dark) ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.style.colorScheme = t;
            var c = document.querySelector('meta[name="theme-color"]');
            if (c) { c.setAttribute('content', t === 'dark' ? '#11151c' : '#ffffff'); }
        })();
    </script>
    <title><?php
        if ($this->is('index')) {
            echo duxEsc($this->options->title);
            if ($duxDesc !== '') { echo ' - ' . duxEsc($duxDesc); }
        } else {
            $this->archiveTitle(array(
                'category' => _t('%s'), 'search' => _t('搜索 %s'),
                'tag' => _t('标签 %s'), 'author' => _t('作者 %s')
            ), '', ' - ');
            echo duxEsc($this->options->title);
        }
    ?></title>
<?php if ($this->options->DnsPrefetch == "able"): ?>
    <meta http-equiv="x-dns-prefetch-control" content="on">
<?php $duxCdn = duxSafeUrl($this->options->cdn_add); if ($duxCdn !== ''): ?>
    <link rel="dns-prefetch" href="<?php echo duxEsc($duxCdn); ?>" />
<?php endif; ?>
<?php endif; ?>
    <link rel="shortcut icon" href="<?php $duxIco = duxSafeUrl($this->options->icoUrl); echo $duxIco !== '' ? duxEsc($duxIco) : duxEsc(duxAssetUrl('img/favicon.ico')); ?>">
    <!-- 关键字体预加载：URL 必须与 font-awesome.min.css 里的 ?v=4.7.0 完全一致才能命中缓存 -->
    <link rel="preload" as="font" type="font/woff2" crossorigin href="<?php echo duxEsc($duxThemeUrl . '/font-awesome/fonts/fontawesome-webfont.woff2?v=4.7.0'); ?>">

    <link rel="stylesheet" href="<?php echo duxEsc(duxAssetUrl('css/bootstrap.min.css')); ?>" type="text/css" media="all" />
    <link rel="stylesheet" href="<?php echo duxEsc(duxAssetUrl('font-awesome/css/font-awesome.min.css')); ?>" type="text/css" media="all" />
<?php if (defined('DUX_404_PAGE')): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
    <link rel="stylesheet" href="<?php echo duxEsc(duxAssetUrl('css/main.css')); ?>" type="text/css" media="all" />
<?php if ($duxElegance): ?>
    <link rel="stylesheet" href="<?php echo duxEsc(duxAssetUrl('css/dux-elegance.css')); ?>" type="text/css" media="all" />
<?php endif; ?>

    <style>
<?php if (!$duxElegance): ?>
        /* 外观增强关闭：增强层样式表不加载，补一份不依赖任何 CSS 变量的最小 a11y 规则，
           保证屏幕阅读器文本与跳转主内容链接不会变成可见元素。 */
        .dux-sr-only { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        .dux-skip-link { position: fixed; top: -60px; left: 16px; z-index: 2000; padding: 10px 18px; background: #1e88e5; color: #fff !important; border-radius: 0 0 8px 8px; box-shadow: 0 2px 8px rgba(0,0,0,.25); text-decoration: none; transition: top .2s ease; }
        .dux-skip-link:focus { top: 0; outline: 2px solid #1e88e5; outline-offset: 2px; }
<?php endif; ?>
<?php if ($duxElegance && duxAccentCss() !== ''): ?>
        <?php echo duxAccentCss(); ?>
<?php endif; ?>
<?php if ($this->options->indexpic == 'disable'): ?>
        .excerpt {padding-left: 20px !important;}
        .excerpt-minic {padding-left: 20px !important;}
<?php endif; ?>
<?php if ($this->options->customcss): ?>
        <?php echo duxSafeCss($this->options->customcss); ?>
<?php endif; ?>
    </style>

    <!-- defer：不再阻塞首屏渲染；footer 里的 bootstrap / loader 同样是 defer，执行顺序保持不变 -->
    <script defer type="text/javascript" src="<?php echo duxEsc(duxAssetUrl('css/jquery.min.js')); ?>"></script>

    <?php
    /*
     * 只屏蔽 generator / template / wlw 这类无用或暴露版本的信息，
     * 其余交给 Typecho 1.3 自己输出：description、keywords、canonical、
     * og/twitter、RSS 订阅发现链接，以及评论回复与 antiSpam 脚本
     * （1.3.1 的评论表单依赖它们注入 "_" 隐藏域）。
     */
    $this->header('generator=&template=&wlw=');
    ?>
</head>

<?php
// body class 统一用数组生成：DUX 的评论增强、搜索高亮、浮动工具栏都依赖这些 class
$bodyClasses = array();
if ($this->is('index')) {
    $bodyClasses[] = 'home';
} elseif ($this->is('post')) {
    $bodyClasses[] = 'single';
} elseif ($this->is('page')) {
    $bodyClasses[] = 'page';
} elseif ($this->is('archive')) {
    $bodyClasses[] = 'archive';
}
if ($this->is('search')) {
    $bodyClasses[] = 'search-results';
}
// 仅评论开放的单篇内容才加载评论模块，避免覆盖 Typecho 1.3.1 官方回复脚本
if (($this->is('post') || $this->is('page')) && $this->allow('comment')) {
    $bodyClasses[] = 'comment-open';
}
if ($this->user->hasLogin()) {
    $bodyClasses[] = 'logged-in';
}
if ($duxElegance) {
    $bodyClasses[] = 'dux-elegance';
}
?>
<body class="<?php echo duxEsc(implode(' ', $bodyClasses)); ?>">
    <a class="dux-skip-link" href="#dux-main">跳到主内容</a>
    <header class="header">
        <div class="container">
            <<?php echo $duxLogoTags; ?> class="logo">
<?php if (!empty($this->options->logoUrl)): ?>
                <a href="<?php $this->options->siteUrl(); ?>"><img src="<?php $duxLogo = duxSafeUrl($this->options->logoUrl); echo duxEsc($duxLogo !== '' ? $duxLogo : duxAssetUrl('img/logo.png')); ?>" width="137" height="32" alt="<?php $this->options->title();?>" /></a>
<?php else: ?>
                <a href="<?php $this->options->siteUrl(); ?>"><img src="<?php echo duxEsc(duxAssetUrl('img/logo.png')); ?>" width="137" height="32" alt="<?php $this->options->title();?>" /><?php $this->options->title();?></a>
<?php endif; ?>
            </<?php echo $duxLogoTags; ?>>
            <div class="brand"><?php if($this->options->logotext && !empty($this->options->logotext) ): ?><?php echo duxSafeHtml($this->options->logotext); ?><?php endif; ?></div>
            <ul class="site-nav site-navbar" role="navigation" aria-label="主导航">
                <!--<li><a href="<?php $this->options->siteUrl(); ?>"><i class="fa fa-home"></i>首页</a></li>-->

<?php if ($this->options->categorymenu == 'able'): ?>
                <li class="nav-has-sub">
                    <a href="#" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-folder-open"></i>文章分类</a>
                    <ul class="sub-menu" aria-label="文章分类">
<?php $this->widget('Widget_Metas_Category_List')->to($cats); while($cats->next()): ?>
                <li><a href="<?php $cats->permalink()?>"><?php $cats->name()?></a></li>
<?php endwhile; ?>
                    </ul>
                </li>
<?php else: ?>
<?php $this->widget('Widget_Metas_Category_List')->to($cats); $i=0; while($cats->next()): ?>
                <li><a href="<?php $cats->permalink()?>"><?php echo fa_ico(1,$i); ?> <?php $cats->name()?></a></li>
<?php $i++; endwhile; ?>
<?php endif; ?>


<?php if ($this->options->pagemenu == 'able'): ?>
                <li class="nav-has-sub">
                    <a href="#" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-file-text-o"></i>独立页面</a>
                    <ul class="sub-menu" aria-label="独立页面">
<?php $this->widget('Widget_Contents_Page_List')->to($pages); while($pages->next()): ?>
                        <li <?php if($this->is('page', $pages->slug)): ?> class="current-menu-item"<?php endif; ?>><a href="<?php $pages->permalink(); ?>"><?php $pages->title(); ?></a></li>
<?php endwhile; ?>
                    </ul>
                </li>
<?php else: ?>
<?php $this->widget('Widget_Contents_Page_List')->to($pages); $i=0; while($pages->next()): ?>
                <li><a href="<?php $pages->permalink(); ?>"><?php echo fa_ico(2,$i); ?> <?php $pages->title(); ?></a></li>
<?php $i++; endwhile; ?>
<?php endif; ?>

<?php if ($duxMode !== 'disable'): ?>
                <li class="navto-theme">
                    <button type="button" class="dux-theme-toggle dux-theme-toggle-global"
                            aria-pressed="false" aria-label="切换到深色模式" title="切换到深色模式">
                        <i class="fa fa-moon-o" aria-hidden="true"></i>
                    </button>
                </li>
<?php endif; ?>
                <li class="navto-search">
                    <a href="#" rel="nofollow" class="search-show active" role="button"
                       aria-label="打开搜索" aria-expanded="false"><i class="fa fa-search" aria-hidden="true"></i></a>
                </li>
            </ul>
            <button type="button" class="m-icon-nav" aria-label="打开导航菜单" aria-expanded="false"><i class="fa fa-bars" aria-hidden="true"></i></button>
        </div>
    </header>
    <div class="site-search">
        <div class="container">
            <form method="get" class="site-search-form" action="<?php $this->options->siteUrl(); ?>" role="search">
                <label class="dux-sr-only" for="dux-search-input">搜索站内文章</label>
                <input id="dux-search-input" class="search-input" name="s" type="search" placeholder="输入关键字" value="<?php $s = $this->request->get('s'); echo duxEsc(is_scalar($s) ? $s : ''); ?>" aria-label="搜索站内文章">
                <button class="search-btn" type="submit" aria-label="提交搜索"><i class="fa fa-search" aria-hidden="true"></i></button>
            </form>
        </div>
    </div>
    <!--header-->
