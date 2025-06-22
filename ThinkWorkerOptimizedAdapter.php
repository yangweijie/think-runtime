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
 * 基于 think-worker 优化思路的 Workerman 适配器
 * 
 * 核心优化：
 * 1. Sandbox 沙盒机制 - 应用实例隔离和复用
 * 2. 智能重置器 - 清理特定实例而非重建整个应用
 * 3. 克隆而非重建 - 使用 clone 而不是 new
 * 4. 内存管理优化 - 更精确的内存控制
 */
class ThinkWorkerOptimizedAdapter extends AbstractRuntime implements AdapterInterface
{
    protected ?Worker $worker = null;
    protected ?ServerRequestCreator $requestCreator = null;
    
    // 应用快照 - 核心优化点
    protected $appSnapshot = null;
    protected array $initialServices = [];
    protected $initialConfig = null;
    
    // 轻量化统计
    protected array $stats = [
        'requests' => 0,
        'memory_peak' => 0,
        'last_gc' => 0,
        'resets' => 0,
    ];
    
    // 需要重置的实例列表（参考 think-worker）
    protected array $resetInstances = [
        'log', 'session', 'view', 'response', 'cookie', 'request'
    ];
    
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'count' => 4,
        'name' => 'ThinkWorker-Optimized',
        'reloadable' => true,
        'reusePort' => true,
        
