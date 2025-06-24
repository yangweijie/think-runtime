<?php

declare(strict_types=1);

/**
 * 测试 Workerman 运行时是否可用
 */

// 引入自动加载
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;
use yangweijie\thinkRuntime\config\RuntimeConfig;

echo "=== 测试 Workerman 运行时 ===\n\n";

try {
    // 1. 直接测试 Workerman 类
    echo "1. 测试 Workerman 类可用性...\n";
    if (class_exists('Workerman\\Worker')) {
        echo "   ✅ Workerman\\Worker 类可用\n";
    } else {
        echo "   ❌ Workerman\\Worker 类不可用\n";
        exit(1);
    }

    // 2. 创建 ThinkPHP 应用
    echo "\n2. 创建 ThinkPHP 应用...\n";
    $app = new App();
    $app->initialize();
    echo "   ✅ ThinkPHP 应用创建成功\n";

    // 3. 创建运行时配置
    echo "\n3. 创建运行时配置...\n";
    $config = new RuntimeConfig([
        'default' => 'workerman',
        'runtimes' => [
            'workerman' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'count' => 4,
            ]
        ]
    ]);
    echo "   ✅ 运行时配置创建成功\n";

    // 4. 创建运行时管理器
    echo "\n4. 创建运行时管理器...\n";
    $manager = new RuntimeManager($app, $config);
    echo "   ✅ 运行时管理器创建成功\n";

    // 5. 测试运行时检测
    echo "\n5. 测试运行时检测...\n";
    $availableRuntimes = $manager->getAvailableRuntimes();
    echo "   可用运行时: " . implode(', ', $availableRuntimes) . "\n";
    
    if (in_array('workerman', $availableRuntimes)) {
        echo "   ✅ workerman 运行时可用\n";
    } else {
        echo "   ❌ workerman 运行时不可用\n";
        echo "   这可能是 think-runtime 版本或配置问题\n";
    }

    // 6. 获取运行时信息
    echo "\n6. 获取运行时信息...\n";
    $runtimeInfo = $manager->getRuntimeInfo();
    echo "   当前运行时: " . $runtimeInfo['name'] . "\n";
    echo "   运行时可用: " . ($runtimeInfo['available'] ? '是' : '否') . "\n";

    // 7. 尝试获取 workerman 运行时实例
    echo "\n7. 测试获取 workerman 运行时实例...\n";
    try {
        $workermanRuntime = $manager->getRuntime('workerman');
        if ($workermanRuntime) {
            echo "   ✅ workerman 运行时实例获取成功\n";
            echo "   运行时名称: " . $workermanRuntime->getName() . "\n";
            echo "   是否可用: " . ($workermanRuntime->isAvailable() ? '是' : '否') . "\n";
        } else {
            echo "   ❌ workerman 运行时实例获取失败\n";
        }
    } catch (Exception $e) {
        echo "   ❌ 获取 workerman 运行时实例时出错: " . $e->getMessage() . "\n";
    }

    echo "\n✅ 测试完成！workerman 运行时应该可以正常使用了。\n";
    echo "\n现在您可以运行：\n";
    echo "php think runtime:info\n";
    echo "php think runtime:start workerman\n";

} catch (Exception $e) {
    echo "\n❌ 测试过程中出现错误: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n请检查：\n";
    echo "1. think-runtime 是否正确安装\n";
    echo "2. ThinkPHP 版本是否兼容\n";
    echo "3. 配置文件是否正确\n";
}
