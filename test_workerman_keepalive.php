<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * Workerman Keep-Alive 测试服务器
 * 测试 keep-alive 和 gzip 压缩功能
 */

echo "=== Workerman Keep-Alive 测试服务器 ===\n";

// 创建测试应用
class KeepAliveTestApp
{
    public function initialize(): void
    {
        echo "✅ 应用初始化 (Keep-Alive + Gzip)\n";
    }

    public function has(string $name): bool
    {
        return false;
    }
}

// 创建 HTTP Worker
$worker = new Worker('http://0.0.0.0:8080');
$worker->count = 4; // 4个进程
$worker->name = 'keepalive-test';
$worker->reusePort = true; // 启用端口复用

// 创建适配器实例 - 优化的 keep-alive 配置
$testApp = new KeepAliveTestApp();
$config = [
    'host' => '0.0.0.0',
    'port' => 8080,
    'count' => 4,
    'name' => 'keepalive-test',
    
    // Keep-Alive 配置
    'keep_alive' => [
        'enable' => true,
        'timeout' => 60,        // 60秒超时
        'max_requests' => 1000, // 每连接最大1000请求
        'close_on_idle' => 300, // 5分钟空闲关闭
    ],
    
    // 压缩配置
    'compression' => [
        'enable' => true,
        'type' => 'gzip',
        'level' => 6,
        'min_length' => 100, // 降低阈值便于测试
        'types' => [
            'application/json',
            'text/html',
            'text/plain',
        ],
    ],
    
    // Socket 配置
    'socket' => [
        'so_reuseport' => true,
        'tcp_nodelay' => true,
        'so_keepalive' => true,
        'backlog' => 1024,
    ],
];

$adapter = new WorkermanAdapter($testApp, $config);

// 统计变量
$stats = [
    'requests' => 0,
    'keepalive_requests' => 0,
    'gzip_responses' => 0,
    'start_time' => 0,
];

// 请求处理
$worker->onMessage = function(TcpConnection $connection, Request $request) use ($adapter, &$stats) {
    $stats['requests']++;
    
    try {
        $path = $request->path();
        $connectionHeader = $request->header('connection', '');
        $acceptEncoding = $request->header('accept-encoding', '');
        
        // 统计 keep-alive 请求
        if (strtolower($connectionHeader) === 'keep-alive') {
            $stats['keepalive_requests']++;
        }
        
        // 根据路径返回不同内容
        switch ($path) {
            case '/':
                $data = [
                    'message' => 'Workerman Keep-Alive + Gzip 测试',
                    'server_info' => [
                        'name' => 'Workerman-ThinkPHP-Runtime',
                        'version' => '1.0.0',
                        'features' => ['keep-alive', 'gzip', 'high-performance'],
                    ],
                    'request_info' => [
                        'path' => $path,
                        'method' => $request->method(),
                        'connection' => $connectionHeader,
                        'accept_encoding' => $acceptEncoding,
                        'user_agent' => $request->header('user-agent', 'unknown'),
                    ],
                    'stats' => [
                        'total_requests' => $stats['requests'],
                        'keepalive_requests' => $stats['keepalive_requests'],
                        'keepalive_rate' => $stats['requests'] > 0 ? round($stats['keepalive_requests'] / $stats['requests'] * 100, 2) . '%' : '0%',
                        'uptime' => time() - $stats['start_time'],
                    ],
                    'test_data' => str_repeat('这是用于测试压缩效果的重复数据。', 20),
                ];
                break;
                
            case '/large':
                $data = [
                    'message' => '大数据量测试',
                    'large_array' => array_fill(0, 100, [
                        'id' => rand(1, 1000),
                        'name' => '测试数据 ' . rand(1, 100),
                        'description' => str_repeat('详细描述内容 ', 10),
                        'timestamp' => time(),
                    ]),
                    'stats' => [
                        'total_requests' => $stats['requests'],
                        'keepalive_rate' => $stats['requests'] > 0 ? round($stats['keepalive_requests'] / $stats['requests'] * 100, 2) . '%' : '0%',
                    ],
                ];
                break;
                
            case '/stats':
                $data = [
                    'server_stats' => [
                        'total_requests' => $stats['requests'],
                        'keepalive_requests' => $stats['keepalive_requests'],
                        'keepalive_rate' => $stats['requests'] > 0 ? round($stats['keepalive_requests'] / $stats['requests'] * 100, 2) . '%' : '0%',
                        'uptime' => time() - $stats['start_time'],
                        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                        'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
                    ],
                    'adapter_stats' => $adapter->getMemoryStats(),
                ];
                break;
                
            default:
                $data = [
                    'error' => 'Not Found',
                    'available_endpoints' => ['/', '/large', '/stats'],
                ];
                break;
        }
        
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Server' => 'Workerman-ThinkPHP-Runtime',
            'X-Powered-By' => 'ThinkPHP-Runtime/Workerman',
        ];
        
        // 使用反射调用适配器的方法
        $reflection = new ReflectionClass($adapter);
        
        // 添加 Keep-Alive 头
        $addKeepAliveMethod = $reflection->getMethod('addKeepAliveHeaders');
        $addKeepAliveMethod->setAccessible(true);
        $addKeepAliveMethod->invoke($adapter, $request, $headers);
        
        // 应用压缩
        $applyCompressionMethod = $reflection->getMethod('applyCompression');
        $applyCompressionMethod->setAccessible(true);
        $compressedData = $applyCompressionMethod->invoke($adapter, $request, $body, $headers);
        
        // 统计压缩响应
        if ($compressedData['compressed']) {
            $stats['gzip_responses']++;
        }
        
        // 添加统计头
        $headers['X-Total-Requests'] = $stats['requests'];
        $headers['X-KeepAlive-Rate'] = $stats['requests'] > 0 ? round($stats['keepalive_requests'] / $stats['requests'] * 100, 2) . '%' : '0%';
        $headers['X-Gzip-Rate'] = $stats['requests'] > 0 ? round($stats['gzip_responses'] / $stats['requests'] * 100, 2) . '%' : '0%';
        
        $response = new \Workerman\Protocols\Http\Response(200, $headers, $compressedData['body']);
        $connection->send($response);
        
    } catch (Exception $e) {
        $errorResponse = new \Workerman\Protocols\Http\Response(500, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'error' => true,
            'message' => $e->getMessage(),
        ]));
        
        $connection->send($errorResponse);
    }
};

