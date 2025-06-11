<?php

declare(strict_types=1);

/**
 * ThinkPHP Runtime 安装检查脚本
 * 用于验证安装是否正确
 */

echo "ThinkPHP Runtime 安装检查\n";
echo "========================\n\n";

// 检查PHP版本
echo "1. 检查PHP版本...\n";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo "   ✅ PHP版本: " . PHP_VERSION . " (满足要求 >= 8.0)\n";
} else {
    echo "   ❌ PHP版本: " . PHP_VERSION . " (需要 >= 8.0)\n";
    exit(1);
}

// 检查必需的扩展
echo "\n2. 检查必需的PHP扩展...\n";
$requiredExtensions = ['json', 'mbstring', 'curl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ {$ext}: 已加载\n";
    } else {
        echo "   ❌ {$ext}: 未加载 (必需)\n";
        exit(1);
    }
}

// 检查可选的扩展
echo "\n3. 检查可选的PHP扩展...\n";
$optionalExtensions = [
    'swoole' => 'Swoole运行时支持',
    'openssl' => 'SSL/TLS支持',
    'pcntl' => '进程控制支持',
];
foreach ($optionalExtensions as $ext => $desc) {
    if (extension_loaded($ext)) {
        echo "   ✅ {$ext}: 已加载 ({$desc})\n";
    } else {
        echo "   ⚠️  {$ext}: 未加载 ({$desc})\n";
    }
}

// 检查Composer自动加载
echo "\n4. 检查Composer自动加载...\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "   ✅ Composer自动加载: 已找到\n";
} else {
    echo "   ❌ Composer自动加载: 未找到 vendor/autoload.php\n";
    echo "   请运行: composer install\n";
    exit(1);
}

// 检查核心类
echo "\n5. 检查核心类...\n";
$coreClasses = [
    'yangweijie\\thinkRuntime\\runtime\\RuntimeManager',
    'yangweijie\\thinkRuntime\\config\\RuntimeConfig',
    'yangweijie\\thinkRuntime\\service\\RuntimeService',
    'yangweijie\\thinkRuntime\\command\\RuntimeStartCommand',
    'yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand',
];

foreach ($coreClasses as $class) {
    if (class_exists($class)) {
        echo "   ✅ {$class}: 已加载\n";
    } else {
        echo "   ❌ {$class}: 未找到\n";
        exit(1);
    }
}

// 检查Symfony Console
echo "\n6. 检查Symfony Console...\n";
if (class_exists('Symfony\\Component\\Console\\Command\\Command')) {
    echo "   ✅ Symfony Console: 已加载\n";
} else {
    echo "   ❌ Symfony Console: 未找到\n";
    echo "   请运行: composer require symfony/console\n";
    exit(1);
}

// 检查配置文件
echo "\n7. 检查配置文件...\n";
if (file_exists(__DIR__ . '/config/runtime.php')) {
    echo "   ✅ 配置文件: config/runtime.php 已找到\n";

    $config = include __DIR__ . '/config/runtime.php';
    if (is_array($config)) {
        echo "   ✅ 配置格式: 有效\n";

        if (isset($config['default'])) {
            echo "   ✅ 默认运行时: {$config['default']}\n";
        }

        if (isset($config['auto_detect_order']) && is_array($config['auto_detect_order'])) {
            echo "   ✅ 自动检测顺序: " . implode(', ', $config['auto_detect_order']) . "\n";
        }
    } else {
        echo "   ❌ 配置格式: 无效\n";
    }
} else {
    echo "   ⚠️  配置文件: 未找到 (将使用默认配置)\n";
}

// 检查运行时可用性
echo "\n8. 检查运行时可用性...\n";
try {
    $config = new \yangweijie\thinkRuntime\config\RuntimeConfig();

    $runtimes = [
        'fpm' => '传统PHP-FPM',
        'swoole' => 'Swoole高性能服务器',
        'frankenphp' => 'FrankenPHP现代服务器',
        'reactphp' => 'ReactPHP事件驱动服务器',
        'ripple' => 'Ripple协程服务器',
        'roadrunner' => 'RoadRunner高性能服务器',
    ];

    foreach ($runtimes as $name => $desc) {
        try {
            // 这里我们只检查类是否存在，不实际创建实例
            $adapterClass = "yangweijie\\thinkRuntime\\adapter\\" . ucfirst($name) . "Adapter";
            if (class_exists($adapterClass)) {
                echo "   ✅ {$name}: {$desc} - 适配器已加载\n";
            } else {
                echo "   ❌ {$name}: {$desc} - 适配器未找到\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ {$name}: {$desc} - 错误: {$e->getMessage()}\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ 运行时检查失败: {$e->getMessage()}\n";
}

echo "\n========================\n";
echo "✅ 安装检查完成！\n\n";

echo "下一步:\n";
echo "1. 复制配置文件到你的ThinkPHP项目: cp config/runtime.php /path/to/your/thinkphp/config/\n";
echo "2. 在ThinkPHP项目中运行: php think runtime:info\n";
echo "3. 启动运行时服务器: php think runtime:start\n\n";

echo "如果遇到问题，请查看README.md中的故障排除部分。\n";
