<?php

declare(strict_types=1);

/**
 * 在真实 ThinkPHP 项目中测试优化适配器
 */

echo "=== 真实项目优化适配器测试 ===\n";

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

require_once 'vendor/autoload.php';
require_once 'ThinkWorkerOptimizedAdapter.php';

use yangweijie\thinkRuntime\adapter\ThinkWorkerOptimizedAdapter;
use think\App;

echo "项目目录: " . getcwd() . "\n";

// 创建真实的 ThinkPHP 应用
echo "创建 ThinkPHP 应用...\n";
$app = new App();

// 配置优化适配器
$config = [
    'host' => '127.0.0.1',
    'port' => 8086,
    'count' => 1, // 单进程测试
    'sandbox' => [
        'enable' => true,
        'reset_instances' => ['log', 'session', 'view', 'response', 'cookie', 'request'],
        'clone_services' => true,
    ],
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 20,
        'reset_interval' => 50,
        'memory_limit' => '256M',
    ],
];

echo "创建优化适配器...\n";
$adapter = new ThinkWorkerOptimizedAdapter($app, $config);

if (!$adapter->isAvailable()) {
    echo "❌ Workerman 不可用\n";
    exit(1);
}

echo "✅ 优化适配器可用\n";

// 内存基准测试
echo "\n=== 真实应用内存测试 ===\n";

$initialMemory = memory_get_usage(true);
echo "初始内存: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";

// 测试应用快照创建
echo "测试应用快照创建...\n";
$reflection = new ReflectionClass($adapter);

if ($reflection->hasMethod('createAppSnapshot')) {
    $snapshotMethod = $reflection->getMethod('createAppSnapshot');
    $snapshotMethod->setAccessible(true);
    
    $beforeSnapshot = memory_get_usage(true);
    $snapshotMethod->invoke($adapter);
    $afterSnapshot = memory_get_usage(true);
    
    echo "快照创建内存开销: " . round(($afterSnapshot - $beforeSnapshot) / 1024, 2) . " KB\n";
    echo "快照后总内存: " . round($afterSnapshot / 1024 / 1024, 2) . " MB\n";
}

// 测试沙盒应用创建性能
if ($reflection->hasMethod('createSandboxApp')) {
    $sandboxMethod = $reflection->getMethod('createSandboxApp');
    $sandboxMethod->setAccessible(true);
    
    echo "\n测试沙盒应用性能...\n";
    
    $iterations = 50; // 减少迭代次数，因为真实应用更重
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $sandboxApp = $sandboxMethod->invoke($adapter);
        
        // 模拟真实使用
        if ($sandboxApp->has('config')) {
            $sandboxApp->get('config');
        }
        if ($sandboxApp->has('request')) {
            $sandboxApp->get('request');
        }
        
        // 清理
        if ($reflection->hasMethod('cleanupSandboxApp')) {
            $cleanupMethod = $reflection->getMethod('cleanupSandboxApp');
            $cleanupMethod->setAccessible(true);
            $cleanupMethod->invoke($adapter, $sandboxApp);
        }
        
        unset($sandboxApp);
        
        if ($i % 10 === 0) {
            gc_collect_cycles();
            $currentMemory = memory_get_usage(true);
            echo "  迭代 {$i}: " . round($currentMemory / 1024 / 1024, 2) . " MB\n";
        }
    }
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    $duration = ($endTime - $startTime) * 1000;
    $memoryGrowth = $endMemory - $startMemory;
    $avgTime = $duration / $iterations;
    $avgMemory = $memoryGrowth / $iterations;
    
    echo "\n沙盒性能结果:\n";
    echo "总耗时: " . round($duration, 2) . " ms\n";
    echo "平均每次: " . round($avgTime, 3) . " ms\n";
    echo "内存增长: " . round($memoryGrowth / 1024, 2) . " KB\n";
    echo "平均每次内存: " . round($avgMemory / 1024, 3) . " KB\n";
    
    // 性能评估
    if ($avgTime < 5.0) {
        echo "✅ 沙盒性能优秀 (< 5ms)\n";
    } elseif ($avgTime < 20.0) {
        echo "✅ 沙盒性能良好 (< 20ms)\n";
    } else {
        echo "⚠️  沙盒性能需要优化 (> 20ms)\n";
    }
    
    if ($avgMemory < 10) {
        echo "✅ 内存使用优秀 (< 10KB)\n";
    } elseif ($avgMemory < 100) {
        echo "✅ 内存使用良好 (< 100KB)\n";
    } else {
        echo "⚠️  内存使用需要优化 (> 100KB)\n";
    }
}

// 测试实例重置
echo "\n=== 测试真实应用实例重置 ===\n";

if ($reflection->hasMethod('resetAppInstances')) {
    $resetMethod = $reflection->getMethod('resetAppInstances');
    $resetMethod->setAccessible(true);
    
    // 创建测试应用实例
    $testApp = clone $app;
    
    // 检查初始实例
    $initialInstances = [];
    $resetInstances = ['log', 'session', 'view', 'response', 'cookie', 'request'];
    
    foreach ($resetInstances as $instance) {
        $initialInstances[$instance] = $testApp->has($instance);
    }
    
    echo "重置前实例状态:\n";
    foreach ($initialInstances as $name => $exists) {
        echo "  {$name}: " . ($exists ? '✅' : '❌') . "\n";
    }
    
    // 执行重置
    $resetMethod->invoke($adapter, $testApp);
    
    echo "重置后实例状态:\n";
    $resetSuccess = true;
    foreach ($resetInstances as $instance) {
        $exists = $testApp->has($instance);
        echo "  {$instance}: " . ($exists ? '✅' : '❌') . "\n";
        if ($exists && $initialInstances[$instance]) {
            $resetSuccess = false;
        }
    }
    
    if ($resetSuccess) {
        echo "✅ 实例重置机制工作正常\n";
    } else {
        echo "⚠️  部分实例未能正确重置\n";
    }
}

