<?php

declare(strict_types=1);

/**
 * Workerman 服务器示例
 * 
 * 使用方法：
 * 1. 安装 Workerman: composer require workerman/workerman
 * 2. 运行服务器: php examples/workerman_server.php
 * 3. 访问: http://localhost:8080
 */

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

// 引入自动加载
require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Workerman 服务器示例 ===" . PHP_EOL;
echo "时间: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "PHP 版本: " . PHP_VERSION . PHP_EOL;

// 检查 Workerman 是否可用
if (!class_exists('\Workerman\Worker')) {
    echo "❌ Workerman 未安装，请先安装：" . PHP_EOL;
    echo "   composer require workerman/workerman" . PHP_EOL;
    exit(1);
}

echo "✅ Workerman 可用" . PHP_EOL;
echo PHP_EOL;

try {
    // 创建应用实例
    $app = new App();
    
    // 初始化应用
    $app->initialize();
    
    // 获取运行时管理器
    $manager = $app->make('runtime.manager');
    
    // 配置 Workerman 运行时
    $config = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'count' => 4,                    // 4个进程
        'name' => 'ThinkPHP-Workerman-Example',
        'reloadable' => true,
        'static_file' => [
            'enable' => true,
            'document_root' => 'public',
            'cache_time' => 3600,
        ],
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 500, // 500ms 慢请求阈值
        ],
        'middleware' => [
            'cors' => [
                'enable' => true,
                'allow_origin' => '*',
            ],
            'security' => [
                'enable' => true,
            ],
        ],
        'log' => [
            'enable' => true,
            'file' => 'runtime/logs/workerman-example.log',
        ],
        'timer' => [
            'enable' => true,
            'interval' => 60, // 60秒定时器
        ],
    ];
    
    echo "=== 启动配置 ===" . PHP_EOL;
    echo "监听地址: {$config['host']}:{$config['port']}" . PHP_EOL;
    echo "进程数量: {$config['count']}" . PHP_EOL;
    echo "静态文件: " . ($config['static_file']['enable'] ? '启用' : '禁用') . PHP_EOL;
    echo "性能监控: " . ($config['monitor']['enable'] ? '启用' : '禁用') . PHP_EOL;
    echo "CORS支持: " . ($config['middleware']['cors']['enable'] ? '启用' : '禁用') . PHP_EOL;
    echo "定时器: " . ($config['timer']['enable'] ? '启用' : '禁用') . PHP_EOL;
    echo PHP_EOL;
    
    // 启动 Workerman 运行时
    echo "正在启动 Workerman 服务器..." . PHP_EOL;
    $manager->start('workerman', $config);
    
} catch (\Throwable $e) {
    echo "❌ 启动失败: " . $e->getMessage() . PHP_EOL;
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    
    if ($e->getPrevious()) {
        echo "原始错误: " . $e->getPrevious()->getMessage() . PHP_EOL;
    }
    
    exit(1);
}

echo "=== 使用说明 ===" . PHP_EOL;
echo "1. 访问 http://localhost:8080 查看应用" . PHP_EOL;
echo "2. 访问 http://localhost:8080/static.html 测试静态文件" . PHP_EOL;
echo "3. 使用 Ctrl+C 停止服务器" . PHP_EOL;
echo "4. 使用 kill -USR1 \$pid 平滑重启" . PHP_EOL;
echo "5. 使用 kill -USR2 \$pid 平滑停止" . PHP_EOL;
echo PHP_EOL;

echo "=== 管理命令 ===" . PHP_EOL;
echo "启动: php examples/workerman_server.php start" . PHP_EOL;
echo "停止: php examples/workerman_server.php stop" . PHP_EOL;
echo "重启: php examples/workerman_server.php restart" . PHP_EOL;
echo "重载: php examples/workerman_server.php reload" . PHP_EOL;
echo "状态: php examples/workerman_server.php status" . PHP_EOL;
echo "连接: php examples/workerman_server.php connections" . PHP_EOL;
echo PHP_EOL;

echo "=== 性能特性 ===" . PHP_EOL;
echo "✅ 多进程架构，充分利用多核CPU" . PHP_EOL;
echo "✅ 长连接支持，减少连接开销" . PHP_EOL;
echo "✅ 静态文件服务，高效资源处理" . PHP_EOL;
echo "✅ 中间件支持，灵活的请求处理" . PHP_EOL;
echo "✅ 性能监控，实时性能分析" . PHP_EOL;
echo "✅ 定时器支持，后台任务处理" . PHP_EOL;
echo "✅ 平滑重启，零停机部署" . PHP_EOL;
echo "✅ 内存监控，防止内存泄漏" . PHP_EOL;
echo PHP_EOL;

echo "=== 注意事项 ===" . PHP_EOL;
echo "1. Workerman 需要 PHP CLI 模式运行" . PHP_EOL;
echo "2. 建议在 Linux 环境下使用以获得最佳性能" . PHP_EOL;
echo "3. 进程数建议设置为 CPU 核心数" . PHP_EOL;
echo "4. 生产环境建议启用守护进程模式" . PHP_EOL;
echo "5. 定期监控内存使用情况" . PHP_EOL;
echo PHP_EOL;

echo "服务器已启动，请查看上方的访问地址！" . PHP_EOL;
