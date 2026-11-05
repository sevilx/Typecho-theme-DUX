<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DUX for Typecho —— 兼容层
 *
 * 目标版本：Typecho 1.3.1 (PHP 7.4+ / 8.x)
 * 说明：
 *  1. Typecho 1.2 起全面命名空间化，但保留了旧类名到新类的自动映射，
 *     本文件仍优先使用新类名，并为旧版 Typecho 提供回退。
 *  2. PHP 8 下 null 传给 array_key_exists / trim / md5 会抛 TypeError 或弃用警告，
 *     所有数据库与配置项读取均做空值保护。
 *  3. 评论表单的安全令牌由 Typecho 自身在 header() 中注入，主题不再手写，
 *     避免与 1.3.1 的 antiSpam 脚本产生双 "_" 字段导致校验失败。
 */

/** 主题版本号：静态资源缓存 bust 与 AMD 模块 urlArgs 共用同一个值 */
if (!defined('DUX_VERSION')) {
    define('DUX_VERSION', '1.3.2');
}

/** 读取当前 Typecho 版本号，兼容命名空间版与旧版 */
function duxTypechoVersion()
{
    if (class_exists('Typecho\Common') && defined('Typecho\Common::VERSION')) {
        return (string) constant('Typecho\Common::VERSION');
    }
    if (class_exists('Typecho_Common') && defined('Typecho_Common::VERSION')) {
        return (string) constant('Typecho_Common::VERSION');
    }
    return '1.0';
}

/** 是否为 1.2+ 的命名空间版 Typecho */
function duxIsModernTypecho()
{
    return version_compare(duxTypechoVersion(), '1.2.0', '>=');
}

/** 统一的 HTML 转义 */
function duxEsc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** 带版本号的静态资源 URL，避免主题升级后浏览器一直命中旧缓存 */
function duxAssetUrl($path)
{
    $options = duxOptions();
    return rtrim((string) $options->themeUrl, '/') . '/' . ltrim((string) $path, '/')
        . '?ver=' . DUX_VERSION;
}

/**
 * 外链白名单：只放行 http(s)，并剔除控制字符与非法主机名。
 * 所有写入 href / src / poster 属性的值都必须先过这里，
 * 再过 duxEsc()，才能同时挡住「协议注入」与「属性闭合」两类 XSS。
 */
function duxSafeUrl($url)
{
    $url = preg_replace('/[\x00-\x20\x7F]+/', '', trim((string) $url));
    if ($url === '' || strlen($url) > 2048) {
        return '';
    }
    // 主机名只允许 RFC 3986 常见字符；javascript: / data: / vbscript: 一律拒绝
    // 分隔符是 #，正则里的 # 必须转义，否则模式会被提前截断
    if (!preg_match('#^https?://[A-Za-z0-9._~%\-]+(?::\d+)?(?:/|\?|\#|$)#i', $url)) {
        return '';
    }
    return $url;
}

/**
 * 头像地址集中生成。
 * 默认使用国内可达的 Cravatar 镜像（原 cn.gravatar.com 已长期不可用），
 * 可在主题设置 avatarMirror 中换成任意兼容 Gravatar 的服务，留空即用默认值。
 * 返回值已经过 HTML 转义，可直接输出到属性中。
 */
function duxAvatarUrl($mail, $size = 50, $rating = 'G')
{
    // 后台填写的镜像地址同样走协议白名单，非法值安静回退到默认的 Cravatar
    $mirror = duxSafeUrl(duxOptions()->avatarMirror);
    $base = $mirror === '' ? 'https://cravatar.cn/avatar' : rtrim($mirror, '/');
    return duxEsc($base . '/' . md5(strtolower(trim((string) $mail)))
        . '?s=' . (int) $size . '&r=' . rawurlencode((string) $rating) . '&d=mm');
}

/** Widget 工厂：优先新命名空间，回退旧类名 */
function duxWidget($alias, $params = null, $request = null)
{
    if (class_exists('Typecho\Widget')) {
        return \Typecho\Widget::widget($alias, $params, $request);
    }
    return Typecho_Widget::widget($alias, $params, $request);
}

