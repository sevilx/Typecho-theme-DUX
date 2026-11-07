<?php
/**
 * 分类存档
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
$this->widget('Widget_Metas_Category_List')->to($categories);

if ($categories->have()) {
    // 流式输出：分类数 × 文章数不再同时驻留内存
    echo '<article class="archives">';

    while ($categories->next()) {
        $mid = (int) $categories->mid;
        echo '<div class="item"><h3><a href="' . duxEsc($categories->permalink)
            . '"><span style="color:#F15A23">' . duxEsc($categories->name) . '</span></a></h3>'
            . '<ul class="archives-list">';

        // Typecho 1.3.1 的 Widget::widget() 第三参数会 new Config($request)，
        // Config 同时支持 "mid=N" 字符串与数组；这里用数组并用 mid 生成唯一别名，
        // 避免同名分类共用对象池导致后面所有分类都输出第一组文章。
        try {
            $this->widget(
                'Widget_Archive@dux-category-' . $mid,
                'type=category&pageSize=10000',
                array('mid' => $mid)
            )->to($categoryPosts);

            while ($categoryPosts->next()) {
                echo '<li><a href="' . duxEsc($categoryPosts->permalink) . '">'
                    . duxEsc($categoryPosts->title) . '</a> <span class="text-muted">'
                    . (int) $categoryPosts->commentsNum . '评论</span></li>';
            }
        } catch (\Throwable $e) {
            // 单个分类查询异常（分类被删除、分页越界等）不应让整个页面 500
        }

        echo '</ul></div>';
    }

    echo '</article>';
} else {
    echo '<article class="archives">无分类</article>';
}
?>
<?php if ($this->allow('comment')) { $this->need('comments.php'); } ?>
        </div>
    </div>
<?php $this->need('footer.php'); ?>
