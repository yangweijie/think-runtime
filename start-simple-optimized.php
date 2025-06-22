<?php

declare(strict_types=1);

/**
 * 启动简化优化的 Workerman 服务
 */

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

require_once 'vendor/autoload.php';
require_once 'SimpleOptimizedAdapter.php';

use yangweijie\thinkRuntime\adapter\SimpleOptimizedAdapter;
use think\App;

echo "=== 启动简化优化的 Workerman 服务 ===\n";

// 创建 ThinkPHP 应用
$app = new App();

// 简化的优化配置
$config = [
    'host' => '127.0.0.1',
    'port' => 8088,
    'count' => 4,
    'name' => 'ThinkPHP-Simple-Optimized',
    'reloadable' => true,
    'reusePort' => true,
    
    // 内存管理
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 50,
        'memory_limit' => '256M',
        'aggressive_gc' => true,
    ],
    
    // 性能优化
    'performance' => [
        'disable_debug' => true,
        'disable_trace' => true,
        'enable_opcache' => true,
    ],
];

echo "优化策略:\n";
echo "1. ✅ 避免应用实例重复创建\n";
echo "2. ✅ 激进的垃圾回收策略\n";
echo "3. ✅ 强制禁用调试工具\n";
echo "4. ✅ 启用 OPcache 优化\n";
echo "5. ✅ 简化的内存管理\n";

// 创建简化优化适配器
$adapter = new SimpleOptimizedAdapter($app, $config);

if (!$adapter->isAvailable()) {
    echo "❌ Workerman 不可用\n";
    exit(1);
}

echo "\n服务配置:\n";
echo "- 监听地址: {$config['host']}:{$config['port']}\n";
echo "- 进程数: {$config['count']}\n";
echo "- 内存限制: {$config['memory']['memory_limit']}\n";
echo "- GC 间隔: {$config['memory']['gc_interval']} 请求\n";

echo "\n🚀 启动简化优化的 Workerman 服务...\n";
echo "预期改善: 稳定的内存使用 + 更好的 QPS\n";
echo "\n按 Ctrl+C 停止服务\n\n";

// 启动服务
$adapter->start();
