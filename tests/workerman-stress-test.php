<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * Workerman 压力测试脚本
 * 
 * 用于测试修复后的 Workerman 适配器在高并发下的内存表现
 */

echo "=== Workerman 压力测试 ===\n";

// 创建测试应用
class StressTestApp
{
    private int $requestCount = 0;
    private array $cache = [];
    private array $services = [];
    private bool $initialized = false;
    public $http;

    public function initialize(): void
    {
        $this->initialized = true;

        // 创建模拟的 HTTP 对象
        $this->http = new class {
            public function run($request) {
                return new class {
                    public function getCode() { return 200; }
                    public function getHeader() { return ['Content-Type' => 'application/json']; }
                    public function getContent() {
                        return json_encode([
                            'message' => 'Hello from Workerman!',
                            'timestamp' => microtime(true),
                            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                        ]);
                    }
                };
            }

            public function end($response) {
                // 结束处理，什么都不做
            }
        };

        echo "应用初始化完成\n";
    }

    public function initialized(): bool
    {
        return $this->initialized;
    }

    // 实现 ThinkPHP 应用接口
    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    public function get(string $name)
    {
        return $this->services[$name] ?? null;
    }

    public function make(string $name, array $vars = [])
    {
        return $this->get($name);
    }

    public function bind(string $name, $value): void
    {
        $this->services[$name] = $value;
    }

    public function delete(string $name): void
    {
        unset($this->services[$name]);
    }

    public function handle($request): string
    {
        $this->requestCount++;

        // 模拟一些内存操作
        $data = [
            'request_id' => uniqid(),
            'timestamp' => microtime(true),
            'data' => str_repeat('x', 1024), // 1KB 数据
            'count' => $this->requestCount,
        ];

        // 模拟缓存操作（可能导致内存泄漏）
        $this->cache[uniqid()] = $data;

        // 限制缓存大小
        if (count($this->cache) > 100) {
            $this->cache = array_slice($this->cache, -50, null, true);
        }

        return json_encode([
            'message' => 'Hello from Workerman!',
            'request_count' => $this->requestCount,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            'cache_size' => count($this->cache),
        ]);
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }
}

// 创建适配器配置
$config = [
    'host' => '127.0.0.1',
    'port' => 8080,
    'count' => 2, // 2个进程
    'name' => 'stress-test-server',
    
    // 内存管理配置
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 50, // 每50个请求GC一次
        'context_cleanup_interval' => 30, // 30秒清理一次
        'max_context_size' => 500, // 最大上下文数量
    ],
    
    // 定时器配置
    'timer' => [
        'enable' => true,
        'interval' => 15, // 15秒输出一次统计
    ],
    
    // 监控配置
    'monitor' => [
        'enable' => true,
        'slow_request_threshold' => 100, // 100ms
        'memory_limit' => '512M',
    ],
    
    // 静态文件配置
    'static_file' => [
        'enable' => false, // 关闭静态文件服务以专注于动态请求
    ],
];

$app = new StressTestApp();
$adapter = new WorkermanAdapter($app, $config);

// 检查可用性
if (!$adapter->isAvailable()) {
    echo "错误: Workerman 不可用\n";
    exit(1);
}

echo "Workerman 适配器配置:\n";
$adapterConfig = $adapter->getConfig();
echo "- 主机: {$adapterConfig['host']}:{$adapterConfig['port']}\n";
echo "- 进程数: {$adapterConfig['count']}\n";
echo "- GC间隔: {$adapterConfig['memory']['gc_interval']} 请求\n";
echo "- 上下文清理间隔: {$adapterConfig['memory']['context_cleanup_interval']} 秒\n";
echo "- 最大上下文数: {$adapterConfig['memory']['max_context_size']}\n";

echo "\n=== 启动压力测试服务器 ===\n";
echo "服务器地址: http://{$adapterConfig['host']}:{$adapterConfig['port']}\n";
echo "\n测试建议:\n";
echo "1. 使用 ab 进行压力测试:\n";
echo "   ab -n 10000 -c 100 http://127.0.0.1:8080/\n";
echo "\n2. 使用 curl 进行简单测试:\n";
echo "   curl http://127.0.0.1:8080/\n";
echo "\n3. 观察内存使用情况:\n";
echo "   服务器会每15秒输出内存统计信息\n";
echo "\n4. 按 Ctrl+C 停止服务器\n";

echo "\n启动服务器...\n";

// 添加信号处理
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function() {
        echo "\n收到停止信号，正在关闭服务器...\n";
        exit(0);
    });
}

try {
    $adapter->run();
} catch (Throwable $e) {
    echo "服务器启动失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
