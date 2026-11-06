<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 侧边栏浮动模块序号：统一用 json_encode 输出，避免引号/逗号导致 JS 注入或语法错误
$rollRaw = (string) ($this->options->sitebar_fu ?: '1');
$roll = array_values(array_filter(array_map('intval', explode(',', $rollRaw))));
if (empty($roll)) {
    $roll = array(1);
}
$jsui = array(
    'www'       => (string) $this->options->siteUrl,
    'uri'       => rtrim((string) $this->options->themeUrl, '/'),
    'ver'       => DUX_VERSION,
    'roll'      => $roll,
    'ajaxpager' => ($this->options->infpage == 'disable') ? 0 : 1,
);
// HEX_* 防止站点地址里的 </script>、引号等字符提前闭合脚本块
$jsuiJson = json_encode(
    $jsui,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($jsuiJson === false) {
    $jsuiJson = '{"www":"","uri":"","ver":"' . DUX_VERSION . '","roll":[1],"ajaxpager":0}';
}
$duxElegance = duxEleganceEnabled();
?>

    <!--footer-->
<?php if($this->options->footad && !empty($this->options->footad) ): ?>
    <div class="branding branding-black">
        <div class="container">
            <?php echo duxSafeHtml($this->options->footad); ?>
        </div>
    </div>
<?php endif; ?>
    <footer id="footer" class="footer">
        <div class="container">
<?php if($this->options->flinks && !empty($this->options->flinks) ): ?>
            <div class="flinks">
                <strong>友情链接</strong>
                <ul class='xoxo blogroll'>
                    <?php echo duxSafeHtml($this->options->flinks); ?>
                </ul>
            </div>
<?php endif; ?>
<?php if($this->options->fcode && !empty($this->options->fcode) ): ?>
            <div class="fcode"><?php echo duxSafeHtml($this->options->fcode); ?></div>
<?php endif; ?>
            <div class="copyright">
                <p>
                    &copy; 2010 - <?php echo date("Y"); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title();?></a>
                    <?php
                    // 备案号只从「设置外观 → 备案号」读取，代码里不内置任何站点信息，方便任何人直接安装使用
                    $duxIcp = trim((string) $this->options->miitbeian);
                    if ($duxIcp !== ''): ?> | <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer nofollow"><?php echo duxEsc($duxIcp); ?></a><?php endif; ?> | Powered by <a href="https://typecho.org/" target="_blank" rel="noopener noreferrer nofollow">Typecho</a> | Theme <a href="https://github.com/sevilx/Typecho-theme-DUX" target="_blank" rel="noopener noreferrer nofollow">DUX · Ben Edition</a> by Ben
                    <br>
                    <!-- 上游致谢：大前端 DUX for WordPress → hiCasper/Typecho-theme-DUX；本分支由 Ben 独立维护 -->
                    本站部分图片、文章来源于网络，版权归原作者所有，如有侵权，请联系我们删除。
                </p>
            </div>
            <div class="hide"></div>
        </div>
    </footer>
<?php if ($this->options->useHighline == 'able'): ?>
    <script defer src="<?php echo duxEsc(duxAssetUrl('css/highlight.min.js')); ?>"></script>
<?php endif; ?>
    <script>
        window.jsui = <?php echo $jsuiJson; ?>;
    </script>
    <!-- 全部 defer：按文档顺序执行，jQuery 仍先于 bootstrap / RequireJS 就绪，但不再阻塞首屏 -->
    <script defer type='text/javascript' src='<?php echo duxEsc(duxAssetUrl('css/bootstrap.min.js')); ?>'></script>
    <script defer type='text/javascript' src='<?php echo duxEsc(duxAssetUrl('js/loader.js')); ?>'></script>
<?php if ($duxElegance): ?>
    <script defer type='text/javascript' src='<?php echo duxEsc(duxAssetUrl('js/dux-enhance.js')); ?>'></script>
<?php endif; ?>
    <!-- Analytics code：仅输出后台「统计代码」里由站长自己填写的内容，留空即无任何第三方请求 -->
<?php if($this->options->GoogleAnalytics): ?>
    <?php $this->options->GoogleAnalytics(); ?>
<?php endif; ?>
</body>
</html>
