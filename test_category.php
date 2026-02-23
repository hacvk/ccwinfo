<?php
define('APP_PATH', __DIR__ . '/application/');
define('BIND_MODULE', 'index');
define('APP_DEBUG', true);
define('RUNTIME_PATH', __DIR__ . '/runtime/');

use app\common\model\GuideCategory;

// 加载框架引导文件
require __DIR__ . '/thinkphp/start.php';

try {
    // 测试获取分类数据
    echo "开始测试获取 GuideCategory 数据\n";
    echo "-----------------------------------\n";

    $rows = GuideCategory::where('status', 'normal')->order('weigh desc,id asc')->select();
    echo "获取到的分类数量: " . count($rows) . "\n";

    echo "\n详细数据:\n";
    foreach ($rows as $row) {
        $row = $row->toArray();
        echo sprintf(
            "ID: %d | 名称: %s | 状态: %s | PID: %d\n",
            $row['id'], $row['name'], $row['status'], $row['pid']
        );
    }

    echo "\n\n测试完成\n";

} catch (Exception $e) {
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . " (第 " . $e->getLine() . " 行)\n";
    echo "错误追踪:\n" . $e->getTraceAsString() . "\n";
}
?>