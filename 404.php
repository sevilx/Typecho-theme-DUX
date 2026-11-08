<?php
/**
 * 404 独立页：不加载主题公共模板，零 $this / 零 functions.php 依赖。
 *
 * Typecho 1.3 的 404 由异常处理器渲染，Archive 上下文未完整初始化；
 * header.php 里的 archiveTitle() / header() / 自定义函数可能抛 Fatal，
 * 异常处理器无法二次兜底，display_errors=Off 时输出缓冲被丢弃 → 空 body。
 * 本页只依赖 PHP 标准函数，保证任何渲染上下文都能稳定输出响应体。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!headers_sent()) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');
}

// 不硬编码域名，也不依赖 Widget/Options（404 异常路径下未必可用）。
$duxScript = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/';
$duxBase = rtrim(str_replace('\\', '/', dirname($duxScript)), '/');
$duxHome = ($duxBase === '' || $duxBase === '.' || $duxBase === '/')
    ? '/'
    : htmlspecialchars($duxBase . '/', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>404 Page Not Found</title>
</head>
<body>
<main style="max-width:32rem;margin:10vh auto;padding:0 1.5rem;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;line-height:1.7;color-scheme:light dark">
<h1>404 Page Not Found</h1>
<p>你访问的页面不存在，可能已被删除或链接有误。</p>
<p><a href="<?php echo $duxHome; ?>">返回首页</a></p>
</main>
</body>
</html>
