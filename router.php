<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// 禁止访问 plugins 和 includes 目录
if (preg_match('#^/(plugins|includes)/#', $path)) {
    http_response_code(403);
    exit('403 Forbidden');
}

// /pay/xxx -> pay.php?s=xxx
if (preg_match('#^/pay/(.*)$#', $path, $m)) {
    $_GET['s'] = $m[1];
    $_SERVER['PHP_SELF'] = '/pay.php';
    require __DIR__ . '/pay.php';
    exit;
}

// /api/xxx -> api.php?s=xxx
if (preg_match('#^/api/(.*)$#', $path, $m)) {
    $_GET['s'] = $m[1];
    $_SERVER['PHP_SELF'] = '/api.php';
    require __DIR__ . '/api.php';
    exit;
}

// /xxx.html -> index.php?mod=xxx
if (preg_match('#^/([\w\-]+)\.html$#', $path, $m)) {
    $_GET['mod'] = $m[1];
    require __DIR__ . '/index.php';
    exit;
}

// /doc/xxx.html -> index.php?doc=xxx
if (preg_match('#^/doc/([\w\-]+)\.html$#', $path, $m)) {
    $_GET['doc'] = $m[1];
    require __DIR__ . '/index.php';
    exit;
}

// 静态文件直接返回
if ($path !== '/' && file_exists(__DIR__ . $path)) {
    return false;
}

// 其他请求交给 index.php
require __DIR__ . '/index.php';
