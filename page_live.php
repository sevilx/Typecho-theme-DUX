<?php
/**
 * 极客实验室与多媒体演播厅
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<?php $this->need('header.php'); ?>
<?php
// 片源 / 封面全部改为后台可配置，默认留空 = 不向任何第三方域名发起请求。
// 所有取值先过 http(s) 协议白名单，再过 HTML 转义才写进 video 属性。
$duxLiveVideo  = duxSafeUrl($this->options->liveVideoUrl);
$duxLivePoster = duxSafeUrl($this->options->livePosterUrl);
if ($duxLivePoster === '') {
    $duxLivePoster = duxAssetUrl('img/banner.jpg');
}
?>

<style>
/* 演播室专用暗黑科技风样式 */
.live-studio-wrap {
    background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
    border-radius: 12px;
    padding: 28px;
    color: #f8fafc;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
    margin-bottom: 25px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.live-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 18px;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 12px;
}
.live-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
}
.live-badge {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.4);
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    letter-spacing: 0.5px;
}
.live-badge .dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    display: inline-block;
    animation: pulse-green 2s infinite;
}
@keyframes pulse-green {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.live-title {
    font-size: 22px;
    font-weight: 600;
    color: #ffffff;
    margin: 0;
}
.live-meta {
    font-size: 13px;
    color: #94a3b8;
    display: flex;
    gap: 18px;
}
.live-player-grid {
    display: grid;
    grid-template-columns: 1fr 310px;
    gap: 22px;
}
@media (max-width: 992px) {
    .live-player-grid {
        grid-template-columns: 1fr;
    }
}
.video-box {
    position: relative;
    background: #020617;
    border-radius: 10px;
    overflow: hidden;
    aspect-ratio: 16 / 9;
    box-shadow: inset 0 0 40px rgba(0, 0, 0, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
}
.video-box video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.video-overlay-tip {
    position: absolute;
    top: 14px;
    left: 16px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(6px);
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    color: #cbd5e1;
    border: 1px solid rgba(255, 255, 255, 0.1);
    pointer-events: none;
}
.live-sidebar {
    background: rgba(15, 23, 42, 0.65);
    border-radius: 10px;
    padding: 18px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.sidebar-section-title {
    font-size: 14px;
    font-weight: 600;
    color: #e2e8f0;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.telemetry-list {
    list-style: none;
    padding: 0;
    margin: 0 0 18px 0;
    font-size: 13px;
}
.telemetry-list li {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed rgba(255, 255, 255, 0.06);
    color: #94a3b8;
}
.telemetry-list li span.val {
    color: #38bdf8;
    font-family: Consolas, Monaco, monospace;
    font-weight: 600;
}
.telemetry-list li span.val.ok {
    color: #34d399;
}
.schedule-box {
    background: rgba(30, 41, 59, 0.5);
    border-radius: 8px;
    padding: 12px;
    font-size: 12px;
    color: #cbd5e1;
    line-height: 1.6;
    border-left: 3px solid #38bdf8;
}
.tech-notes-card {
    background: #ffffff;
    border-radius: 10px;
    padding: 25px 30px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}
.tech-notes-card h3 {
    font-size: 18px;
    margin-top: 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}
</style>

<section class="container">
    <div class="content-wrap">
        <div class="content" id="dux-main" tabindex="-1" style="width: 100%; margin-right: 0;">

            <!-- 演播室主视觉区（纯前端本地展示，无任何子域名或节点信息暴露） -->
            <div class="live-studio-wrap">
                <div class="live-header">
                    <div class="live-title-group">
                        <span class="live-badge"><span class="dot"></span>STANDBY / 演示轮播中</span>
                        <h1 class="live-title"><?php $this->title(); ?></h1>
                    </div>
                    <div class="live-meta">
                        <span><i class="fa fa-television"></i> 频道：Web 音视频技术测试</span>
                        <span><i class="fa fa-code"></i> 渲染引擎：HTML5 MediaElement</span>
                    </div>
                </div>

                <div class="live-player-grid">
                    <!-- 左侧 HTML5 播放器（仅加载公开开源演示片源，不触发任何私有域名请求） -->
                    <div class="video-box">
                        <div class="video-overlay-tip">
<?php if ($duxLiveVideo !== ''): ?>
                            <i class="fa fa-film"></i> 当前为非直播时段，正在播放站内配置的演示片源
<?php else: ?>
                            <i class="fa fa-film"></i> 当前为非直播时段，未配置片源（默认不加载任何外部视频）
<?php endif; ?>
                        </div>
                        <video id="studio-player" controls preload="none" poster="<?php echo duxEsc($duxLivePoster); ?>">
<?php if ($duxLiveVideo !== ''): ?>
                            <source src="<?php echo duxEsc($duxLiveVideo); ?>" type="video/mp4">
<?php endif; ?>
                            您的浏览器不支持 HTML5 视频播放。
                        </video>
                    </div>

                    <!-- 右侧播放器本地状态栏 -->
                    <div class="live-sidebar">
                        <div>
                            <div class="sidebar-section-title">
                                <span><i class="fa fa-sliders"></i> 播放器环境参数</span>
                                <span style="font-size: 11px; color: #64748b;">LOCAL</span>
                            </div>
                            <ul class="telemetry-list">
                                <li>
                                    <span>当前状态</span>
                                    <span class="val ok">离线录播演示</span>
                                </li>
                                <li>
                                    <span>视频编码</span>
                                    <span class="val">AVC1 (H.264 High)</span>
                                </li>
                                <li>
                                    <span>音频编码</span>
                                    <span class="val">AAC-LC 48kHz</span>
                                </li>
                                <li>
                                    <span>色彩空间</span>
                                    <span class="val">BT.709 / 8-Bit</span>
                                </li>
                                <li>
                                    <span>硬件加速</span>
                                    <span class="val ok" id="hw-status">WebGL / GPU Ready</span>
                                </li>
                            </ul>
                        </div>

                        <div class="schedule-box">
                            <strong><i class="fa fa-bullhorn"></i> 频道说明：</strong><br>
                            本页面用于日常测试响应式页面布局与浏览器多媒体解码兼容性。非直播活动期间默认加载公共开源测试视频，不产生任何私有媒体流消耗。
                        </div>
                    </div>
                </div>
            </div>

            <!-- 下方图文区（支持在 Typecho 后台编辑页面正文） -->
            <div class="tech-notes-card">
                <h3><i class="fa fa-file-text-o"></i> 频道归档与更新日志</h3>
                <article class="article-content">
                    <?php if (trim((string) $this->content) !== ''): ?>
                        <?php parseContent($this); ?>
                    <?php else: ?>
                        <p>欢迎来到多媒体测试专栏。本页面主要用于分享前端音视频开发笔记、桌面软硬件折腾记录以及不定期的在线技术交流。</p>
                        <ul>
                            <li><strong>日常状态：</strong>非直播时段处于待机模式，仅提供静态文章阅读与公共样片测试。</li>
                            <li><strong>技术讨论：</strong>如果您对站内技术文章有任何疑问，欢迎在下方评论区留言交流。</li>
                        </ul>
                    <?php endif; ?>
                </article>
            </div>

            <?php $this->need('comments.php'); ?>
        </div>
    </div>
</section>

<?php
/*
 * 已移除原先在页面加载后向外部媒体子域发起随机 x_padding
 * OPTIONS「心跳」的脚本：它没有任何播放器功能，却会在每位访客打开
 * 演播室页面时向外部域名泄露 IP / Referer，与主题「默认不产生外部请求」
 * 的约定冲突，也白白多一次跨域预检。片源请在后台显式配置。
 */
$this->need('footer.php');
?>