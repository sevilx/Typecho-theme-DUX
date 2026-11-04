<?php if(!defined( '__TYPECHO_ROOT_DIR__'))exit;?>
<!DOCTYPE HTML>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
    <meta name="auth" content="a1a3170911eaa82c07fccbcbdf61f6bb"/>
    <meta charset="<?php $this->options->charset(); ?>">
<?php if ($this->options->DnsPrefetch == "able"): ?>
    <meta http-equiv="x-dns-prefetch-control" content="on">
<?php if ($this->options->cdn_add): ?>
    <link rel="dns-prefetch" href="<?php $this->options->cdn_add(); ?>" />
<?php endif; ?>
<?php endif; ?>
    <title><?php $this->archiveTitle(array('category'=>_t(' %s '),'search'=>_t(' %s '),'tag'=>_t(' %s '),'author'=>_t(' %s ')),'',' - ');?> <?php $this->options->title();?> - <?php $this->options->description() ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="shortcut icon" href="<?php if ($this->options->icoUrl): ?><?php $this->options->icoUrl(); ?><?php else: ?><?php $this->options->themeUrl('img/favicon.ico'); ?><?php endif; ?>">
    <meta name="apple-mobile-web-app-title" content="<?php $this->options->title();?>">
    <meta http-equiv="Cache-Control" content="no-siteapp">
    <meta http-equiv="Cache-Control" content="no-transform">

    <link rel="stylesheet" href="<?php $this->options->themeUrl('css/bootstrap.min.css'); ?>" type="text/css" media="all" />
    <link rel="stylesheet" href="<?php $this->options->themeUrl('font-awesome/css/font-awesome.min.css'); ?>" type="text/css" media="all" />
    <link rel="stylesheet" href="<?php $this->options->themeUrl('css/main.css'); ?>" type="text/css" media="all" />

    <style>
<?php if ($this->options->indexpic == 'disable'): ?>
        .excerpt {padding-left: 20px !important;}
        .excerpt-minic {padding-left: 20px !important;}
<?php endif; ?><?php if ($this->options->customcss): ?>
        <?php $this->options->customcss(); ?>
<?php endif; ?>
    </style>

    <script type="text/javascript" src="<?php $this->options->themeUrl('css/jquery.min.js'); ?>"></script>
    <!--加载进度条
    <link href="<?php $this->options->themeUrl('css/pace-theme-flash.css'); ?>" rel="stylesheet" />
    <script> paceOptions = { elements: {selectors: ['#footer']}};</script>
    <script src="<?php $this->options->themeUrl('css/pace.min.js'); ?>"></script>
    <![if lt IE 9]><script src="<?php $this->options->themeUrl('css/html5shiv.min.js'); ?>"></script><![endif]-->

    <?php
    // 保留 commentReply / antiSpam：Typecho 1.3.1 依赖它们输出官方的
    // TypechoComment 回复脚本与评论表单安全令牌，主题不再自行实现。
    $this->header('generator=&template=&pingback=&xmlrpc=&wlw=&rss1=&rss2=&atom=');
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
?>
<body class="<?php echo duxEsc(implode(' ', $bodyClasses)); ?>">
    <header class="header">
        <div class="container">
            <h1 class="logo">
<?php if (!empty($this->options->logoUrl)): ?>
                <a href="<?php $this->options->siteUrl(); ?>"><img src="<?php $this->options->logoUrl(); ?>" alt="<?php $this->options->title();?>" /></a>
<?php else: ?>
                <a href="<?php $this->options->siteUrl(); ?>"><img src="<?php $this->options->themeUrl('img/logo.png'); ?>" alt="<?php $this->options->title();?>" /><?php $this->options->title();?></a>
<?php endif; ?>
            </h1>
            <div class="brand"><?php if($this->options->logotext && !empty($this->options->logotext) ): ?><?php $this->options->logotext(); ?><?php endif; ?></div>
            <ul class="site-nav site-navbar">
                <!--<li><a href="<?php $this->options->siteUrl(); ?>"><i class="fa fa-home"></i>首页</a></li>-->

<?php if ($this->options->categorymenu == 'able'): ?>
                <li>
                    <a><i class="fa fa-folder-open"></i>文章分类</a>
                    <ul class="sub-menu">
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
                <li>
                    <a><i class="fa fa-file-text-o"></i>独立页面</a>
                    <ul class="sub-menu">
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

                <li class="navto-search">
                    <a href="javascript:;" class="search-show active"><i class="fa fa-search"></i></a>
                </li>
            </ul>
            <i class="fa fa-bars m-icon-nav"></i>
        </div>
    </header>
    <div class="site-search">
        <div class="container">
            <form method="get" class="site-search-form" action="<?php $this->options->siteUrl(); ?>">
                <input class="search-input" name="s" type="text" placeholder="输入关键字" value="<?php $s = $this->request->get('s'); echo duxEsc(is_scalar($s) ? $s : ''); ?>">
                <button class="search-btn" type="submit"><i class="fa fa-search"></i></button>
            </form>
        </div>
    </div>
    <!--header-->