$worker->onWorkerStart = function(Worker $worker) use (&$stats) {
    $stats['start_time'] = time();
    
    echo "Keep-Alive Test Server started!\n";
    echo "Worker #{$worker->id} (PID: " . getmypid() . ")\n";
    echo "URL: http://0.0.0.0:8080/\n";
    
    echo "\n功能特性:\n";
    echo "✅ Keep-Alive: 启用 (timeout=60s, max=1000)\n";
    echo "✅ Gzip 压缩: 启用 (level=6, min=100bytes)\n";
    echo "✅ 端口复用: 启用\n";
    echo "✅ TCP 优化: 启用\n";
    
    echo "\n测试端点:\n";
    echo "- http://0.0.0.0:8080/ (基础测试)\n";
    echo "- http://0.0.0.0:8080/large (大数据测试)\n";
    echo "- http://0.0.0.0:8080/stats (统计信息)\n";
    
    echo "\nwrk 测试命令:\n";
    echo "# 基础 Keep-Alive 测试\n";
    echo "wrk -t4 -c100 -d30s -H 'Connection: keep-alive' http://0.0.0.0:8080/\n\n";
    echo "# Keep-Alive + Gzip 测试\n";
    echo "wrk -t4 -c100 -d30s -H 'Connection: keep-alive' -H 'Accept-Encoding: gzip' http://0.0.0.0:8080/\n\n";
    echo "# 使用 Lua 脚本测试\n";
    echo "wrk -t8 -c200 -d60s -s keepalive.lua http://0.0.0.0:8080/\n\n";
    echo "# 运行完整测试套件\n";
    echo "./wrk_keepalive_test.sh\n\n";
    
    echo "Press Ctrl+C to stop\n\n";
};

echo "Starting Workerman Keep-Alive Test Server...\n";
echo "Listening on: http://0.0.0.0:8080\n";
echo "Workers: 4\n";
echo "Features: Keep-Alive + Gzip + Port Reuse\n";
echo "Press Ctrl+C to stop\n\n";

Worker::runAll();
