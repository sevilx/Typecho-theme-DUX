# Typecho-theme-DUX

> Forked from https://coding.net/u/Arco-X/p/DUX-for-Typecho/git
> 一款基于大前端 DUX for WordPress 主题修改而来的 Typecho 主题。

当前分支针对 **Typecho 1.3.x 开发版（1.3.1-rc）+ PHP 8.2** 做了完整适配、回归与安全审计，
同时保留对 Typecho 1.2+ / PHP 7.4+ 的兼容回退。
**1.4.0** 在此基础上追加了一轮性能、视觉与安全优化（见下节），全部改动保持 DUX 原有布局与配色可回退。

## 特性

- 修复了原版不能正常评论的问题，评论表单走 Typecho 1.3 官方 `TypechoComment` / `antiSpam` 流程
- 美化了样式，修改细节，增加了一些小功能
- 新旧 Typecho 兼容层：`duxWidget` / `duxDb` / `duxOptions` / `duxFormElement` / `duxEsc`
- 自定义模板：文章归档、分类归档、标签云、友链、多媒体演播室、侧边栏页面
- 1.4.0：外观增强层、深浅色模式、可配置主题色、阅读进度、代码一键复制、原生懒加载

## 1.4.0 体验优化

设计原则：**不改 4500 行的历史 `css/main.css`**，新增独立的 `css/dux-elegance.css` 与
无依赖的 `js/dux-enhance.js` 作为增强层。后台「外观增强」选「禁用」即可一键回到旧版外观，
增强层 JS 即使 RequireJS / jQuery 加载失败也不会影响主题主功能。

### 后台新增配置项（设置外观）

| 配置 | 取值 | 说明 |
| --- | --- | --- |
| `elegance` | 启用 / 禁用 | 是否加载增强层；老站点升级后无此配置时默认启用 |
| `darkMode` | auto / light / dark / disable | 跟随系统 / 强制浅色 / 强制深色 / 不提供切换（恒浅色） |
| `accentColor` | `#RRGGBB` | 自定义主色，派生 `--dux-accent-hover` / `-soft` / `-line`；非法值自动回退默认蓝 |

深浅色由 `<head>` 内一段同步脚本在首屏渲染前写入 `data-theme`，避免闪白；
用户选择存 `localStorage`，未选择时跟随 `prefers-color-scheme`。

### 性能

- 全站 JS 改 `defer`；Font Awesome 字体 `preload` + `font-display: swap`
- 用原生 `loading="lazy"` / `decoding="async"` 取代 jQuery `lazyload`，删除 `src` + `data-src` 双写造成的重复请求
- 所有缩略图 / Logo / banner 补 `width` / `height`，降低 CLS；首屏首图加 `fetchpriority="high"`
- 滚动监听改 `requestAnimationFrame` 节流 + `passive: true`，只在跨越 200px 阈值时切换 rollbar
- 静态资源统一 `?ver=1.4.0` 缓存 bust（版本号集中在 `DUX_VERSION`）
- 移除演播室页面每次加载都向外部媒体子域发起的 `OPTIONS` 心跳（无功能、泄露访客 IP / Referer）

### 视觉与可访问性

- CSS 设计令牌统一颜色 / 圆角 / 阴影 / 字体栈 / 缓动；重做卡片、正文排版、代码块、表格、引用、标签、分页、评论、页脚
- 修复原 `.comt-main { max-height: 300px; overflow: hidden }` 截断长评论
- 补 `focus-visible` 焦点环（原 `main.css` 的 `a:focus { outline: 0 }` 抹掉了键盘焦点）、skip link、`<button>` 替代伪按钮、`aria-*` / `<time datetime>`
- 恢复移动端缩放（移除 `user-scalable=0` / `maximum-scale=1`）
- 阅读进度条、代码一键复制、深浅色切换按钮
- `prefers-reduced-motion` 动效降级与 `@media print` 打印样式
- 修正首页空站点描述时 `<title>站名 - </title>` 的悬空分隔符

### 安全与隐私

