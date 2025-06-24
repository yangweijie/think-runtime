<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * Workerman Gzip 压缩测试
 * 测试默认 gzip 压缩功能
 */

echo "=== Workerman Gzip 压缩测试 ===\n";

// 创建测试应用
class GzipTestApp
{
    public function initialize(): void
    {
        echo "✅ 应用初始化 (Gzip 测试)\n";
    }

    public function has(string $name): bool
    {
        return false;
    }
}

// 创建 HTTP Worker
$worker = new Worker('http://127.0.0.1:8087');
$worker->count = 1;
$worker->name = 'gzip-test';

// 创建适配器实例 - 启用 gzip 压缩
$testApp = new GzipTestApp();
$config = [
    'host' => '127.0.0.1',
    'port' => 8087,
    'count' => 1,
    'name' => 'gzip-test',
    
    // 压缩配置
    'compression' => [
        'enable' => true,
        'type' => 'gzip',
        'level' => 6,
        'min_length' => 100, // 降低阈值便于测试
        'types' => [
            'text/html',
            'text/css',
            'text/javascript',
            'text/xml',
            'text/plain',
            'application/javascript',
            'application/json',
            'application/xml',
        ],
    ],
];

$adapter = new WorkermanAdapter($testApp, $config);

// 请求处理
$worker->onMessage = function(TcpConnection $connection, Request $request) use ($adapter) {
    try {
        $path = $request->path();
        
        // 根据路径返回不同类型的内容进行测试
        switch ($path) {
            case '/':
                $data = [
                    'message' => 'Workerman Gzip 压缩测试',
                    'compression' => [
                        'enabled' => true,
                        'type' => 'gzip',
                        'level' => 6,
                        'min_length' => 100,
                    ],
                    'test_data' => str_repeat('这是用于测试 gzip 压缩的重复数据。', 50),
                    'timestamp' => time(),
                    'request_info' => [
                        'path' => $request->path(),
                        'method' => $request->method(),
                        'accept_encoding' => $request->header('accept-encoding', 'none'),
                        'user_agent' => $request->header('user-agent', 'unknown'),
                    ],
                ];
                
                $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $headers = [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Access-Control-Allow-Origin' => '*',
                ];
                break;
                
            case '/large':
                // 大内容测试
                $largeData = [
                    'message' => '大内容 Gzip 压缩测试',
                    'large_content' => str_repeat('这是一个很长的字符串用于测试 gzip 压缩效果。', 200),
                    'array_data' => array_fill(0, 100, [
                        'id' => rand(1, 1000),
                        'name' => '测试数据 ' . rand(1, 100),
                        'description' => str_repeat('描述内容 ', 10),
                    ]),
                ];
                
                $body = json_encode($largeData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $headers = [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Access-Control-Allow-Origin' => '*',
                ];
                break;
                
            case '/html':
                // HTML 内容测试
                $body = '<!DOCTYPE html>
<html>
<head>
    <title>Gzip 压缩测试</title>
    <meta charset="utf-8">
</head>
<body>
    <h1>Workerman Gzip 压缩测试</h1>
    <p>' . str_repeat('这是用于测试 HTML 内容 gzip 压缩的段落。', 30) . '</p>
    <div>
        <ul>
            ' . str_repeat('<li>列表项目 - 测试压缩效果</li>', 20) . '
        </ul>
    </div>
</body>
</html>';
                
                $headers = [
                    'Content-Type' => 'text/html; charset=utf-8',
                ];
                break;
                
            case '/small':
                // 小内容测试（不应该被压缩）
                $body = json_encode(['message' => 'small'], JSON_UNESCAPED_UNICODE);
                $headers = [
                    'Content-Type' => 'application/json; charset=utf-8',
                ];
                break;
                
            default:
                $body = json_encode(['error' => 'Not Found'], JSON_UNESCAPED_UNICODE);
                $headers = [
                    'Content-Type' => 'application/json; charset=utf-8',
                ];
                break;
        }
        
        // 使用反射调用压缩方法
        $reflection = new ReflectionClass($adapter);
        $applyCompressionMethod = $reflection->getMethod('applyCompression');
        $applyCompressionMethod->setAccessible(true);
        
        $originalLength = strlen($body);
        $compressedData = $applyCompressionMethod->invoke($adapter, $request, $body, $headers);
        $compressedLength = strlen($compressedData['body']);
        
        // 添加压缩统计信息到响应头
        $headers['X-Original-Length'] = $originalLength;
        $headers['X-Compressed-Length'] = $compressedLength;
        $headers['X-Compression-Ratio'] = $originalLength > 0 ? round((1 - $compressedLength / $originalLength) * 100, 2) . '%' : '0%';
        $headers['X-Compressed'] = $compressedData['compressed'] ? 'true' : 'false';
        
        $response = new Response(200, $headers, $compressedData['body']);
        $connection->send($response);
        
    } catch (Exception $e) {
        $errorResponse = new Response(500, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'error' => true,
            'message' => $e->getMessage(),
        ]));
        
        $connection->send($errorResponse);
    }
};

$worker->onWorkerStart = function(Worker $worker) {
    echo "Workerman Gzip Test Server started!\n";
    echo "PID: " . getmypid() . "\n";
    echo "URL: http://127.0.0.1:8087/\n";
    echo "\n测试端点:\n";
    echo "- http://127.0.0.1:8087/ (JSON 压缩测试)\n";
    echo "- http://127.0.0.1:8087/large (大内容压缩测试)\n";
    echo "- http://127.0.0.1:8087/html (HTML 压缩测试)\n";
    echo "- http://127.0.0.1:8087/small (小内容测试 - 不压缩)\n";
    echo "\n测试命令:\n";
    echo "curl -H 'Accept-Encoding: gzip' -v http://127.0.0.1:8087/\n";
    echo "curl -H 'Accept-Encoding: gzip' -v http://127.0.0.1:8087/large\n";
    echo "curl -H 'Accept-Encoding: gzip' -v http://127.0.0.1:8087/html\n";
    echo "\nPress Ctrl+C to stop\n\n";
};

echo "Starting Workerman Gzip Test Server...\n";
echo "Listening on: http://127.0.0.1:8087\n";
echo "Gzip compression: Enabled\n";
echo "Compression level: 6\n";
echo "Min length: 100 bytes\n";
echo "Press Ctrl+C to stop\n\n";

Worker::runAll();
