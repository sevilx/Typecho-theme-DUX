<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/**
 * 自定义单条评论输出。
 * 用 function_exists 包裹，避免模板被重复 include 时出现 "Cannot redeclare"。
 */
if (!function_exists('threadedComments')) :
function threadedComments($comments, $options)
{
    // 只放行 http(s)，避免评论者网址里夹带 javascript: / data: 造成 XSS
    $safeUrl = duxSafeUrl($comments->url);
    if ($safeUrl !== '') {
        $author = '<a href="' . duxEsc($safeUrl) . '" target="_blank" rel="external nofollow noopener noreferrer">'
            . duxEsc($comments->author) . '</a>';
    } else {
        $author = duxEsc($comments->author);
    }

    // $options 是 singleCommentOptions，子评论递归时还要用到，切勿覆盖
    $siteOptions = duxOptions();
    $rating = $siteOptions->commentsAvatarRating ?: 'G';
    $avatar = duxAvatarUrl($comments->mail, 50, $rating);
?>
    <li id="<?php $comments->theId(); ?>" class="comment byuser comment-author-admin bypostauthor depth-<?php echo ((int) $comments->levels + 1); ?> comment-body<?php
    if ($comments->levels > 0) {
        echo ' comment-child';
        $comments->levelsAlt(' comment-level-odd', ' comment-level-even');
    } else {
        echo ' comment-parent';
    }
    $comments->alt(' odd', ' even');
    ?>">
        <div class="comt-avatar"><img alt="" class="avatar photo" height="50" width="50" loading="lazy" decoding="async" referrerpolicy="no-referrer" src="<?php echo $avatar; ?>"></div>
        <div class="comt-main" id="div-<?php $comments->theId(); ?>">
            <?php $comments->content(); ?>
            <div class="comt-meta">
                <span class="comt-author"><?php echo $author; ?></span> <?php $comments->date('Y-m-d'); ?>&nbsp;&nbsp;&nbsp;&nbsp;<?php $comments->reply('回复'); ?>
            </div>
        </div>
    <?php if ($comments->children) { ?><ul class="children"><?php $comments->threadedComments(); ?></ul><?php } ?>
    </li>
<?php
}
endif;

$this->comments()->to($comments);
$loggedIn = $this->user->hasLogin();
$rememberAuthor = $this->remember('author', true);
$rememberMail = $this->remember('mail', true);
$hasRemembered = ($rememberAuthor != '' && $rememberMail != '');
$anonymousAvatar = duxAvatarUrl('', 50);
$loggedInAvatar = duxAvatarUrl($this->user->mail, 50);
$rememberedAvatar = duxAvatarUrl($rememberMail, 50);
?>
    <div class="title" id="comments">
        <h3>评论 <?php $this->commentsNum('<small></small>', '<b>1</b>', '<b>%d</b>'); ?></h3>
    </div>
    <div id="<?php $this->respondId(); ?>" class="no_webshot">
<?php if(!$this->allow('comment')){ ?>
        <h3 class="title">
            <strong>文章评论已关闭！</strong>
        </h3>
<?php }else{ ?>
        <form action="<?php $this->commentUrl() ?>" method="post" id="commentform">
            <div class="comt">
                <div class="comt-title">
<?php if($loggedIn): ?>
                    <img alt="" class="avatar photo" height="50" width="50" decoding="async" referrerpolicy="no-referrer" src="<?php echo $loggedInAvatar; ?>">
<?php elseif($hasRemembered): ?>
                    <img alt="" class="avatar photo" height="50" width="50" decoding="async" referrerpolicy="no-referrer" src="<?php echo $rememberedAvatar; ?>">
<?php else: ?>
                    <img alt="" class="avatar photo" height="50" width="50" decoding="async" referrerpolicy="no-referrer" src="<?php echo $anonymousAvatar; ?>">
<?php endif; ?>
                    <p><?php $comments->cancelReply('<span style="color:#FF5151"><br>取消</span>'); ?></p>
                </div>
                <div class="comt-box">
                    <span class="comt-user" style="font-size:14px;color:#66B3FF;"><?php if($loggedIn): ?><?php $this->user->screenName(); ?><?php elseif($hasRemembered): ?><?php echo duxEsc($rememberAuthor); ?> <a href="#" class="commnet-user-change" style="color:#999;font-size:12px;text-decoration:none" rel="nofollow">修改资料</a><?php endif; ?></span>
                    <label class="dux-sr-only" for="comment">评论内容</label>
                    <textarea placeholder="欢迎在此评论， be nice &amp; cool." class="input-block-level comt-area" name="text" id="comment" cols="100%" rows="3" tabindex="1" aria-label="评论内容" required></textarea>
                    <div class="comt-ctrl">
                        <div class="comt-tips"></div>
                        <button type="submit" name="submit" id="submit" tabindex="5">提交评论</button>
                        <?php
                        // Typecho 1.2+ 由 header() 的 antiSpam 脚本自动注入 "_"，
                        // 主题若再输出一个会造成双字段 / referer 不一致而被 protect() 拒绝。
                        // 仅在旧版 Typecho(<1.2) 下补写兼容令牌。
                        duxLegacyCommentToken($this);
                        ?>
                    </div>
                </div>
<?php if(!$loggedIn): ?>
                <div class="comt-comterinfo" id="comment-author-info" <?php if($hasRemembered): ?>style="display:none;"<?php endif; ?> >
                    <ul>
                        <li class="form-inline"><label class="dux-sr-only" for="author">昵称</label><input class="ipt" type="text" name="author" id="author" value="<?php $this->remember('author'); ?>" tabindex="2" placeholder="昵称（必填）" autocomplete="name" aria-label="昵称" required><span class="text-muted"></span></li>
                        <li class="form-inline"><label class="dux-sr-only" for="mail">邮箱</label><input class="ipt" type="email" name="mail" id="mail" value="<?php $this->remember('mail'); ?>" tabindex="3" placeholder="邮箱（必填）" autocomplete="email" aria-label="邮箱" <?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>><span class="text-muted"></span></li>
                        <li class="form-inline"><label class="dux-sr-only" for="url">网址</label><input class="ipt" type="text" inputmode="url" name="url" id="url" value="<?php $this->remember('url'); ?>" tabindex="4" placeholder="网址" autocomplete="url" aria-label="网址"><span class="text-muted"></span></li>
                    </ul>
                </div>
<?php endif; ?>
            </div>
        </form>
<?php } ?>
    </div>
<?php if ($comments->have()) : ?>
    <div id="postcomments">
        <ol class="commentlist"><?php $comments->listComments(array('before' => '', 'after' => '')); ?></ol>
        <div class="pagination">
            <?php $comments->pageNav('←','→',2,'...',array('wrapTag' => 'ul', 'wrapClass' => 'page-navigator', 'itemTag' => 'li', 'textTag' => 'span', 'currentClass' => 'active', 'prevClass' => 'prev-page', 'nextClass' => 'next-page')); ?>
        </div>
    </div>
<?php endif; ?>