- 后台「可填 HTML」字段（`slidercode` / `fatext` / `footad` / `flinks` / `fcode` / `smallbanner` / `brand` 等）
  统一经 `duxSafeHtml()` DOM 白名单清洗：移除 `script` / `iframe` / `style` 等危险标签、所有 `on*` 事件、
  `javascript:` / `data:` 协议与危险 CSS，并给 `target="_blank"` 自动补 `noopener noreferrer`
- `customcss` 经 `duxSafeCss()` 处理，防止 `</style>` 提前闭合注入脚本
- 头像加 `referrerpolicy="no-referrer"`；外链补 `noopener noreferrer`；移除两处 `javascript:` 伪协议
- 评论表单补 `autocomplete` / `aria-label` / `inputmode`，网址改为 `type="text"` 避免误拒裸域名
- 动态属性输出统一 `duxEsc()`；`duxSafeUrl()` 保持 URL 协议白名单

> **`GoogleAnalytics` 是唯一仍按原样输出的字段**：它的设计用途就是让站长放统计脚本，
> 只有管理员能写，请勿粘贴来路不明的代码。

### 验证结论（1.4.0）

| 项目 | 结果 |
| --- | --- |
| `php -l` / `node --check` | 16 个 PHP + 17 个 JS 全部通过 |
| `duxSafeHtml()` 白名单单测 | 10 例（XSS 拦截 + 正常排版保留）全通过 |
| `security_audit.py` | 扫描 117 个文件，HIGH/MEDIUM/LOW 均为 0 |
| `http_regression.py`（隔离 Typecho + SQLite） | 22 条路由、14 个静态资源，0 失败、0 警告、0 PHP 错误 |
| 后台 `options-theme.php` | 200，新增 3 组控件渲染正常；保存后 `darkMode` / `accentColor` 正确回写前台 |
| 老站点升级（无新配置） | 优雅降级为「增强层启用 + 跟随系统 + 默认主色」 |
| 新增资源 | `css/dux-elegance.css`、`js/dux-enhance.js` 均 200 |
| 浏览器实渲染（Chromium 1440×1200） | 首页 / 文章页 × 浅色 / 深色均正常；据此修复了深色模式下侧栏白块、评论框白底与「提交评论」按钮折行 |

## 1.3.x 适配要点

| 位置 | 处理 |
| --- | --- |
| 类名 | 旧类名由核心自动加载器映射到命名空间类；`Widget_Stat`、`Widget_Abstract_Contents` 等已核对存在于 1.3.1-rc |
| 评论 | 主题不再手写 `_` 令牌；`threadedComments()` 无参调用，兼容 `singleCommentOptions` 递归 |
| 阅读数 | 直接读取 `\Widget\Base\Contents::filter()` 保留在 row 里的 `views` 列，消除列表页 N+1 查询；列缺失时自动建列并安静降级 |
| 缩略图 | `attachments()` 返回 Widget，需 `have()` + `next()` 后读 `attachment`；结果带请求级缓存 |
| 附件 / 设置 | 1.3 起主题设置以 JSON 保存，全部通过 `$this->options->*` 读取，不直接 `unserialize()` |
| PHP 8 | 所有配置读取做空值保护，`json_encode` 加 `JSON_HEX_*`，不写 Widget 动态属性 |

## 回归测试

`tests/http_regression.py` 是只读回归脚本：自动从首页发现站内路由，
逐条 GET 检查 HTTP 状态、PHP 错误特征、HTML 完整性，并校验 CSS/JS/图片等静态资源。

```bash
# 生产站点
python tests/http_regression.py --base https://www.xiongjinghang.com

# 本地隔离实例
python tests/http_regression.py --base http://127.0.0.1:8901 --out report.json
```

退出码 `0` 表示全部通过；报告写入 `report.json`（含延迟均值 / p95 / 最大值）。

建议在 `error_reporting=E_ALL`、`display_errors=On` 的隔离环境里再跑一次，
因为官方 Docker 镜像默认关闭 `display_errors`，弃用提示不会出现在生产页面里。

已验证的覆盖范围：首页与分页、文章页、分类/标签/搜索/作者归档、RSS、
自定义模板页面、评论分页与嵌套评论、置顶文章、幻灯片、附件缩略图、404、全部静态资源。

