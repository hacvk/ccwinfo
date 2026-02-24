<?php
define('APP_PATH', __DIR__ . '/application/');
define('BIND_MODULE', 'index');
define('APP_DEBUG', true);
define('RUNTIME_PATH', __DIR__ . '/runtime/');

// 加载框架引导文件
require __DIR__ . '/thinkphp/start.php';

// 测试访问 /guide/1-2026lddlll.html 路由
try {
    $app = new \think\App();
    $request = \think\Request::instance();
    $request->pathinfo('guide/1-2026lddlll.html');
    $app->initialize();
    $app->run($request);
} catch (Exception $e) {
    echo '错误信息：' . $e->getMessage() . "\n";
    echo '错误文件：' . $e->getFile() . ' (第 ' . $e->getLine() . ' 行)' . "\n";
    echo '错误追踪：' . $e->getTraceAsString() . "\n";
}
?>