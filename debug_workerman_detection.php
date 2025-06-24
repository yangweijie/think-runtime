<?php

declare(strict_types=1);

/**
 * Workerman 运行时检测调试脚本
 * 用于诊断为什么 think-runtime 检测不到 workerman
 */

echo "=== Workerman 运行时检测调试 ===\n\n";

// 1. 检查 Composer 自动加载
echo "1. 检查 Composer 自动加载...\n";
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        echo "   ✅ 找到自动加载文件: $path\n";
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    echo "   ❌ 未找到 Composer 自动加载文件\n";
    echo "   请确保在项目根目录运行 composer install\n";
    exit(1);
}

// 2. 检查 workerman/workerman 包是否安装
echo "\n2. 检查 workerman/workerman 包安装状态...\n";

$composerLockPath = dirname($path) . '/composer.lock';
if (file_exists($composerLockPath)) {
    $composerLock = json_decode(file_get_contents($composerLockPath), true);
    $workermanFound = false;
    
    if (isset($composerLock['packages'])) {
        foreach ($composerLock['packages'] as $package) {
            if ($package['name'] === 'workerman/workerman') {
                echo "   ✅ workerman/workerman 已安装，版本: {$package['version']}\n";
                $workermanFound = true;
                break;
            }
        }
    }
    
    if (!$workermanFound) {
        echo "   ❌ workerman/workerman 未在 composer.lock 中找到\n";
        echo "   请运行: composer require workerman/workerman\n";
    }
} else {
    echo "   ⚠️  composer.lock 文件不存在\n";
}

// 3. 检查 Workerman\Worker 类是否可以加载
echo "\n3. 检查 Workerman\\Worker 类...\n";

// 尝试不同的类名引用方式
$classVariants = [
    'Workerman\\Worker',
    '\\Workerman\\Worker',
    'Worker',
];

$classFound = false;
foreach ($classVariants as $className) {
    if (class_exists($className)) {
        echo "   ✅ 类 $className 存在\n";
        $classFound = true;
        
        // 获取类信息
        $reflection = new ReflectionClass($className);
        echo "   - 类文件路径: " . $reflection->getFileName() . "\n";
        echo "   - 命名空间: " . $reflection->getNamespaceName() . "\n";
        break;
    } else {
        echo "   ❌ 类 $className 不存在\n";
    }
}

// 4. 检查是否有 use 语句问题
echo "\n4. 检查命名空间导入...\n";
try {
    // 尝试手动导入
    if (!$classFound) {
        echo "   尝试手动加载 Workerman 类...\n";
        
        // 查找可能的 Workerman 文件
        $vendorDir = dirname($path);
        $workermanPaths = [
            $vendorDir . '/workerman/workerman/Worker.php',
            $vendorDir . '/workerman/workerman/src/Worker.php',
        ];
        
        foreach ($workermanPaths as $workermanPath) {
            if (file_exists($workermanPath)) {
                echo "   ✅ 找到 Workerman 文件: $workermanPath\n";
                require_once $workermanPath;
                
                // 再次检查类
                if (class_exists('Workerman\\Worker')) {
                    echo "   ✅ 手动加载后 Workerman\\Worker 类可用\n";
                    $classFound = true;
                }
                break;
            }
        }
    }
} catch (Exception $e) {
    echo "   ❌ 手动加载失败: " . $e->getMessage() . "\n";
}

// 5. 模拟 think-runtime 的检测逻辑
echo "\n5. 模拟 think-runtime 检测逻辑...\n";

// 这是 think-runtime 中实际使用的检测方法
$isSupported = class_exists('Workerman\\Worker');
echo "   class_exists('Workerman\\\\Worker'): " . ($isSupported ? '✅ true' : '❌ false') . "\n";

// 尝试其他可能的检测方式
$alternativeChecks = [
    "class_exists('\\\\Workerman\\\\Worker')" => class_exists('\\Workerman\\Worker'),
    "class_exists('Worker')" => class_exists('Worker'),
];

foreach ($alternativeChecks as $check => $result) {
    echo "   $check: " . ($result ? '✅ true' : '❌ false') . "\n";
}

// 6. 提供解决方案
echo "\n6. 解决方案建议...\n";

if (!$classFound) {
    echo "   ❌ Workerman 类未找到，建议解决方案：\n";
    echo "   \n";
    echo "   方案 1: 重新安装 workerman\n";
    echo "   composer remove workerman/workerman\n";
    echo "   composer require workerman/workerman\n";
    echo "   \n";
    echo "   方案 2: 清理并重新安装依赖\n";
    echo "   rm -rf vendor/ composer.lock\n";
    echo "   composer install\n";
    echo "   \n";
    echo "   方案 3: 检查 PHP 版本兼容性\n";
    echo "   php -v\n";
    echo "   (Workerman 需要 PHP >= 7.0)\n";
} else {
    echo "   ✅ Workerman 类检测正常\n";
    echo "   \n";
    echo "   如果 think-runtime 仍然提示不可用，可能的原因：\n";
    echo "   1. think-runtime 版本过旧，请更新到最新版本\n";
    echo "   2. 缓存问题，尝试清理 ThinkPHP 缓存\n";
    echo "   3. 自定义适配器配置问题\n";
}

// 7. 系统信息
echo "\n7. 系统信息...\n";
echo "   PHP 版本: " . PHP_VERSION . "\n";
echo "   操作系统: " . PHP_OS . "\n";
echo "   SAPI: " . PHP_SAPI . "\n";
echo "   内存限制: " . ini_get('memory_limit') . "\n";

echo "\n=== 调试完成 ===\n";
