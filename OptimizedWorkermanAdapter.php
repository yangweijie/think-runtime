<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Timer;
use Nyholm\Psr7Server\ServerRequestCreator;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * 优化的 Workerman 适配器 - 专门针对真实项目内存优化
 * 
 * 主要优化：
 * 1. 完全避免应用实例重复创建
 * 2. 激进的内存管理策略
 * 3. 更频繁的垃圾回收
 * 4. 详细的内存监控
 */
class OptimizedWorkermanAdapter extends AbstractRuntime implements AdapterInterface
{
    protected ?Worker $worker = null;
    protected ?ServerRequestCreator $requestCreator = null;
    
    // 轻量化上下文存储
    protected array $connectionContext = [];
    
    // 内存统计
    protected array $memoryStats = [
        'peak_usage' => 0,
        'request_count' => 0,
        'last_cleanup' => 0,
        'gc_count' => 0,
        'context_cleanups' => 0,
    ];
    
    // 激进的默认配置
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'count' => 4,
        'name' => 'ThinkPHP-Workerman-Optimized',
        'user' => '',
        'group' => '',
        'reloadable' => true,
        'reusePort' => false,
        'transport' => 'tcp',
        'context' => [],
        'protocol' => 'http',
        
        // 激进的内存管理配置
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 20,              // 每20个请求强制GC
            'context_cleanup_interval' => 10, // 10秒清理一次上下文
            'max_context_size' => 100,        // 最大上下文数量
            'memory_limit_mb' => 128,         // 内存限制 128MB
            'enable_memory_monitor' => true,   // 启用内存监控
        ],
        
        // 定时器配置
        'timer' => [
            'enable' => true,
            'interval' => 10, // 10秒执行一次清理
        ],
        
        // 性能监控
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 500, // 500ms
            'memory_warning_threshold' => 100, // 100MB
        ],
    ];

    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('Workerman is not installed');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 设置更严格的 PHP 配置
        ini_set('memory_limit', $config['memory']['memory_limit_mb'] . 'M');
        ini_set('max_execution_time', '0');
        
        // 启用 opcache 如果可用
        if (function_exists('opcache_reset')) {
            ini_set('opcache.enable', '1');
            ini_set('opcache.enable_cli', '1');
        }

        // 初始化请求创建器
        $this->requestCreator = new ServerRequestCreator(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory
        );

        // 创建 Worker
        $listen = 'http://' . $config['host'] . ':' . $config['port'];
        $this->worker = new Worker($listen, $config['context']);

        // 设置 Worker 属性
        $this->worker->count = $config['count'];
        $this->worker->name = $config['name'];
        $this->worker->user = $config['user'];
        $this->worker->group = $config['group'];
        $this->worker->reloadable = $config['reloadable'];
        $this->worker->reusePort = $config['reusePort'];

        // 绑定事件
        $this->bindEvents();
    }

    protected function bindEvents(): void
    {
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        $this->worker->onMessage = [$this, 'onMessage'];
        $this->worker->onConnect = [$this, 'onConnect'];
        $this->worker->onClose = [$this, 'onClose'];
        $this->worker->onError = [$this, 'onError'];
        $this->worker->onWorkerStop = [$this, 'onWorkerStop'];
    }

    public function onWorkerStart(Worker $worker): void
    {
        try {
            // 设置进程标题
            if (function_exists('cli_set_process_title')) {
                cli_set_process_title("workerman-optimized-{$worker->id}");
            }

            // 初始化应用 - 只初始化一次
            if ($this->app && method_exists($this->app, 'initialize')) {
                $this->app->initialize();
            }

            // 设置激进的清理定时器
            $this->setupAggressiveTimer();

            echo "Optimized Worker #{$worker->id} started with aggressive memory management\n";

        } catch (Throwable $e) {
            echo "Worker #{$worker->id} start failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 设置激进的清理定时器
     */
    protected function setupAggressiveTimer(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        if ($config['timer']['enable'] ?? true) {
            Timer::add($config['timer']['interval'], function() {
                $this->aggressiveCleanup();
            });
        }
    }

    /**
     * 激进的清理策略
     */
    protected function aggressiveCleanup(): void
    {
        $beforeMemory = memory_get_usage(true);
        
        // 1. 清理过期上下文
        $this->cleanupExpiredContexts();
        
        // 2. 强制垃圾回收
        for ($i = 0; $i < 3; $i++) {
            gc_collect_cycles();
        }
        
        // 3. 清理 opcache (谨慎使用)
        if (function_exists('opcache_reset') && $this->memoryStats['request_count'] % 1000 === 0) {
            opcache_reset();
        }
        
        // 4. 重置内存峰值统计
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        
        $afterMemory = memory_get_usage(true);
        $freed = $beforeMemory - $afterMemory;
        
        if ($freed > 0) {
            echo "Aggressive cleanup freed " . round($freed / 1024 / 1024, 2) . "MB\n";
        }
        
        $this->memoryStats['last_cleanup'] = time();
        $this->memoryStats['gc_count']++;
        
        // 内存监控
        $this->monitorMemoryUsage();
    }

    /**
     * 内存监控
     */
    protected function monitorMemoryUsage(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        if (!($config['memory']['enable_memory_monitor'] ?? true)) {
            return;
        }
        
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryLimitMB = $config['memory']['memory_limit_mb'] ?? 128;
        $warningThresholdMB = $config['monitor']['memory_warning_threshold'] ?? 100;
        
        $currentMB = round($currentMemory / 1024 / 1024, 2);
        $peakMB = round($peakMemory / 1024 / 1024, 2);
        
        // 更新统计
        if ($peakMemory > $this->memoryStats['peak_usage']) {
            $this->memoryStats['peak_usage'] = $peakMemory;
        }
        
        // 内存警告
        if ($currentMB > $warningThresholdMB) {
            echo "⚠️  Memory Warning: Current {$currentMB}MB, Peak {$peakMB}MB, Limit {$memoryLimitMB}MB\n";
            echo "   Requests: {$this->memoryStats['request_count']}, GC: {$this->memoryStats['gc_count']}\n";
            
            // 触发额外清理
            $this->emergencyCleanup();
        }
    }

    /**
     * 紧急清理
     */
    protected function emergencyCleanup(): void
    {
        echo "🚨 Emergency cleanup triggered!\n";
        
        // 清空所有上下文
        $this->connectionContext = [];
        
        // 多次强制GC
        for ($i = 0; $i < 5; $i++) {
            gc_collect_cycles();
        }
        
        // 重置应用状态（如果安全的话）
        if ($this->app && method_exists($this->app, 'clearCache')) {
            $this->app->clearCache();
        }
        
        $this->memoryStats['context_cleanups']++;
    }

    public function onMessage(TcpConnection $connection, WorkermanRequest $request): void
    {
        $startTime = microtime(true);
        
        // 增加请求计数
        $this->memoryStats['request_count']++;
        
        // 每20个请求强制GC
        if ($this->memoryStats['request_count'] % 20 === 0) {
            gc_collect_cycles();
        }

        try {
            // 轻量化上下文设置
            $this->setLightweightContext($connection, $request, $startTime);

            // 处理请求 - 直接使用现有应用实例，不创建新的
            $response = $this->handleRequestDirectly($request);
            
            $connection->send($response);

            // 记录慢请求
            $duration = (microtime(true) - $startTime) * 1000;
            if ($duration > 500) {
                echo "Slow request: {$request->uri()} took {$duration}ms\n";
            }

        } catch (Throwable $e) {
            $this->handleError($connection, $e);
        } finally {
            // 立即清理上下文
            $this->clearLightweightContext($connection);
            
            // 定期清理
            if ($this->memoryStats['request_count'] % 100 === 0) {
                $this->aggressiveCleanup();
            }
        }
    }

    /**
     * 轻量化上下文设置
     */
    protected function setLightweightContext(TcpConnection $connection, WorkermanRequest $request, float $startTime): void
    {
        // 只存储最必要的信息
        $this->connectionContext[$connection->id] = [
            'start_time' => $startTime,
            'method' => $request->method(),
            'uri' => $request->uri(),
            'created_at' => time(),
        ];
        
        // 限制上下文数量
        if (count($this->connectionContext) > 100) {
            $this->cleanupOldestContexts();
        }
    }

    /**
     * 清理最旧的上下文
     */
    protected function cleanupOldestContexts(): void
    {
        // 按创建时间排序，删除最旧的一半
        uasort($this->connectionContext, fn($a, $b) => $a['created_at'] <=> $b['created_at']);
        
        $toKeep = (int)(count($this->connectionContext) * 0.5);
        $this->connectionContext = array_slice($this->connectionContext, -$toKeep, null, true);
        
        echo "Cleaned oldest contexts, kept {$toKeep}\n";
    }

    /**
     * 直接处理请求，避免创建新应用实例
     */
    protected function handleRequestDirectly(WorkermanRequest $request): WorkermanResponse
    {
        // 保存原始全局变量
        $originalGet = $_GET;
        $originalPost = $_POST;
        $originalFiles = $_FILES;
        $originalCookie = $_COOKIE;
        $originalServer = $_SERVER;

        try {
            // 设置全局变量
            $_GET = $request->get() ?? [];
            $_POST = $request->post() ?? [];
            $_FILES = $request->file() ?? [];
            $_COOKIE = $request->cookie() ?? [];
            $_SERVER = $this->buildServerArray($request);

            // 直接使用现有应用实例处理请求
            $psr7Request = $this->convertWorkermanRequestToPsr7($request);
            $psr7Response = $this->handleRequest($psr7Request);
            
            return $this->convertPsr7ToWorkermanResponse($psr7Response);

        } finally {
            // 恢复全局变量
            $_GET = $originalGet;
            $_POST = $originalPost;
            $_FILES = $originalFiles;
            $_COOKIE = $originalCookie;
            $_SERVER = $originalServer;
        }
    }

    /**
     * 构建 $_SERVER 数组
     */
    protected function buildServerArray(WorkermanRequest $request): array
    {
        return array_merge($_SERVER, [
            'REQUEST_METHOD' => $request->method(),
            'REQUEST_URI' => $request->uri(),
            'PATH_INFO' => $request->path(),
            'QUERY_STRING' => $request->queryString(),
            'HTTP_HOST' => $request->host() ?: 'localhost:8080',
            'SERVER_NAME' => $request->host() ?: 'localhost',
            'HTTP_USER_AGENT' => $request->header('user-agent', 'Workerman/5.0'),
            'CONTENT_TYPE' => $request->header('content-type', ''),
            'CONTENT_LENGTH' => $request->header('content-length', ''),
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PORT' => '8080',
            'HTTPS' => '',
            'argv' => [],
            'argc' => 0,
        ]);
    }

    // 其他必要的方法...
    public function start(array $options = []): void
    {
        if (!$this->worker) {
            $this->boot();
        }

        echo "Starting Optimized Workerman HTTP Server...\n";
        echo "Memory limit: " . ini_get('memory_limit') . "\n";
        echo "Aggressive memory management: ENABLED\n";
        echo "Listening on: {$this->worker->getSocketName()}\n\n";

        Worker::$command = 'start';
        Worker::runAll();
    }

    public function getName(): string { return 'workerman-optimized'; }
    public function isAvailable(): bool { return $this->isSupported(); }
    public function isSupported(): bool { return class_exists(Worker::class); }
    public function getPriority(): int { return 110; }

    // 实现其他必要的方法...
    public function onConnect(TcpConnection $connection): void {}
    public function onClose(TcpConnection $connection): void 
    {
        unset($this->connectionContext[$connection->id]);
    }
    public function onError(TcpConnection $connection, $code, $msg): void 
    {
        echo "Connection error: $msg\n";
    }
    public function onWorkerStop(Worker $worker): void 
    {
        echo "Optimized Worker #{$worker->id} stopped\n";
    }

    /**
     * 获取内存统计
     */
    public function getMemoryStats(): array
    {
        return array_merge($this->memoryStats, [
            'current_memory' => memory_get_usage(true),
            'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round($this->memoryStats['peak_usage'] / 1024 / 1024, 2),
            'context_count' => count($this->connectionContext),
        ]);
    }

    // 清理方法
    protected function clearLightweightContext(TcpConnection $connection): void
    {
        unset($this->connectionContext[$connection->id]);
    }

    protected function cleanupExpiredContexts(): void
    {
        $now = time();
        $expired = [];
        
        foreach ($this->connectionContext as $id => $context) {
            if ($now - $context['created_at'] > 60) { // 60秒过期
                $expired[] = $id;
            }
        }
        
        foreach ($expired as $id) {
            unset($this->connectionContext[$id]);
        }
        
        if (!empty($expired)) {
            echo "Cleaned " . count($expired) . " expired contexts\n";
        }
    }

    protected function handleError(TcpConnection $connection, Throwable $e): void
    {
        $response = new WorkermanResponse(500);
        $response->withHeader('Content-Type', 'application/json');
        $response->withBody(json_encode([
            'error' => $e->getMessage(),
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
        ]));
        $connection->send($response);
    }

    // 需要实现的抽象方法
    protected function convertWorkermanRequestToPsr7(WorkermanRequest $request): ServerRequestInterface
    {
        return $this->requestCreator->fromArrays(
            $this->buildServerArray($request),
            $request->header() ?? [],
            $request->cookie() ?? [],
            $request->get() ?? [],
            $request->post() ?? [],
            $request->file() ?? [],
            $request->rawBody() ?: null
        );
    }

    protected function convertPsr7ToWorkermanResponse(ResponseInterface $psr7Response): WorkermanResponse
    {
        $response = new WorkermanResponse($psr7Response->getStatusCode());
        
        foreach ($psr7Response->getHeaders() as $name => $values) {
            $response->withHeader($name, implode(', ', $values));
        }
        
        $response->withBody((string) $psr7Response->getBody());
        return $response;
    }
}
