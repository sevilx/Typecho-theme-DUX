# Typecho-theme-DUX

> Forked from https://coding.net/u/Arco-X/p/DUX-for-Typecho/git
> 一款基于大前端 DUX for WordPress 主题修改而来的 Typecho 主题。

当前分支针对 **Typecho 1.3.x 开发版（1.3.1-rc）+ PHP 8.2** 做了完整适配与回归，
同时保留对 Typecho 1.2+ / PHP 7.4+ 的兼容回退。

## 特性

- 修复了原版不能正常评论的问题，评论表单走 Typecho 1.3 官方 `TypechoComment` / `antiSpam` 流程
- 美化了样式，修改细节，增加了一些小功能
- 新旧 Typecho 兼容层：`duxWidget` / `duxDb` / `duxOptions` / `duxFormElement` / `duxEsc`
- 自定义模板：文章归档、分类归档、标签云、友链、多媒体演播室、侧边栏页面

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
- `page_live.php` 内的演示视频来自公开测试片源，可在后台替换为自己的内容。
- 头像默认走 Cravatar 镜像，可在「主题设置 → 头像镜像地址」换成其他兼容 Gravatar 的服务。

> 本主题仅基于爱好修改，使用时请尊重原作者大前端版权。