/** 数据库实例 */
function duxDb()
{
    return class_exists('Typecho\Db') ? \Typecho\Db::get() : Typecho_Db::get();
}

/** 站点配置 */
function duxOptions()
{
    return duxWidget('Widget_Options');
}

/** 主题设置表单元素工厂：兼容新旧类名 */
function duxFormElement($type, ...$args)
{
    // 单引号字符串中末尾的反斜杠必须写成 "\\"，否则会转义结束引号导致解析错误
    $newClass = 'Typecho\\Widget\\Helper\\Form\\Element\\' . $type;
    $oldClass = 'Typecho_Widget_Helper_Form_Element_' . $type;
    $class = class_exists($newClass) ? $newClass : $oldClass;
    return new $class(...$args);
}

/**
 * 旧版 Typecho(<1.2) 的评论表单需要手写安全令牌。
 * 1.2+ 由 header() 输出的 antiSpam 脚本自动注入，
 * 或 commentsAntiSpam 关闭时 protect() 直接放行，主题不得重复输出 "_"。
 */
function duxLegacyCommentToken($archive)
{
    if (duxIsModernTypecho()) {
        return;
    }
    $security = $archive->widget('Widget_Security');
    echo '<input type="hidden" name="_" value="'
        . duxEsc($security->getToken($archive->request->getReferer())) . '" />';
}