## 安全审计

`tests/security_audit.py` 是纯标准库、**只读、不联网、不改文件**的静态审计脚本，可离线跑、可进 CI。

```bash
python tests/security_audit.py                    # 打印摘要
python tests/security_audit.py --json audit.json  # 结构化报告（含全量 sha256 清单）
python tests/security_audit.py --strict           # 有 HIGH 时退出 1，用于 CI 卡口
```

检查项：

1. 非 PHP 文件混入 PHP 标签（图片马 / JS 后门）
2. 图片、字体文件尾部夹带可执行文本
3. PHP 模板里的动态执行、命令执行、反序列化等危险调用
4. 外部 URL 域名清单，区分「运行时会发起请求」与「仅注释 / 文档」
5. 输出型 HTML 里的固定长十六进制指纹（隐藏授权 / 追踪标记）
6. `target="_blank"` 缺 `noopener`（tabnabbing）
7. `href` / `src` 属性直接输出后台设置值且未做协议白名单
8. 全量文件 sha256，用于两次发布之间的完整性比对

### 外链策略

- 主题默认**不向任何第三方域名发起请求**（唯一例外是可在后台配置、留空即用 Cravatar 的头像镜像）。
- 所有写进 `href` / `src` / `poster` 的值统一走 `duxSafeUrl()`（只放行 http(s)、剔除控制字符与非法主机名）
  + `duxEsc()`（HTML 转义），同时挡住协议注入与属性闭合。
- 演播室片源、封面、头像镜像、Logo、favicon、CDN 前缀都是后台可配置项，默认值安全。
- 所有 `target="_blank"` 都带 `rel="noopener noreferrer"`。

### 后台可填 HTML 的字段

1.4.0 起，`slidercode`、`fatext`、`pagefatext`、`sidebarAD`、`footad`、`flinks`、
`fcode`、`smallbanner`、`brand`、`logotext` 等排版片段会先经 `duxSafeHtml()` 白名单清洗，
`customcss` 经 `duxSafeCss()` 清洗，均**不再原样输出**。

唯一例外是 `GoogleAnalytics`（统计代码），它按设计原样输出、只有管理员能写。
请勿粘贴来路不明的统计代码或第三方脚本；填写前后各跑一次
`security_audit.py` 与 `http_regression.py`。这是「站长自己的选择」，不是主题的默认行为。

## 部署建议（Docker 镜像）

官方 `joyqi/typecho:nightly-php8.2-apache` 与 `nightly-php8.2-fpm` 的 PHP 扩展、
Typecho 代码、`opcache` 完全一致，差别只在 Web 服务器层：

- **apache**：单容器、自带 `mod_rewrite` + 官方写好的 `.htaccess` 伪静态、暴露 80，开箱即用。
- **fpm**：只暴露 9000，必须额外跑一个 Nginx/Caddy 容器，并自行配置
  `try_files $uri $uri/ /index.php$is_args$args;`、静态文件、HTTPS 与日志。

对单台小规格 VPS 上的个人博客，**继续用 apache 版**：容器更少、常驻内存更低、
故障点更少、伪静态不会配错。FPM 的优势（worker 独立扩缩、多 pool、慢日志）
只有在高并发或已有 Nginx 网关时才兑现。

如需进一步压内存，可给容器加上 `MEMORY_LIMIT=128M`、`TIMEZONE=Asia/Shanghai`，
并限制 Apache `mpm_prefork` 的 `MaxRequestWorkers`（默认 150 在小内存机器上偏高）。

## 已知限制

- 主题会自动给 `contents` 表加 `views` 列；只读数据库或无 `ALTER` 权限时阅读数固定显示 0，不影响页面。
- 演播室默认不播放任何外部视频；如需演示片源，在「主题设置 → 演播室片源地址」填自己的 http(s) 直链。
- 头像默认走 Cravatar 镜像，可在「主题设置 → 头像镜像地址」换成其他兼容 Gravatar 的服务。

> 本主题仅基于爱好修改，使用时请尊重原作者大前端版权。
