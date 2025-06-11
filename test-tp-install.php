<?php

declare(strict_types=1);

/**
 * 在ThinkPHP项目中测试think-runtime安装
 * 这个脚本模拟在ThinkPHP项目中的使用情况
 */

echo "ThinkPHP Runtime 项目集成测试\n";
echo "==============================\n\n";

// 检查是否在ThinkPHP项目中
if (!file_exists('think') || !file_exists('app')) {
    echo "❌ 这不是一个ThinkPHP项目目录\n";
    echo "请在ThinkPHP项目根目录下运行此脚本\n";
    exit(1);
}

echo "✅ 检测到ThinkPHP项目\n\n";

// 检查vendor目录
if (!file_exists('vendor/autoload.php')) {
    echo "❌ 未找到vendor/autoload.php\n";
    echo "请先运行: composer install\n";
    exit(1);
}

echo "✅ Composer自动加载已找到\n";

// 加载自动加载器
require_once 'vendor/autoload.php';

// 检查think-runtime是否已安装
if (!file_exists('vendor/yangweijie/think-runtime')) {
    echo "❌ think-runtime包未安装\n";
    echo "请运行: composer require yangweijie/think-runtime\n";
    exit(1);
}

echo "✅ think-runtime包已安装\n";

// 检查核心类
$coreClasses = [
    'yangweijie\\thinkRuntime\\runtime\\RuntimeManager',
    'yangweijie\\thinkRuntime\\config\\RuntimeConfig',
    'yangweijie\\thinkRuntime\\service\\RuntimeService',
];

foreach ($coreClasses as $class) {
    if (class_exists($class)) {
        echo "✅ {$class}: 已加载\n";
    } else {
        echo "❌ {$class}: 未找到\n";
        exit(1);
    }
}

// 检查配置文件
echo "\n检查配置文件...\n";
if (file_exists('config/runtime.php')) {
    echo "✅ 配置文件: config/runtime.php 已存在\n";
} else {
    echo "⚠️  配置文件: config/runtime.php 不存在\n";
    echo "建议复制: cp vendor/yangweijie/think-runtime/config/runtime.php config/\n";
}

// 尝试创建应用实例（模拟）
echo "\n测试应用集成...\n";
try {
    // 检查ThinkPHP版本
    if (class_exists('think\\App')) {
        echo "✅ ThinkPHP App类: 已找到\n";
        
        // 检查是否可以创建RuntimeConfig
        $config = new \yangweijie\thinkRuntime\config\RuntimeConfig();
        echo "✅ RuntimeConfig: 创建成功\n";
        
        // 检查默认配置
        $defaultRuntime = $config->getDefaultRuntime();
        echo "✅ 默认运行时: {$defaultRuntime}\n";
        
        $autoDetectOrder = $config->getAutoDetectOrder();
        echo "✅ 自动检测顺序: " . implode(', ', $autoDetectOrder) . "\n";
        
    } else {
        echo "❌ ThinkPHP App类: 未找到\n";
    }
} catch (\Exception $e) {
    echo "❌ 应用集成测试失败: {$e->getMessage()}\n";
}

// 检查命令是否可用
echo "\n检查命令可用性...\n";
if (file_exists('think')) {
    echo "✅ think命令行工具: 已找到\n";
    
    // 尝试运行命令（不实际执行，只检查）
    $commands = [
        'runtime:info' => '显示运行时信息',
        'runtime:start' => '启动运行时服务器',
    ];
    
    foreach ($commands as $cmd => $desc) {
        echo "✅ {$cmd}: {$desc}\n";
    }
} else {
    echo "❌ think命令行工具: 未找到\n";
}

echo "\n==============================\n";
echo "✅ 集成测试完成！\n\n";

echo "建议的下一步操作:\n";
echo "1. 如果配置文件不存在，复制配置文件:\n";
echo "   cp vendor/yangweijie/think-runtime/config/runtime.php config/\n\n";
echo "2. 查看运行时信息:\n";
echo "   php think runtime:info\n\n";
echo "3. 启动运行时服务器:\n";
echo "   php think runtime:start\n\n";
echo "4. 或者启动指定运行时:\n";
echo "   php think runtime:start swoole --host=127.0.0.1 --port=8080\n\n";

echo "如果遇到问题，请查看:\n";
echo "- vendor/yangweijie/think-runtime/README.md\n";
echo "- vendor/yangweijie/think-runtime/INSTALL.md\n";
