<?php

declare(strict_types=1);

/**
 * 检测 ThinkPHP 调试模式和 think-trace 状态
 */

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

require_once 'vendor/autoload.php';

use think\App;

echo "=== ThinkPHP 调试模式检测 ===\n";

// 创建 ThinkPHP 应用
$app = new App();

// 初始化应用以加载配置
if (method_exists($app, 'initialize')) {
    $app->initialize();
}

echo "1. 环境变量检测:\n";
// 使用 getenv 而不是 env 函数
$appDebugEnv = getenv('APP_DEBUG') ?: 'false';
$appEnv = getenv('APP_ENV') ?: 'production';
echo "   APP_DEBUG: " . ($appDebugEnv === 'true' ? '✅ true' : '❌ false') . "\n";
echo "   APP_ENV: " . $appEnv . "\n";

echo "\n2. 配置检测:\n";
if ($app->has('config')) {
    $config = $app->config;
    echo "   app.debug: " . ($config->get('app.debug') ? '✅ true' : '❌ false') . "\n";
    echo "   trace.enable: " . ($config->get('trace.enable') ? '✅ true' : '❌ false') . "\n";
    echo "   app.trace: " . ($config->get('app.trace') ? '✅ true' : '❌ false') . "\n";
} else {
    echo "   ❌ 无法获取配置\n";
}

echo "\n3. think-trace 检测:\n";
if ($app->has('trace')) {
    echo "   trace 服务: ✅ 已注册\n";
    try {
        $trace = $app->trace;
        echo "   trace 实例: " . get_class($trace) . "\n";
        
        // 检查 trace 是否启用
        $reflection = new ReflectionClass($trace);
        if ($reflection->hasProperty('config')) {
            $configProperty = $reflection->getProperty('config');
            $configProperty->setAccessible(true);
            $traceConfig = $configProperty->getValue($trace);
            echo "   trace 配置: " . json_encode($traceConfig) . "\n";
        }
    } catch (Exception $e) {
        echo "   trace 检测失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "   trace 服务: ❌ 未注册\n";
}

echo "\n4. 调试相关常量:\n";
echo "   THINK_VERSION: " . (defined('THINK_VERSION') ? THINK_VERSION : '未定义') . "\n";
echo "   APP_PATH: " . (defined('APP_PATH') ? APP_PATH : '未定义') . "\n";

echo "\n5. 检测调试模式的方法:\n";

// 方法1: 通过环境变量
$debugByEnv = getenv('APP_DEBUG') === 'true';
echo "   方法1 (环境变量): " . ($debugByEnv ? '✅ 调试模式' : '❌ 生产模式') . "\n";

// 方法2: 通过配置
$debugByConfig = false;
if ($app->has('config')) {
    $debugByConfig = $app->config->get('app.debug', false);
}
echo "   方法2 (配置文件): " . ($debugByConfig ? '✅ 调试模式' : '❌ 生产模式') . "\n";

// 方法3: 通过应用方法
$debugByApp = false;
if (method_exists($app, 'isDebug')) {
    $debugByApp = $app->isDebug();
    echo "   方法3 (应用方法): " . ($debugByApp ? '✅ 调试模式' : '❌ 生产模式') . "\n";
} else {
    echo "   方法3 (应用方法): ❌ 方法不存在\n";
}

// 综合判断
$isDebugMode = $debugByEnv || $debugByConfig || $debugByApp;
echo "\n📊 综合判断: " . ($isDebugMode ? '🔧 当前为调试模式' : '🚀 当前为生产模式') . "\n";

echo "\n6. think-worker 智能检测实现:\n";
echo "think-worker 的智能检测机制应该是:\n";
echo "```php\n";
echo "// 检测调试模式\n";
echo "\$isDebug = (getenv('APP_DEBUG') === 'true') || \$app->config->get('app.debug', false);\n";
echo "\n";
echo "// 根据调试模式决定是否启用 think-trace\n";
echo "if (!\$isDebug && \$app->has('trace')) {\n";
echo "    // 禁用 think-trace\n";
echo "    \$app->delete('trace');\n";
echo "    // 或者设置配置\n";
echo "    \$app->config->set('trace.enable', false);\n";
echo "}\n";
echo "```\n";

echo "\n7. 建议的优化策略:\n";
if ($isDebugMode) {
    echo "⚠️  当前为调试模式，建议:\n";
    echo "   1. 设置 APP_DEBUG=false\n";
    echo "   2. 修改 config/app.php 中的 debug => false\n";
    echo "   3. 修改 config/trace.php 中的 enable => false\n";
} else {
    echo "✅ 当前为生产模式，但仍需检查:\n";
    echo "   1. think-trace 是否完全禁用\n";
    echo "   2. 其他调试工具是否关闭\n";
}

echo "\n8. 实际测试 think-trace 影响:\n";

// 测试 think-trace 的性能影响
$iterations = 1000;

// 启用 trace 的测试
if ($app->has('trace')) {
    echo "测试启用 think-trace 的性能...\n";
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        // 模拟请求处理
        $app->make('request');
        if ($i % 100 === 0) {
            gc_collect_cycles();
        }
    }
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    $traceTime = ($endTime - $startTime) * 1000;
    $traceMemory = $endMemory - $startMemory;
    
    echo "   启用 trace - 时间: " . round($traceTime, 2) . "ms, 内存: " . round($traceMemory / 1024, 2) . "KB\n";
}

// 禁用 trace 的测试
if ($app->has('trace')) {
    $app->delete('trace');
}

echo "测试禁用 think-trace 的性能...\n";
$startTime = microtime(true);
$startMemory = memory_get_usage(true);

for ($i = 0; $i < $iterations; $i++) {
    // 模拟请求处理
    $app->make('request');
    if ($i % 100 === 0) {
        gc_collect_cycles();
    }
}

$endTime = microtime(true);
$endMemory = memory_get_usage(true);

$noTraceTime = ($endTime - $startTime) * 1000;
$noTraceMemory = $endMemory - $startMemory;

echo "   禁用 trace - 时间: " . round($noTraceTime, 2) . "ms, 内存: " . round($noTraceMemory / 1024, 2) . "KB\n";

// 计算影响
if (isset($traceTime)) {
    $timeImpact = $traceTime - $noTraceTime;
    $memoryImpact = $traceMemory - $noTraceMemory;
    
    echo "\n📈 think-trace 性能影响:\n";
    echo "   时间开销: " . round($timeImpact, 2) . "ms (" . round(($timeImpact / $noTraceTime) * 100, 1) . "%)\n";
    echo "   内存开销: " . round($memoryImpact / 1024, 2) . "KB (" . round(($memoryImpact / $noTraceMemory) * 100, 1) . "%)\n";
    
    if ($timeImpact > 10) {
        echo "   🚨 think-trace 对性能影响较大，建议在生产环境禁用\n";
    } else {
        echo "   ✅ think-trace 性能影响可接受\n";
    }
}

echo "\n✅ 调试模式检测完成！\n";
