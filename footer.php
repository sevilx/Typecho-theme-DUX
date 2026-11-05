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
?>

    <!--footer-->
<?php if($this->options->footad && !empty($this->options->footad) ): ?>
    <div class="branding branding-black">
        <div class="container">
            <?php $this->options->footad(); ?>
        </div>
    </div>
<?php endif; ?>
    <footer id="footer" class="footer">
        <div class="container">
<?php if($this->options->flinks && !empty($this->options->flinks) ): ?>
            <div class="flinks">
                <strong>友情链接</strong>
                <ul class='xoxo blogroll'>
                    <?php $this->options->flinks(); ?>
                </ul>
            </div>
<?php endif; ?>
<?php if($this->options->fcode && !empty($this->options->fcode) ): ?>
            <div class="fcode"><?php $this->options->fcode(); ?></div>
<?php endif; ?>
            <div class="copyright">
                <p>
                    &copy; 2010 - <?php echo date("Y"); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title();?></a>
                    <?php if($this->options->miitbeian): ?> | <a href="http://beian.miit.gov.cn/" target="_blank" rel="noopener nofollow"><?php $this->options->miitbeian(); ?></a><?php endif; ?>
                    <br>
                    <!--Powered by <a href="http://www.typecho.org/" target="_blank">Typecho</a> |
                    Theme <a href="https://github.com/hiCasper/Typecho-theme-DUX" target="_blank">DUX</a>-->
                    本站部分图片、文章来源于网络，版权归原作者所有，如有侵权，请联系我们删除。
                </p>
            </div>
            <div class="hide"></div>
        </div>
    </footer>
<?php if ($this->options->useHighline == 'able'): ?>
    <script src="<?php echo duxEsc(duxAssetUrl('css/highlight.min.js')); ?>"></script>
<?php endif; ?>
    <script>
        window.jsui = <?php echo $jsuiJson; ?>;
    </script>
    <script type='text/javascript' src='<?php echo duxEsc(duxAssetUrl('css/bootstrap.min.js')); ?>'></script>
    <script type='text/javascript' src='<?php echo duxEsc(duxAssetUrl('js/loader.js')); ?>'></script>
    <!-- Analytics code -->
<?php if($this->options->GoogleAnalytics): ?>
    <?php $this->options->GoogleAnalytics(); ?>
<?php endif; ?>
</body>
</html>
