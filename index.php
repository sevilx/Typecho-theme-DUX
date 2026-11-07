<?php
/**
 * DUX for Typecho · Ben Edition
 *
 * 由 Ben (sevilx) 二次开发并维护的独立发行版，面向 Typecho 1.3.x。
 * 与原移植版 https://github.com/hiCasper/Typecho-theme-DUX 不是同一分支：
 * 本版重构了兼容层、外观增强层与安全策略，且版本号跟随所运行的 Typecho。
 *
 * @package DUX for Typecho (Ben Edition)
 * @author  Ben
 * @version 跟随 Typecho\Common::VERSION（见 functions.php 的 DUX_VERSION）
 * @link    https://github.com/sevilx/Typecho-theme-DUX
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');?>

    <section class="container">
    <div class="content-wrap">
        <div class="content" id="dux-main" tabindex="-1">
<?php $duxFirstPage = !method_exists($this, 'getCurrentPage') || (int) $this->getCurrentPage() <= 1; ?>
<?php if ($this->is('index') && $duxFirstPage): ?>
            <!--幻灯片开始-->
<?php if ($this->options->Slider == 'SliderTrue'): ?>
                <?php slout(); ?>
<?php endif; ?>
            <!--幻灯片结束-->
            <!--置顶文章-->
            <?php hotpost(); ?>
<?php endif; ?>

            <div class="title">
                <h3>最新发布</h3>
                <?php if($this->options->smallbanner && !empty($this->options->smallbanner) ): ?>
                <div class="more"><?php echo duxSafeHtml($this->options->smallbanner); ?></div><?php endif; ?>
            </div>
<?php if($this->have()):?>
<?php $duxRow = 0; while($this->next()): $duxRow++; ?>
            <article class="excerpt">
<?php if ($this->options->indexpic == 'able'): ?>
                <?php $thumb = duxEsc(showThumb($this)); ?>
                <a class="focus" href="<?php $this->permalink() ?>"><img src="<?php echo $thumb; ?>" class="thumb" width="220" height="150" loading="<?php echo $duxRow <= 2 ? 'eager' : 'lazy'; ?>"<?php echo $duxRow === 1 ? ' fetchpriority="high"' : ''; ?> decoding="async" alt="<?php echo duxEsc($this->title); ?>"></a>
<?php endif; ?>
                <header>
                    <a class="cat"><?php $this->category(',',false); ?><i></i></a>
                    <h2><a href="<?php $this->permalink() ?>" title="<?php echo duxEsc($this->title); ?>-<?php echo duxEsc($this->options->title); ?>"><?php $this->title() ?></a></h2>
                </header>
                <p class="meta">
                    <time datetime="<?php $this->date('c'); ?>"><i class="fa fa-clock-o" aria-hidden="true"></i><?php $this->date('Y-m-d'); ?></time>
                    <span class="author"><i class="fa fa-user" aria-hidden="true"></i><?php $this->author(); ?></span>
                    <span class="pv"><i class="fa fa-eye" aria-hidden="true"></i>阅读(<?php get_post_view($this) ?>)</span>
                    <a class="pc" href="<?php $this->permalink() ?>#comments"><i class="fa fa-comments-o" aria-hidden="true"></i>评论(<?php $this->commentsNum('0', '1', '%d'); ?>)</a>
                </p>
                <p class="note"><?php $this->excerpt(150, '...'); ?></p>
            </article>
<?php endwhile; ?>
<?php endif; ?>
            <div class="pagination">
                <?php $this->pageNav('上一页', '下一页', 3, '...', array('wrapTag' => 'ul', 'wrapClass' => 'page-navigator', 'itemTag' => 'li', 'textTag' => 'span', 'currentClass' => 'active', 'prevClass' => 'prev-page', 'nextClass' => 'next-page')); ?>
            </div>
        </div>
    </div>
<?php $this->need('sidebar.php'); ?>
    </section>

<?php $this->need('footer.php'); ?>