/* ------------------------------------------------------------------ */
/* 后台设置                                                            */
/* ------------------------------------------------------------------ */
function themeConfig($form)
{
    // header 部分
    $logoUrl = duxFormElement('Text', 'logoUrl', NULL, NULL, _t('站点LOGO地址'), _t('在这里填入一个图片URL地址, 以在网站标题前加上一个LOGO'));
    $form->addInput($logoUrl->addRule('xssCheck', _t('请不要在图片链接中使用特殊字符')));

    $icoUrl = duxFormElement('Text', 'icoUrl', NULL, NULL, _t('站点favicon地址'), _t('在这里填入favicon.ico的地址，留空为默认'));
    $form->addInput($icoUrl->addRule('xssCheck', _t('请不要在图片链接中使用特殊字符')));

    $logotext = duxFormElement('Text', 'logotext', null, NULL, _t('站点说明'), _t('logo 旁边的两行说明 以&lt;br&gt;换行'));
    $form->addInput($logotext);

    $customcss = duxFormElement('Textarea', 'customcss', null, NULL, _t('自定义样式'), _t('已经包含style标签'));
    $form->addInput($customcss);

    $DnsPrefetch = duxFormElement('Radio', 'DnsPrefetch',
        array('able' => _t('启用'), 'disable' => _t('禁止')),
        'disable', _t('DNS预解析加速'), _t('默认禁止，启用则会对CDN资源和Gravatar进行加速'));
    $form->addInput($DnsPrefetch);

    $categorymenu = duxFormElement('Radio', 'categorymenu',
        array('able' => _t('折叠'), 'disable' => _t('展开')),
        'able', _t('折叠首页文章分类菜单'), _t(''));
    $form->addInput($categorymenu);

    $fatext = duxFormElement('Textarea', 'fatext', NULL, NULL, _t('顶部导航栏分类fa图标'),
        _t('顶部导航栏分类fa图标，&lt;i class="fa fa-plug"&gt;&lt;/i&gt; 格式一行一个<br>和导航条分类菜单项按顺序匹配,用法参见<a href="http://www.fontawesome.com.cn/faicons/" target="_blank" rel="noopener noreferrer nofollow">FA图标CSS分类参考</a>'));
    $form->addInput($fatext);

    $pagemenu = duxFormElement('Radio', 'pagemenu',
        array('able' => _t('折叠'), 'disable' => _t('展开')),
        'able', _t('折叠首页独立页面菜单'), _t(''));
    $form->addInput($pagemenu);

    $pagefatext = duxFormElement('Textarea', 'pagefatext', NULL, NULL, _t('顶部导航栏独立页面fa图标'),
        _t('顶部导航栏独立页面fa图标，&lt;i class="fa fa-plug"&gt;&lt;/i&gt; 格式一行一个<br>和导航条独立页面项按顺序匹配,用法参见<a href="http://www.fontawesome.com.cn/faicons/" target="_blank" rel="noopener noreferrer nofollow">FA图标CSS分类参考</a>'));
    $form->addInput($pagefatext);

    $tuijian = duxFormElement('Text', 'tj_cid', NULL, NULL, _t('置顶展示'), _t('请输入要置顶展示文章的cid'));
    $form->addInput($tuijian);

    $smallbanner = duxFormElement('Textarea', 'smallbanner', NULL, NULL, _t('首页中部小菜单'), _t('填写导航条代码 A链接格式即可'));
    $form->addInput($smallbanner);

    $indexpic = duxFormElement('Radio', 'indexpic',
        array('able' => _t('启用'), 'disable' => _t('禁止')),
        'able', _t('首页文章显示缩略图'), _t(''));
    $form->addInput($indexpic);

    $Slider = duxFormElement('Radio', 'Slider',
        array('SliderTrue' => _t('开启'), 'SliderFalse' => _t('关闭')),
        'SliderTrue', _t('幻灯片开关'), _t('开启后请在下方填写来幻灯片代码发布幻灯片'));
    $form->addInput($Slider);

    $slidercode = duxFormElement('Textarea', 'slidercode', NULL, NULL, _t('幻灯片代码'),
        _t('请按以下格式填写，展示几个就填几行<br>&lt;a href="你的链接"&gt; &lt;img src="图片链接" width="100%" /&gt;&lt;/a&gt;'));
    $form->addInput($slidercode);

    $infpage = duxFormElement('Radio', 'infpage',
        array('able' => _t('启用'), 'disable' => _t('禁止')),
        'disable', _t('首页文章无限加载'), _t(''));
    $form->addInput($infpage);

    // 侧边栏
    $sidebarBlock = duxFormElement('Checkbox', 'sidebarBlock',
        array('ShowRecentPosts' => _t('最新文章'), 'ShowCategory' => _t('推荐链接，广告位'),
            'ShowRecentComments' => _t('最新评论'), 'ShowTags' => _t('标签云')),
        array('ShowRecentPosts', 'ShowCategory', 'ShowRecentComments', 'ShowTags'), _t('侧边栏显示'));
    $form->addInput($sidebarBlock->multiMode());

    $sidebarAD = duxFormElement('Textarea', 'sidebarAD', NULL, NULL, _t('侧边栏推荐位红色'),
        _t('请按固定格式填写，否则会造成错乱，可添加多个，第一行是广告的链接地址，第二行是广告标题，第三行是广告内容<br>例如:<br>http://themebetter.com/theme/dux<br>DUX主题 新一代主题<br>DUX Wordpress主题是大前端当前使用主题，是大前端积累多年Wordpress主题经验设计而成；更加扁平的风格和干净白色的架构会让网站显得内涵而出色...'));
    $form->addInput($sidebarAD);

    $sitebar_fu = duxFormElement('Text', 'sitebar_fu', NULL, NULL, _t('侧边栏浮动'),
        _t('请输入要浮动的侧边栏模块序号并使用英文逗号分隔，例如1,3 代表第1和第3块侧边栏会浮动'));
    $form->addInput($sitebar_fu);

    $pagesidebar = duxFormElement('Radio', 'pagesidebar',
        array('able' => _t('启用'), 'disable' => _t('禁止')),
        'able', _t('独立页面左边导航栏'), _t('在独立页面左边显示导航栏'));
    $form->addInput($pagesidebar);

    // 图片
    $srcAddress = duxFormElement('Text', 'src_add', NULL, NULL, _t('图片CDN替换前地址'), _t('即你的附件存放链接，一般为http://www.yourblog.com/usr/uploads/'));
    $form->addInput($srcAddress->addRule('xssCheck', _t('请不要在链接中使用特殊字符')));
    $cdnAddress = duxFormElement('Text', 'cdn_add', NULL, NULL, _t('图片CDN替换后地址'), _t('即你的七牛云存储域名，一般为http://yourblog.qiniudn.com/，可能也支持其他有镜像功能的CDN服务'));
    $form->addInput($cdnAddress->addRule('xssCheck', _t('请不要在链接中使用特殊字符')));
    $default_thumb = duxFormElement('Text', 'default_thumb', NULL, '', _t('默认缩略图'), _t('文章没有图片时的默认缩略图，留空则无，一般为http://www.yourblog.com/image.png'));
    $form->addInput($default_thumb->addRule('xssCheck', _t('请不要在链接中使用特殊字符')));

    // 作者简介
    $authordesc = duxFormElement('Text', 'authordesc', null, NULL, _t('作者简介'), _t('文章页的作者说明'));
    $form->addInput($authordesc);

    // 头像镜像（原 cn.gravatar.com 已不可用，留空使用 cravatar.cn）
    $avatarMirror = duxFormElement('Text', 'avatarMirror', NULL, NULL, _t('头像镜像地址'),
        _t('留空使用 Cravatar (https://cravatar.cn/avatar)；也可填任意兼容 Gravatar 的服务根地址，不要带结尾斜杠'));
    $form->addInput($avatarMirror->addRule('xssCheck', _t('请不要在链接中使用特殊字符')));

    // 代码高亮设置
    $useHighline = duxFormElement('Radio', 'useHighline',
        array('able' => _t('启用'), 'disable' => _t('禁止')),
        'disable', _t('代码高亮设置'), _t('默认禁止，启用则会对 ``` 进行代码高亮，支持20种编程语言的高亮'));
    $form->addInput($useHighline);

    // footer 部分
    $footad = duxFormElement('Textarea', 'footad', NULL, NULL, _t('底部广告栏'), _t('页底广告位，可以放置广告，bootstrap样式'));
    $form->addInput($footad);
    $flinks = duxFormElement('Textarea', 'flinks', NULL, NULL, _t('底部友情链接'), _t('底部导航条 一般li格式 友情链接使用'));
    $form->addInput($flinks);
    $fcode = duxFormElement('Textarea', 'fcode', NULL, NULL, _t('底部小广告'), _t('该块显示在网站底部版权上方，可已定义放一些链接或者图片之类的内容。'));
    $form->addInput($fcode);
    $miitbeian = duxFormElement('Text', 'miitbeian', NULL, NULL, _t('备案号'), _t('填写你的备案号，留空则不显示'));
    $form->addInput($miitbeian);
    $GoogleAnalytics = duxFormElement('Textarea', 'GoogleAnalytics', NULL, NULL, _t('统计代码'), _t('填写你的各种跟踪统计代码，相当于页尾代码；此项会原样输出到页面，请只填自己可信的代码'));
    $form->addInput($GoogleAnalytics);

    // 演播室：默认关闭一切外部片源，需要时由站长自己填 http(s) 直链
    $liveVideoUrl = duxFormElement('Text', 'liveVideoUrl', NULL, '',
        _t('演播室片源地址'),
        _t('留空则不加载任何外部视频；填写 http(s) 视频直链后才会播放。默认关闭，避免向第三方 CDN 暴露访客 IP'));
    $form->addInput($liveVideoUrl);
    $livePosterUrl = duxFormElement('Text', 'livePosterUrl', NULL, '',
        _t('演播室封面地址'),
        _t('留空使用主题内置封面图；同样仅接受 http(s) 地址'));
    $form->addInput($livePosterUrl);
}