        // think-worker 风格的优化配置
        'sandbox' => [
            'enable' => true,
            'reset_instances' => ['log', 'session', 'view', 'response', 'cookie', 'request'],
            'clone_services' => true,
        ],
        
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 50,
            'reset_interval' => 100,
            'memory_limit' => '256M',
        ],
        
        'performance' => [
            'preload_routes' => true,
            'preload_middleware' => true,
            'enable_opcache_reset' => false,
        ],
    ];

    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('Workerman is not installed');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 初始化请求创建器
        $this->requestCreator = new ServerRequestCreator(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory
        );

        // 创建 Worker
        $listen = 'http://' . $config['host'] . ':' . $config['port'];
        $this->worker = new Worker($listen);

        $this->worker->count = $config['count'];
        $this->worker->name = $config['name'];
        $this->worker->reloadable = $config['reloadable'];
        $this->worker->reusePort = $config['reusePort'];

        $this->bindEvents();
    }

    protected function bindEvents(): void
    {
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        $this->worker->onMessage = [$this, 'onMessage'];
        $this->worker->onWorkerStop = [$this, 'onWorkerStop'];
    }

    public function onWorkerStart(Worker $worker): void
    {
        try {
            echo "ThinkWorker Optimized Worker #{$worker->id} started\n";
            
            // 创建应用快照（关键优化）
            $this->createAppSnapshot();
            
            // 设置定时器
            $this->setupOptimizedTimer();
            
        } catch (Throwable $e) {
            echo "Worker start failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 创建应用快照 - think-worker 的核心思想
     */
    protected function createAppSnapshot(): void
    {
        if (!$this->app) {
            return;
        }

        // 初始化应用
        if (method_exists($this->app, 'initialize')) {
            $this->app->initialize();
        }

        // 保存初始状态
        $this->saveInitialState();
        
        // 创建快照（使用 clone 而不是 new）
        $this->appSnapshot = clone $this->app;
        
        echo "Application snapshot created\n";
    }

    /**
     * 保存初始状态
     */
    protected function saveInitialState(): void
    {
        // 保存初始配置
        if ($this->app->has('config')) {
            $this->initialConfig = $this->app->config;
        }
        
        // 保存初始服务
        // 这里可以根据需要保存其他初始状态
    }

    public function onMessage(TcpConnection $connection, WorkermanRequest $request): void
    {
        $startTime = microtime(true);
        $this->stats['requests']++;

        try {
            // 使用沙盒机制处理请求
            $this->runInSandbox(function() use ($connection, $request) {
                $this->handleHttpRequest($connection, $request);
            });

        } catch (Throwable $e) {
            $this->handleConnectionError($connection, $e);
        } finally {
            // 定期优化
            $this->performPeriodicOptimization();
        }
    }

    /**
     * 沙盒机制 - think-worker 的核心
     */
    protected function runInSandbox(callable $callback): void
    {
        // 创建沙盒应用实例
        $sandboxApp = $this->createSandboxApp();
        
        // 临时替换全局应用实例
        $originalApp = $this->app;
        $this->app = $sandboxApp;
        
        try {
            // 执行回调
            call_user_func($callback);
            
        } finally {
            // 恢复原始应用
            $this->app = $originalApp;
            
            // 清理沙盒应用
            $this->cleanupSandboxApp($sandboxApp);
        }
    }

    /**
     * 创建沙盒应用 - 使用 clone 而不是 new
     */
    protected function createSandboxApp()
    {
        if (!$this->appSnapshot) {
            throw new RuntimeException('Application snapshot not created');
        }

        // 克隆快照（关键优化：clone 比 new 快很多）
        $sandboxApp = clone $this->appSnapshot;
        
        // 重置特定实例（而不是重建整个应用）
        $this->resetAppInstances($sandboxApp);
        
        return $sandboxApp;
    }

    /**
     * 重置应用实例 - think-worker 的精髓
     */
    protected function resetAppInstances($app): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $resetInstances = $config['sandbox']['reset_instances'] ?? $this->resetInstances;
        
        // 只重置必要的实例，而不是整个应用
        foreach ($resetInstances as $instance) {
            if ($app->has($instance)) {
                $app->delete($instance);
            }
        }
        
        // 重置配置（如果需要）
        if ($this->initialConfig !== null) {
            $app->instance('config', $this->initialConfig);
        }
        
        $this->stats['resets']++;
    }

    /**
     * 清理沙盒应用
     */
    protected function cleanupSandboxApp($app): void
    {
        // 清理实例
        if (method_exists($app, 'clearInstances')) {
            $app->clearInstances();
        }

        // 清理所有可能的引用
        if (method_exists($app, 'flush')) {
            $app->flush();
        }

        // 强制垃圾回收
        unset($app);
        gc_collect_cycles();
    }

    /**
     * 处理 HTTP 请求
     */
    protected function handleHttpRequest(TcpConnection $connection, WorkermanRequest $request): void
    {
        // 保存原始全局变量
        $originalGlobals = $this->saveGlobals();
        
        try {
            // 设置请求环境
            $this->setupRequestEnvironment($request);
            
            // 处理请求
            $psr7Request = $this->convertWorkermanRequestToPsr7($request);
            $psr7Response = $this->handleRequest($psr7Request);
            $workermanResponse = $this->convertPsr7ToWorkermanResponse($psr7Response);
            
            $connection->send($workermanResponse);
            
        } finally {
            // 恢复全局变量
            $this->restoreGlobals($originalGlobals);
        }
    }

    /**
     * 保存全局变量
     */
    protected function saveGlobals(): array
    {
        return [
            'GET' => $_GET,
            'POST' => $_POST,
            'FILES' => $_FILES,
            'COOKIE' => $_COOKIE,
            'SERVER' => $_SERVER,
        ];
    }

    /**
     * 恢复全局变量
     */
    protected function restoreGlobals(array $globals): void
    {
        $_GET = $globals['GET'];
        $_POST = $globals['POST'];
        $_FILES = $globals['FILES'];
        $_COOKIE = $globals['COOKIE'];
        $_SERVER = $globals['SERVER'];
    }

    /**
     * 设置请求环境
     */
    protected function setupRequestEnvironment(WorkermanRequest $request): void
    {
        $_GET = $request->get() ?? [];
        $_POST = $request->post() ?? [];
        $_FILES = $request->file() ?? [];
        $_COOKIE = $request->cookie() ?? [];
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => $request->method(),
            'REQUEST_URI' => $request->uri(),
            'PATH_INFO' => $request->path(),
            'QUERY_STRING' => $request->queryString(),
            'HTTP_HOST' => $request->host() ?: 'localhost:8080',
            'SERVER_NAME' => $request->host() ?: 'localhost',
            'CONTENT_TYPE' => $request->header('content-type', ''),
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'argv' => [],
            'argc' => 0,
        ]);
    }

    /**
     * 优化的定时器
     */
    protected function setupOptimizedTimer(): void
    {
        Timer::add(30, function() {
            $this->performDeepOptimization();
        });
    }

    /**
     * 定期优化
     */
    protected function performPeriodicOptimization(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        // 每 N 个请求执行 GC
        if ($this->stats['requests'] % $config['memory']['gc_interval'] === 0) {
            gc_collect_cycles();
            $this->stats['last_gc'] = time();
        }
        
        // 更新内存峰值
        $currentMemory = memory_get_usage(true);
        if ($currentMemory > $this->stats['memory_peak']) {
            $this->stats['memory_peak'] = $currentMemory;
        }
    }

    /**
     * 深度优化
     */
    protected function performDeepOptimization(): void
    {
        // 强制垃圾回收
        for ($i = 0; $i < 3; $i++) {
            gc_collect_cycles();
        }
        
        // 重新创建应用快照（如果内存使用过高）
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage > 100 * 1024 * 1024) { // 100MB
            echo "High memory usage detected, recreating snapshot\n";
            $this->createAppSnapshot();
        }
        
        echo sprintf(
            "Stats: Requests=%d, Memory=%.2fMB, Resets=%d\n",
            $this->stats['requests'],
            $memoryUsage / 1024 / 1024,
            $this->stats['resets']
        );
    }

    // 实现必要的抽象方法
    public function start(array $options = []): void
    {
        if (!$this->worker) {
            $this->boot();
        }

        echo "Starting ThinkWorker Optimized Server...\n";
        echo "Sandbox mechanism: ENABLED\n";
        echo "Clone-based optimization: ENABLED\n";
        echo "Listening on: {$this->worker->getSocketName()}\n\n";

        Worker::$command = 'start';
        Worker::runAll();
    }

    public function getName(): string { return 'thinkworker-optimized'; }
    public function isAvailable(): bool { return $this->isSupported(); }
    public function isSupported(): bool { return class_exists(Worker::class); }
    public function getPriority(): int { return 120; }

    public function onWorkerStop(Worker $worker): void
    {
        echo "ThinkWorker Optimized Worker #{$worker->id} stopped\n";
        echo "Final stats: " . json_encode($this->stats) . "\n";
    }

    protected function handleConnectionError(TcpConnection $connection, Throwable $e): void
    {
        $response = new WorkermanResponse(500);
        $response->withHeader('Content-Type', 'application/json');
        $response->withBody(json_encode([
            'error' => $e->getMessage(),
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
        ]));
        $connection->send($response);
    }

    // 实现转换方法
    protected function convertWorkermanRequestToPsr7(WorkermanRequest $request): ServerRequestInterface
    {
        return $this->requestCreator->fromArrays(
            $_SERVER,
            $request->header() ?? [],
            $_COOKIE,
            $_GET,
            $_POST,
            $_FILES,
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

    public function terminate(): void
    {
        $this->stop();
    }

    public function stop(): void
    {
        if ($this->worker !== null) {
            Worker::stopAll();
        }
    }

    public function run(): void
    {
        $this->start();
    }
}
