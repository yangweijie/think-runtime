<?php

declare(strict_types=1);

/**
 * Ripple服务器启动脚本示例
 * 
 * 此文件演示如何手动启动Ripple服务器
 */

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

// 引入自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 检查PHP版本
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    echo "Error: Ripple requires PHP 8.1.0 or higher (current: " . PHP_VERSION . ")\n";
    exit(1);
}

// 创建应用实例
$app = new App(__DIR__);

// 初始化应用
$app->initialize();

// 获取运行时管理器
$manager = $app->make('runtime.manager');

// 自定义Ripple配置
$options = [
    'host' => '0.0.0.0',
    'port' => 8080,
    'worker_num' => 4,
    'max_connections' => 10000,
    'max_coroutines' => 100000,
    'coroutine_pool_size' => 1000,
    'timeout' => 30,
    'enable_keepalive' => true,
    'keepalive_timeout' => 60,
    'max_request_size' => '8M',
    'enable_compression' => true,
    'compression_level' => 6,
    'debug' => true,
    'access_log' => true,
    'error_log' => true,
    'enable_fiber' => true,
    'fiber_stack_size' => 8192,
    'ssl' => [
        'enabled' => false,
        'cert_file' => '',
        'key_file' => '',
        'verify_peer' => false,
    ],
    'database' => [
        'pool_size' => 10,
        'max_idle_time' => 3600,
    ],
];

echo "Starting Ripple Server...\n";
echo "Host: {$options['host']}\n";
echo "Port: {$options['port']}\n";
echo "Workers: {$options['worker_num']}\n";
echo "Max Connections: {$options['max_connections']}\n";
echo "Max Coroutines: {$options['max_coroutines']}\n";
echo "Coroutine Pool Size: {$options['coroutine_pool_size']}\n";
echo "Fiber Support: " . ($options['enable_fiber'] ? 'Enabled' : 'Disabled') . "\n";
echo "Fiber Stack Size: {$options['fiber_stack_size']} bytes\n";
echo "Compression: " . ($options['enable_compression'] ? 'Enabled' : 'Disabled') . "\n";
echo "SSL/TLS: " . ($options['ssl']['enabled'] ? 'Enabled' : 'Disabled') . "\n";
echo "Debug Mode: " . ($options['debug'] ? 'Enabled' : 'Disabled') . "\n";
echo "Database Pool Size: {$options['database']['pool_size']}\n";
echo "Press Ctrl+C to stop the server\n\n";

// 启动Ripple服务器
$manager->start('ripple', $options);
