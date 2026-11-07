<?php
/**
 * 页面存档
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>
    <div class="container <?php if ($this->options->pagesidebar == "able"): ?>container-page<?php else: ?>container-no-sidebar<?php endif; ?>">
<?php if ($this->options->pagesidebar == "able"): ?>
        <div class="pageside">
            <div class="pagemenus">
                <ul class="pagemenu">
<?php $this->widget('Widget_Contents_Page_List')->to($cpages); ?>
<?php $currentPermalink = (string) $this->permalink; ?>
<?php while ($cpages->next()): ?>
                    <li<?php if ((string) $cpages->permalink === $currentPermalink): ?> class="active"<?php endif; ?>><a href="<?php $cpages->permalink(); ?>"><?php $cpages->title(); ?></a></li>
<?php endwhile; ?>
                </ul>
            </div>
        </div>
<?php endif; ?>
        <div class="content" id="dux-main" tabindex="-1">
            <header class="article-header">
                <h1 class="article-title"><a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a></h1>
            </header>
            <article class="article-content"></article>
<?php
$this->widget('Widget_Contents_Post_Recent', 'pageSize=10000')->to($archives);

$month = '';
$opened = false;
// 直接流式输出，不再把全站文章拼成一个大字符串，内存峰值与文章数解耦
echo '<article class="archives">';

while ($archives->next()):
    $yearTmp = date('Y', $archives->created);
    $monthTmp = date('m', $archives->created);
    $key = $yearTmp . '-' . $monthTmp;

    if ($key !== $month) {
        // 只有确实打开过月份分组才闭合，避免空站点产生多余的 </ul></div>
        if ($opened) {
            echo '</ul></div>';
        }
        $month = $key;
        echo '<div class="item"><h3>' . $yearTmp . '年' . $monthTmp
            . ' 月</h3><ul class="archives-list">';
        $opened = true;
    }

    echo '<li><time>' . date('d日: ', $archives->created) . '</time><a href="'
        . duxEsc($archives->permalink) . '">' . duxEsc($archives->title)
        . '</a> <span class="text-muted">' . (int) $archives->commentsNum . '评论</span></li>';
endwhile;

if ($opened) {
    echo '</ul></div>';
}
echo '</article>';
?>
        </div>
<?php $this->need('footer.php'); ?>