/* ------------------------------------------------------------------ */
/* 模板辅助函数                                                        */
/* ------------------------------------------------------------------ */

/**
 * 输出正文并做附件 CDN 替换。
 * 注意：不再向 Widget 写回 content（PHP 8.2 动态属性 / 副作用），改用局部变量。
 */
function parseContent($obj)
{
    $options = duxOptions();
    $content = (string) $obj->content;

    if (!empty($options->src_add) && !empty($options->cdn_add)) {
        $content = str_ireplace($options->src_add, $options->cdn_add, $content);
    }

    echo trim($content);
}

/**
 * 确保 contents 表存在主题自建的 views 列。
 * 单个请求内只执行一次；建列失败（只读库、无 ALTER 权限）时安静降级。
 */
function duxEnsureViewsColumn()
{
    $db = duxDb();

    $probe = function () use ($db) {
        try {
            /*
             * 必须用 SELECT * 判断列是否真实存在。
             * 不能写成 $db->select('views')：SQLite 会把不存在的双引号标识符
             * 降级为字符串字面量，返回 array('views' => 'views')，导致误判。
             */
            $row = $db->fetchRow($db->select('*')->from('table.contents')->limit(1));
            return is_array($row) && array_key_exists('views', $row);
        } catch (\Throwable $e) {
            return false;
        }
    };

    if ($probe()) {
        return true;
    }

    try {
        /*
         * @ 用来压住底层适配器在「只读库 / 无 ALTER 权限」时抛出的 PHP warning，
         * 第二个参数显式指定写库操作，避免主从分离时被发到只读从库。
         */
        @$db->query(
            'ALTER TABLE `' . $db->getPrefix() . 'contents` ADD `views` INT(10) NOT NULL DEFAULT 0',
            $db::WRITE
        );
    } catch (\Throwable $ignored) {
        // 权限不足或适配器不支持时，下面会复查并安静降级
    }

    return $probe();
}

