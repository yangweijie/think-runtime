<?php

declare(strict_types=1);

/**
 * 测试运行脚本
 * 用于验证所有测试是否正常工作
 */

echo "=== ThinkPHP Runtime 测试验证 ===\n\n";

// 检查PHP版本
echo "1. 检查PHP版本\n";
echo "   PHP版本: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    echo "   ❌ PHP版本过低，需要8.0或更高版本\n";
    exit(1);
}
echo "   ✅ PHP版本符合要求\n\n";

// 检查必需的扩展
echo "2. 检查必需扩展\n";
$requiredExtensions = ['json', 'mbstring', 'curl'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ {$ext} 扩展已加载\n";
    } else {
        echo "   ❌ {$ext} 扩展未加载\n";
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "   缺少必需扩展: " . implode(', ', $missingExtensions) . "\n";
    exit(1);
}
echo "\n";

// 检查可选扩展
echo "3. 检查可选扩展（运行时支持）\n";
$optionalExtensions = [
    'swoole' => 'Swoole高性能网络框架',
    'openssl' => 'SSL/TLS支持',
    'pcntl' => '进程控制',
    'posix' => 'POSIX函数',
];

foreach ($optionalExtensions as $ext => $description) {
    if (extension_loaded($ext)) {
        echo "   ✅ {$ext} - {$description}\n";
    } else {
        echo "   ⚠️  {$ext} - {$description} (可选)\n";
    }
}
echo "\n";

// 检查Composer依赖
echo "4. 检查Composer依赖\n";
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "   ❌ Composer依赖未安装，请运行: composer install\n";
    exit(1);
}
echo "   ✅ Composer依赖已安装\n\n";

// 加载自动加载器
require_once __DIR__ . '/vendor/autoload.php';

// 检查核心类
echo "5. 检查核心类\n";
$coreClasses = [
    'yangweijie\\thinkRuntime\\config\\RuntimeConfig',
    'yangweijie\\thinkRuntime\\runtime\\RuntimeManager',
    'yangweijie\\thinkRuntime\\runtime\\AbstractRuntime',
    'yangweijie\\thinkRuntime\\contract\\AdapterInterface',
    'yangweijie\\thinkRuntime\\contract\\RuntimeInterface',
];

foreach ($coreClasses as $class) {
    if (class_exists($class) || interface_exists($class)) {
        $shortName = substr($class, strrpos($class, '\\') + 1);
        echo "   ✅ {$shortName}\n";
    } else {
        echo "   ❌ {$class} 类不存在\n";
        exit(1);
    }
}
echo "\n";

// 检查适配器类
echo "6. 检查适配器类\n";
$adapterClasses = [
    'yangweijie\\thinkRuntime\\adapter\\SwooleAdapter',
    'yangweijie\\thinkRuntime\\adapter\\FrankenphpAdapter',
    'yangweijie\\thinkRuntime\\adapter\\ReactphpAdapter',
    'yangweijie\\thinkRuntime\\adapter\\RippleAdapter',
    'yangweijie\\thinkRuntime\\adapter\\RoadRunnerAdapter',
];

foreach ($adapterClasses as $class) {
    if (class_exists($class)) {
        $shortName = substr($class, strrpos($class, '\\') + 1);
        echo "   ✅ {$shortName}\n";
    } else {
        $shortName = substr($class, strrpos($class, '\\') + 1);
        echo "   ⚠️  {$shortName} (可能未实现)\n";
    }
}
echo "\n";

// 检查测试文件
echo "7. 检查测试文件\n";
$testFiles = [
    'tests/TestCase.php',
    'tests/Pest.php',
    'tests/Unit/RuntimeConfigTest.php',
    'tests/Unit/RuntimeManagerTest.php',
    'tests/Unit/RuntimeInfoCommandTest.php',
    'tests/Feature/SwooleAdapterTest.php',
    'tests/Feature/FrankenphpAdapterTest.php',
    'tests/Feature/ReactphpAdapterTest.php',
    'tests/Feature/RippleAdapterTest.php',
    'tests/Feature/RoadRunnerAdapterTest.php',
    'tests/Performance/RuntimePerformanceTest.php',
];

$missingTestFiles = [];
foreach ($testFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✅ " . basename($file) . "\n";
    } else {
        echo "   ❌ " . basename($file) . " 不存在\n";
        $missingTestFiles[] = $file;
    }
}

if (!empty($missingTestFiles)) {
    echo "   缺少测试文件: " . implode(', ', array_map('basename', $missingTestFiles)) . "\n";
}
echo "\n";

// 运行基本功能测试
echo "8. 运行基本功能测试\n";

try {
    // 测试配置类
    echo "   测试RuntimeConfig...\n";
    $config = new \yangweijie\thinkRuntime\config\RuntimeConfig();
    $defaultRuntime = $config->getDefaultRuntime();
    echo "     ✅ 默认运行时: {$defaultRuntime}\n";
    
    // 测试运行时检测顺序
    $autoDetectOrder = $config->getAutoDetectOrder();
    echo "     ✅ 自动检测顺序: " . implode(', ', $autoDetectOrder) . "\n";
    
    // 测试获取运行时配置
    $swooleConfig = $config->getRuntimeConfig('swoole');
    echo "     ✅ Swoole配置获取成功\n";
    
} catch (\Throwable $e) {
    echo "   ❌ 基本功能测试失败: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// 检查测试框架
echo "9. 检查测试框架\n";
if (class_exists('\\Pest\\TestSuite')) {
    echo "   ✅ Pest测试框架已安装\n";
} else {
    echo "   ⚠️  Pest测试框架未安装，将使用PHPUnit\n";
}

if (class_exists('\\PHPUnit\\Framework\\TestCase')) {
    echo "   ✅ PHPUnit测试框架已安装\n";
} else {
    echo "   ❌ PHPUnit测试框架未安装\n";
    exit(1);
}
echo "\n";

// 运行测试建议
echo "10. 运行测试建议\n";
echo "   运行所有测试:\n";
echo "     composer test\n";
echo "     或者: ./vendor/bin/pest\n";
echo "     或者: ./vendor/bin/phpunit\n\n";

echo "   运行特定测试套件:\n";
echo "     ./vendor/bin/pest tests/Unit\n";
echo "     ./vendor/bin/pest tests/Feature\n";
echo "     ./vendor/bin/phpunit --testsuite=Performance\n\n";

echo "   运行覆盖率测试:\n";
echo "     ./vendor/bin/pest --coverage\n";
echo "     或者: ./vendor/bin/phpunit --coverage-html coverage\n\n";

// 环境信息总结
echo "=== 环境信息总结 ===\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "操作系统: " . PHP_OS . "\n";
echo "架构: " . php_uname('m') . "\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";
echo "最大执行时间: " . ini_get('max_execution_time') . "s\n";

// 运行时支持检测
echo "\n=== 运行时支持检测 ===\n";
$runtimeSupport = [
    'Swoole' => extension_loaded('swoole'),
    'FrankenPHP' => isset($_SERVER['FRANKENPHP_VERSION']),
    'ReactPHP' => class_exists('React\\EventLoop\\Loop'),
    'Ripple' => class_exists('Ripple\\Http\\Server') && version_compare(PHP_VERSION, '8.1.0', '>='),
    'RoadRunner' => isset($_SERVER['RR_MODE']),
];

foreach ($runtimeSupport as $runtime => $supported) {
    $status = $supported ? '✅ 支持' : '❌ 不支持';
    echo "{$runtime}: {$status}\n";
}

echo "\n✅ 测试环境检查完成！\n";
echo "现在可以运行测试了。建议先运行: composer test\n";
