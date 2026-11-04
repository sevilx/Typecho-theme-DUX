/*
 * DUX for Typecho 1.3.1 —— 评论模块
 *
 * 旧版本是 WordPress 移植代码：POST 到主题里根本不存在的
 * jsui.uri + '/action/comment.php'，并依赖 comment_post_ID /
 * comment_parent / wp-temp-form-div / addComment.moveForm 等 WP DOM，
 * 同时会覆盖 Typecho 的 TypechoComment 回复脚本，在 1.3.1 下必然失效。
 *
 * 现在改为「渐进增强」：只做交互与防重复提交，表单本身交给 Typecho 原生提交，
 * 这样回复 parent、antiSpam 的 "_" 令牌、Cookie 记忆、审核跳转全部由核心处理。
 */
tbfine(function () {
    'use strict';

    return {
        init: function () {
            var $ = window.jQuery;
            if (!$) {
                return;
            }

            $('.commentlist .url').attr('target', '_blank').attr('rel', 'external nofollow');

            // 老访客的身份信息默认隐藏，这里提供「修改资料」入口把表单展开。
            // 上游主题从未输出 .commnet-user-change 元素，该处理一直是死代码。
            $('.commnet-user-change').on('click', function (e) {
                e.preventDefault();
                var $info = $('#comment-author-info');
                if (!$info.length) {
                    return;
                }
                $info.slideDown(300);
                $info.find('input:first').trigger('focus');
            });

            var $form = $('#commentform');
            if (!$form.length) {
                return;
            }

            var $tips = $('.comt-tips');
            var $submit = $form.find('#submit');
            var submitting = false;

            if ($tips.length && !$tips.find('.comt-loading').length) {
                $tips.append('<div class="comt-tip comt-loading" style="display:none">评论提交中...</div>');
            }

            $form.on('submit', function (e) {
                if (submitting) {
                    e.preventDefault();
                    return;
                }

                /*
                 * Typecho 1.3.1 的 commentsAntiSpam 脚本会在第一个交互事件
                 * (scroll / mousemove / keyup / touchstart) 时才把隐藏域 "_"
                 * 追加到表单。若用户直接点击提交按钮，令牌可能还没写入，
                 * 会被 Widget\Feedback::comment() 的 protect() 拒绝。
                 * 这里主动派发一次事件触发注入，不影响原生提交流程。
                 */
                try {
                    window.dispatchEvent(new Event('mousemove'));
                } catch (err) {
                    try {
                        var evt = document.createEvent('Event');
                        evt.initEvent('mousemove', true, true);
                        window.dispatchEvent(evt);
                    } catch (ignored) {
                        // 极老浏览器忽略即可，用户移动鼠标已能触发注入
                    }
                }

                submitting = true;
                $tips.find('.comt-loading').slideDown(150);
                $submit.css('opacity', 0.6).attr('aria-busy', 'true');

                // 故意不 return false：让浏览器正常提交表单到 $this->commentUrl()
                setTimeout(function () {
                    submitting = false;
                    $submit.css('opacity', 1).removeAttr('aria-busy');
                }, 10000);
            });
        }
    };
});