/**
 * 输出文章阅读次数。
 *
 * Typecho 1.3 的 \Widget\Base\Contents::filter() 保留数据库返回的全部列，
 * 因此主题自建的 views 列已经在当前 row 中，无需为列表里的每篇文章
 * 再单独发一次 SELECT（旧实现是典型的 N+1 查询）。
 */
function get_post_view($archive)
{
    static $columnChecked = false;
    static $cache = array();

    $cid = (int) $archive->cid;
    if ($cid <= 0) {
        echo 0;
        return;
    }

    // 同一请求内同一篇文章只计算一次，兼顾重复渲染与防重复计数
    if (array_key_exists($cid, $cache)) {
        echo $cache[$cid];
        return;
    }

    $rowViews = $archive->views;
    if ($rowViews === null || !is_numeric($rowViews)) {
        if (!$columnChecked) {
            $columnChecked = true;
            duxEnsureViewsColumn();
        }
        // 本次请求的 row 已构建完成，新建的列默认值为 0，下个请求即可直接读到
        $views = 0;
    } else {
        $views = (int) $rowViews;
    }

    if ($archive->is('single')) {
        $cookie = \class_exists('Typecho\Cookie')
            ? \Typecho\Cookie::get('extend_contents_views')
            : Typecho_Cookie::get('extend_contents_views');

        // Cookie 由访客浏览器提供，必须校验：只保留纯数字 cid、限长 30 条，
        // 否则伪造的超长值会随每个请求头回传，撑大带宽并污染计数。
        $visited = array_slice(array_values(array_filter(
            array_map('trim', explode(',', (string) $cookie)),
            function ($v) {
                return $v !== '' && ctype_digit($v) && strlen($v) <= 10;
            }
        )), -30);

        if (!in_array((string) $cid, $visited, true)) {
            try {
                $db = duxDb();
                @$db->query($db->update('table.contents')
                    ->rows(array('views' => $views + 1))
                    ->where('cid = ?', $cid), $db::WRITE);

                $visited[] = (string) $cid;
                if (class_exists('Typecho\Cookie')) {
                    \Typecho\Cookie::set('extend_contents_views', implode(',', $visited));
                } else {
                    Typecho_Cookie::set('extend_contents_views', implode(',', $visited));
                }
                $views++;
            } catch (\Throwable $e) {
                // 计数失败只影响数字，不影响文章页渲染
            }
        }
    }

    $cache[$cid] = $views;
    echo $views;
}

