<?php

declare(strict_types=1);

/**
 * 启动优化的 Workerman 服务
 */

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

require_once 'vendor/autoload.php';
require_once 'ThinkWorkerOptimizedAdapter.php';

use yangweijie\thinkRuntime\adapter\ThinkWorkerOptimizedAdapter;
use think\App;

echo "=== 启动优化的 Workerman 服务 ===\n";

// 创建 ThinkPHP 应用
$app = new App();

// 优化配置
$config = [
    'host' => '127.0.0.1',
    'port' => 8087,
    'count' => 4, // 4个进程
    'name' => 'ThinkWorker-Optimized',
    'reloadable' => true,
    'reusePort' => true,
    
    // 沙盒配置
    'sandbox' => [
        'enable' => true,
        'reset_instances' => ['log', 'session', 'view', 'response', 'cookie', 'request'],
        'clone_services' => true,
    ],
    
    // 内存管理
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 50,
        'reset_interval' => 100,
        'memory_limit' => '256M',
    ],
    
    // 性能优化
    'performance' => [
        'preload_routes' => true,
        'preload_middleware' => true,
        'enable_opcache_reset' => false,
    ],
];

// 设置 PHP 配置
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '0');

// 启用 OPcache（如果可用）
if (function_exists('opcache_reset')) {
    ini_set('opcache.enable', '1');
    ini_set('opcache.enable_cli', '1');
    ini_set('opcache.memory_consumption', '128');
    ini_set('opcache.max_accelerated_files', '4000');
}

echo "PHP 配置:\n";
echo "- 内存限制: " . ini_get('memory_limit') . "\n";
echo "- OPcache: " . (function_exists('opcache_reset') ? '✅ 启用' : '❌ 未启用') . "\n";
echo "- Event 扩展: " . (extension_loaded('event') ? '✅ 启用' : '❌ 未启用') . "\n";

// 创建优化适配器
$adapter = new ThinkWorkerOptimizedAdapter($app, $config);

if (!$adapter->isAvailable()) {
    echo "❌ Workerman 不可用\n";
    exit(1);
}

echo "\n服务配置:\n";
echo "- 监听地址: {$config['host']}:{$config['port']}\n";
echo "- 进程数: {$config['count']}\n";
echo "- 沙盒模式: " . ($config['sandbox']['enable'] ? '✅ 启用' : '❌ 禁用') . "\n";
echo "- 内存管理: ✅ 启用\n";
echo "- GC 间隔: {$config['memory']['gc_interval']} 请求\n";

echo "\n🚀 启动优化的 Workerman 服务...\n";
echo "预期性能提升: 20-40%\n";
echo "预期 QPS: 1000-1200\n";
echo "\n按 Ctrl+C 停止服务\n\n";

// 启动服务
$adapter->start();
