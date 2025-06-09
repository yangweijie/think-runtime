<?php

declare(strict_types=1);

/**
 * Swoole服务器启动脚本示例
 * 
 * 此文件演示如何手动启动Swoole服务器
 */

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

// 引入自动加载
require_once __DIR__ . '/vendor/autoload.php';

// 创建应用实例
$app = new App();

// 初始化应用
$app->initialize();

// 获取运行时管理器
$manager = $app->make('runtime.manager');

// 自定义Swoole配置
$options = [
    'host' => '0.0.0.0',
    'port' => 9501,
    'settings' => [
        'worker_num' => 4,
        'task_worker_num' => 2,
        'max_request' => 10000,
        'daemonize' => 0,
        'log_file' => __DIR__ . '/runtime/swoole.log',
        'pid_file' => __DIR__ . '/runtime/swoole.pid',
    ],
];

echo "Starting Swoole HTTP Server...\n";
echo "Listening on: {$options['host']}:{$options['port']}\n";
echo "Workers: {$options['settings']['worker_num']}\n";
echo "Press Ctrl+C to stop the server\n\n";

// 启动Swoole服务器
$manager->start('swoole', $options);