/** Typecho 指定天数内发布的文章数量（使用站点时区时间，不再硬编码 UTC+8） */
function get_recent_posts_number($days = 1, $display = true)
{
    $db = duxDb();
    $now = (int) duxOptions()->time;
    $from = $now - ((int) $days * 86400);

    try {
        $result = $db->fetchObject($db->select(array('COUNT(cid)' => 'num'))
            ->from('table.contents')
            ->where('created >= ?', $from)
            ->where('created <= ?', $now)
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->where("password IS NULL OR password = ''"));
        $total = is_object($result) ? (int) ($result->num ?? 0) : 0;
    } catch (\Throwable $e) {
        $total = 0;
    }

    if ($display) {
        echo $total;
    }
    return $total;
}

/** 缩略图：优先附件 -> 正文首图 -> 自定义默认图 -> 内置随机图 */
function showThumb($obj, $size = null, $link = false)
{
    static $cache = array();

    $cid = isset($obj->cid) ? (int) $obj->cid : 0;
    if ($cid > 0 && array_key_exists($cid, $cache)) {
        return $cache[$cid];
    }

    $options = duxOptions();
    $thumb = '';

    // 1.3.1 的 attachments() 返回的是 Widget，需要先 next() 才会填充当前行；
    // 直接 ->attachments(1)->attachment 取到的是 null。
    if (method_exists($obj, 'attachments')) {
        try {
            $attachments = $obj->attachments(1);
            if ($attachments->have() && $attachments->next()) {
                $attach = $attachments->attachment;
                if (is_object($attach) && !empty($attach->isImage)) {
                    $thumb = (string) $attach->url;
                }
            }
        } catch (\Throwable $e) {
            // 附件表异常时静默回退到正文首图
        }
    }

    if ($thumb === '' && preg_match('/<img\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1/i', (string) $obj->content, $matches)) {
        $thumb = $matches[2];
    }

    if ($thumb !== '' && !empty($options->src_add) && !empty($options->cdn_add)) {
        $thumb = str_ireplace($options->src_add, $options->cdn_add, $thumb);
    }

    if ($thumb === '') {
        $thumb = !empty($options->default_thumb)
            ? (string) $options->default_thumb
            : rtrim((string) $options->themeUrl, '/') . '/img/thumb/' . rand(1, 25) . '.jpg';
    }

    // 缓存后同一篇文章在首页/侧边栏/相关推荐里显示同一张随机图，避免抖动
    if ($cid > 0) {
        $cache[$cid] = $thumb;
    }

    // $link 仅为兼容旧调用保留；始终返回地址，调用方负责输出
    return $thumb;
}

/** 编辑推荐（置顶文章） */
function hotpost()
{
    $options = duxOptions();
    $raw = $options->tj_cid;
    $tjids = (!empty($raw) && is_numeric($raw) && floor((float) $raw) == (float) $raw)
        ? (int) $raw : 0;

    if ($tjids <= 0) {
        return;
    }

    $db = duxDb();
    try {
        $result = $db->fetchAll($db->select()->from('table.contents')
            ->where('status = ?', 'publish')
            ->where('type = ?', 'post')
            ->where('cid = ?', $tjids));
    } catch (\Throwable $e) {
        return;
    }

    if (empty($result)) {
        return;
    }

    // Typecho 1.3.1 的 Base\Contents::filter() 只做字段规范化，
    // permalink/title/text 都是 Widget 的魔术属性，必须 push 后再读取；
    // 旧写法 $val['permalink'] 永远为空，置顶文章会生成 href=""。
    $contents = duxWidget('Widget_Abstract_Contents@duxHotpost');

    foreach ($result as $val) {
        $contents->push($val);

        $permalink = (string) $contents->permalink;
        $title = (string) $contents->title;
        $text = strip_tags((string) $contents->text);
        $excerpt = function_exists('mb_substr')
            ? mb_substr($text, 0, 208, 'UTF-8')
            : substr($text, 0, 208);

        printf(
            '<article class="excerpt-minic excerpt-minic-index"><h2><span class="red">【置顶】</span>'
            . '<a href="%s" title="%s">%s</a></h2><p class="note">%s...</p></article>',
            duxEsc($permalink), duxEsc($title), duxEsc($title), duxEsc($excerpt)
        );
    }
}

