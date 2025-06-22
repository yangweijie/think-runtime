<?php

declare(strict_types=1);

/**
 * 启动智能优化的 Workerman 服务
 */

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

require_once 'vendor/autoload.php';
require_once 'SmartOptimizedAdapter.php';

use yangweijie\thinkRuntime\adapter\SmartOptimizedAdapter;
use think\App;

echo "=== 启动智能优化的 Workerman 服务 ===\n";

// 创建 ThinkPHP 应用
$app = new App();

// 智能优化配置
$config = [
    'host' => '127.0.0.1',
    'port' => 8089,
    'count' => 4,
    'name' => 'ThinkPHP-Smart-Optimized',
    'reloadable' => true,
    'reusePort' => true,
    
    // 智能调试检测
    'debug' => [
        'auto_detect' => true,          // 自动检测调试模式
        'force_disable' => true,        // 强制禁用调试（生产环境）
        'disable_trace' => true,        // 禁用 think-trace
        'disable_debug_tools' => true,  // 禁用其他调试工具
    ],
    
    // 内存管理
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 50,
        'memory_limit' => '256M',
        'aggressive_gc' => true,
    ],
];

echo "智能优化特性:\n";
echo "1. ✅ 智能调试模式检测（参考 think-worker）\n";
echo "2. ✅ 自动禁用 think-trace 和调试工具\n";
echo "3. ✅ 避免应用实例重复创建\n";
echo "4. ✅ 激进的垃圾回收策略\n";
echo "5. ✅ 生产环境优化配置\n";

// 创建智能优化适配器
$adapter = new SmartOptimizedAdapter($app, $config);

if (!$adapter->isAvailable()) {
    echo "❌ Workerman 不可用\n";
    exit(1);
}

echo "\n服务配置:\n";
echo "- 监听地址: {$config['host']}:{$config['port']}\n";
echo "- 进程数: {$config['count']}\n";
echo "- 调试检测: " . ($config['debug']['auto_detect'] ? '✅ 启用' : '❌ 禁用') . "\n";
echo "- 强制生产模式: " . ($config['debug']['force_disable'] ? '✅ 启用' : '❌ 禁用') . "\n";

echo "\n🚀 启动智能优化的 Workerman 服务...\n";
echo "预期改善: 智能调试检测 + 自动性能优化\n";
echo "\n按 Ctrl+C 停止服务\n\n";

// 启动服务
$adapter->start();