// 性能对比测试
echo "\n=== 真实应用性能对比 ===\n";

// 传统方式测试
echo "测试传统方式（每次创建新应用）...\n";
$traditionalStart = microtime(true);
$traditionalMemStart = memory_get_usage(true);

for ($i = 0; $i < 20; $i++) { // 减少迭代，真实应用创建成本高
    $newApp = new App();
    if (method_exists($newApp, 'initialize')) {
        $newApp->initialize();
    }
    
    // 模拟使用
    if ($newApp->has('config')) {
        $newApp->get('config');
    }
    
    unset($newApp);
    
    if ($i % 5 === 0) {
        gc_collect_cycles();
    }
}

$traditionalEnd = microtime(true);
$traditionalMemEnd = memory_get_usage(true);

$traditionalTime = ($traditionalEnd - $traditionalStart) * 1000;
$traditionalMem = $traditionalMemEnd - $traditionalMemStart;

echo "传统方式结果:\n";
echo "  时间: " . round($traditionalTime, 2) . " ms\n";
echo "  内存: " . round($traditionalMem / 1024, 2) . " KB\n";
echo "  平均每次: " . round($traditionalTime / 20, 2) . " ms\n";

// 优化方式测试
echo "\n测试优化方式（clone + 重置）...\n";
$optimizedStart = microtime(true);
$optimizedMemStart = memory_get_usage(true);

$baseApp = new App();
if (method_exists($baseApp, 'initialize')) {
    $baseApp->initialize();
}

for ($i = 0; $i < 20; $i++) {
    $clonedApp = clone $baseApp;
    
    // 模拟重置
    foreach (['log', 'session', 'view', 'response', 'cookie', 'request'] as $instance) {
        if ($clonedApp->has($instance)) {
            $clonedApp->delete($instance);
        }
    }
    
    // 模拟使用
    if ($clonedApp->has('config')) {
        $clonedApp->get('config');
    }
    
    unset($clonedApp);
    
    if ($i % 5 === 0) {
        gc_collect_cycles();
    }
}

$optimizedEnd = microtime(true);
$optimizedMemEnd = memory_get_usage(true);

$optimizedTime = ($optimizedEnd - $optimizedStart) * 1000;
$optimizedMem = $optimizedMemEnd - $optimizedMemStart;

echo "优化方式结果:\n";
echo "  时间: " . round($optimizedTime, 2) . " ms\n";
echo "  内存: " . round($optimizedMem / 1024, 2) . " KB\n";
echo "  平均每次: " . round($optimizedTime / 20, 2) . " ms\n";

// 计算改善
$timeImprovement = $traditionalTime > 0 ? (($traditionalTime - $optimizedTime) / $traditionalTime) * 100 : 0;
$memImprovement = $traditionalMem > 0 ? (($traditionalMem - $optimizedMem) / $traditionalMem) * 100 : 0;

echo "\n性能改善:\n";
echo "时间提升: " . round($timeImprovement, 1) . "%\n";
echo "内存节省: " . round($memImprovement, 1) . "%\n";

if ($timeImprovement > 30) {
    echo "🚀 时间性能显著提升！\n";
} elseif ($timeImprovement > 10) {
    echo "✅ 时间性能明显提升\n";
} elseif ($timeImprovement > 0) {
    echo "✅ 时间性能有所提升\n";
} else {
    echo "❌ 时间性能无改善\n";
}

if ($memImprovement > 20) {
    echo "🚀 内存使用显著改善！\n";
} elseif ($memImprovement > 5) {
    echo "✅ 内存使用明显改善\n";
} elseif ($memImprovement > 0) {
    echo "✅ 内存使用有所改善\n";
} else {
    echo "❌ 内存使用无改善\n";
}

// 最终状态
$finalMemory = memory_get_usage(true);
echo "\n=== 最终状态 ===\n";
echo "最终内存: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
echo "总内存增长: " . round(($finalMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";

// 预期性能提升
echo "\n=== 预期性能提升 ===\n";
echo "基于测试结果，在真实 Workerman 环境中预期:\n";

if ($timeImprovement > 20) {
    echo "🎯 QPS 提升: 20-40% (从 870 提升到 1000-1200)\n";
} elseif ($timeImprovement > 10) {
    echo "🎯 QPS 提升: 10-20% (从 870 提升到 950-1050)\n";
} else {
    echo "🎯 QPS 提升: 有限，需要进一步优化\n";
}

if ($memImprovement > 10) {
    echo "💾 内存稳定性: 显著改善\n";
} else {
    echo "💾 内存稳定性: 基本稳定\n";
}

echo "\n✅ 真实项目优化适配器测试完成！\n";
echo "\n下一步: 启动优化适配器进行实际压测\n";