/** 幻灯片输出 */
function slout()
{
    $options = duxOptions();
    $themeUrl = rtrim((string) $options->themeUrl, '/');

    $text = !empty($options->slidercode)
        ? (string) $options->slidercode
        : '<a target="_blank" rel="noopener noreferrer nofollow" href="https://github.com/hiCasper/Typecho-theme-DUX"><img src="'
            . duxEsc($themeUrl . '/img/banner.jpg') . '"></a>';

    // 兼容 Windows / Unix 换行，丢弃空行，避免出现空的幻灯片项
    $items = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)), 'strlen'));
    if (empty($items)) {
        return;
    }

    $html = '<div id="focusslide" class="carousel slide" data-ride="carousel"><ol class="carousel-indicators">';
    foreach ($items as $key => $val) {
        $html .= '<li data-target="#focusslide" data-slide-to="' . $key . '"'
            . ($key === 0 ? ' class="active"' : '') . '></li>';
    }
    $html .= '</ol><div class="carousel-inner" role="listbox">';
    foreach ($items as $key => $val) {
        $html .= '<div class="item' . ($key === 0 ? ' active' : '') . '">' . $val . '</div>';
    }
    $html .= '</div>'
        . '<a class="left carousel-control" href="#focusslide" role="button" data-slide="prev"><i class="fa fa-angle-left"></i></a>'
        . '<a class="right carousel-control" href="#focusslide" role="button" data-slide="next"><i class="fa fa-angle-right"></i></a>'
        . '</div>';

    echo $html;
}

/** 导航 fa 图标 */
function fa_ico($type, $num)
{
    $options = duxOptions();
    $key = ($type == 1) ? 'fatext' : 'pagefatext';
    $text = (string) $options->{$key};

    if ($text === '') {
        return '';
    }

    $items = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)), 'strlen'));
    return isset($items[$num]) ? $items[$num] : '';
}

/** 侧边栏推荐位：返回长度为 3 的倍数的行数组，防止模板下标越界 */
function sitebar_ad($obj)
{
    $text = !empty($obj)
        ? (string) $obj
        : "https://github.com/hiCasper/Typecho-theme-DUX\nDUX主题 新一代主题\nDUX for Typecho";

    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)), 'strlen'));
    $groups = (int) floor(count($lines) / 3);
    return array_slice($lines, 0, $groups * 3);
}

/** 站点运行时间 */
function getBuildTime()
{
    // 在下面按格式输入本站创建的时间
    $site_create_time = strtotime('2010-01-01 00:00:00');
    $time = time() - $site_create_time;
    if (is_numeric($time)) {
        $value = array(
            'years' => 0, 'days' => 0, 'hours' => 0,
            'minutes' => 0, 'seconds' => 0,
        );
        if ($time >= 31556926) {
            $value['years'] = floor($time / 31556926);
            $time = ($time % 31556926);
        }
        if ($time >= 86400) {
            $value['days'] = floor($time / 86400);
            $time = ($time % 86400);
        }
        if ($time >= 3600) {
            $value['hours'] = floor($time / 3600);
            $time = ($time % 3600);
        }
        if ($time >= 60) {
            $value['minutes'] = floor($time / 60);
            $time = ($time % 60);
        }
        $value['seconds'] = floor($time);

        echo '<span class="btime">' . $value['years'] . '年' . $value['days'] . '天'
            . $value['hours'] . '小时</span>';
    }
}
