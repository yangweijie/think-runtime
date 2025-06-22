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
 * 智能调试检测的 Workerman 优化适配器
 * 
 * 核心优化：
 * 1. 智能检测调试模式（参考 think-worker）✅
 * 2. 自动禁用调试工具 ✅
 * 3. 避免应用实例重复创建 ✅
 * 4. 激进的内存管理 ✅
 */
class SmartOptimizedAdapter extends AbstractRuntime implements AdapterInterface
{
    protected ?Worker $worker = null;
    protected ?ServerRequestCreator $requestCreator = null;
    
    // 调试状态
    protected bool $isDebugMode = false;
    protected bool $debugToolsDisabled = false;
    
    // 统计
    protected array $stats = [
        'requests' => 0,
        'memory_peak' => 0,
        'gc_count' => 0,
        'debug_detections' => 0,
    ];
    
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'count' => 4,
        'name' => 'ThinkPHP-Smart-Optimized',
        'reloadable' => true,
        'reusePort' => true,
        
        // 智能调试检测
        'debug' => [
            'auto_detect' => true,          // 自动检测调试模式
            'force_disable' => false,       // 强制禁用调试（生产环境）
            'disable_trace' => true,        // 禁用 think-trace
            'disable_debug_tools' => true,  // 禁用其他调试工具
        ],
        
        // 内存管理
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 50,
            'memory_limit' => '256M',
            'aggressive_gc' => true,
        ],
    ];

    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('Workerman is not installed');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 智能检测和优化调试模式
        $this->smartDebugDetection($config);

        // 优化 PHP 配置
        $this->optimizePhpConfig($config);

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

    /**
     * 智能调试检测 - 参考 think-worker 的实现
     */
    protected function smartDebugDetection(array $config): void
    {
        if (!$config['debug']['auto_detect'] && !$config['debug']['force_disable']) {
            return;
        }

        echo "=== 智能调试检测 ===\n";

        // 多种方式检测调试模式
        $debugSources = [];

        // 1. 环境变量检测
        $envDebug = getenv('APP_DEBUG') === 'true';
        $debugSources['env'] = $envDebug;

        // 2. 配置文件检测
        $configDebug = false;
        if ($this->app && $this->app->has('config')) {
            $configDebug = $this->app->config->get('app.debug', false);
        }
        $debugSources['config'] = $configDebug;

        // 3. 应用方法检测
        $appDebug = false;
        if ($this->app && method_exists($this->app, 'isDebug')) {
            $appDebug = $this->app->isDebug();
        }
        $debugSources['app_method'] = $appDebug;

        // 4. trace 服务检测
        $traceExists = false;
        if ($this->app && $this->app->has('trace')) {
            $traceExists = true;
        }
        $debugSources['trace_service'] = $traceExists;

        // 综合判断
        $this->isDebugMode = $envDebug || $configDebug || $appDebug || $traceExists;

        // 强制禁用模式
        if ($config['debug']['force_disable']) {
            $this->isDebugMode = false;
            echo "🔒 强制禁用调试模式\n";
        }

        echo "调试检测结果:\n";
        foreach ($debugSources as $source => $value) {
            echo "  {$source}: " . ($value ? '✅ 调试' : '❌ 生产') . "\n";
        }
        echo "综合判断: " . ($this->isDebugMode ? '🔧 调试模式' : '🚀 生产模式') . "\n";

        // 如果是生产模式，禁用调试工具
        if (!$this->isDebugMode && $config['debug']['disable_debug_tools']) {
            $this->disableDebugTools($config);
        }

        $this->stats['debug_detections']++;
    }

    /**
     * 禁用调试工具 - 参考 think-worker 的策略
     */
    protected function disableDebugTools(array $config): void
    {
        if ($this->debugToolsDisabled || !$this->app) {
            return;
        }

        echo "🛠️  禁用调试工具:\n";

        // 1. 禁用 think-trace
        if ($config['debug']['disable_trace'] && $this->app->has('trace')) {
            try {
                $this->app->delete('trace');
                echo "  ✅ think-trace 已禁用\n";
            } catch (Throwable $e) {
                echo "  ⚠️  think-trace 禁用失败: " . $e->getMessage() . "\n";
            }
        }

        // 2. 强制设置调试配置为 false
        if ($this->app->has('config')) {
            try {
                $config = $this->app->config;
                $config->set('app.debug', false);
                $config->set('trace.enable', false);
                $config->set('app.trace', false);
                echo "  ✅ 调试配置已禁用\n";
            } catch (Throwable $e) {
                echo "  ⚠️  配置设置失败: " . $e->getMessage() . "\n";
            }
        }

        // 3. 禁用其他可能的调试服务
        $debugServices = ['debug', 'debugbar', 'profiler', 'monitor'];
        foreach ($debugServices as $service) {
            if ($this->app->has($service)) {
                try {
                    $this->app->delete($service);
                    echo "  ✅ {$service} 服务已禁用\n";
                } catch (Throwable $e) {
                    // 忽略错误
                }
            }
        }

        $this->debugToolsDisabled = true;
    }

    /**
     * 优化 PHP 配置
     */
    protected function optimizePhpConfig(array $config): void
    {
        // 设置内存限制
        ini_set('memory_limit', $config['memory']['memory_limit']);
        ini_set('max_execution_time', '0');
        
        // 如果是生产模式，禁用错误显示
        if (!$this->isDebugMode) {
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        }
        
        // 启用 OPcache 优化
        if (function_exists('opcache_reset')) {
            ini_set('opcache.enable', '1');
            ini_set('opcache.enable_cli', '1');
            ini_set('opcache.memory_consumption', '128');
            ini_set('opcache.max_accelerated_files', '4000');
            
            // 生产模式禁用时间戳验证
            if (!$this->isDebugMode) {
                ini_set('opcache.validate_timestamps', '0');
            }
        }
        
        // 垃圾回收优化
        gc_enable();
        ini_set('zend.enable_gc', '1');
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
            echo "Smart Optimized Worker #{$worker->id} started\n";
            echo "Debug mode: " . ($this->isDebugMode ? 'ENABLED' : 'DISABLED') . "\n";
            echo "Debug tools: " . ($this->debugToolsDisabled ? 'DISABLED' : 'ENABLED') . "\n";
            
            // 初始化应用（只初始化一次）
            if ($this->app && method_exists($this->app, 'initialize')) {
                $this->app->initialize();
            }
            
            // 在每个进程中重新检测和禁用调试工具
            $config = array_merge($this->defaultConfig, $this->config);
            $this->smartDebugDetection($config);
            
            // 设置定时器
            $this->setupMemoryManagement();
            
        } catch (Throwable $e) {
            echo "Worker start failed: " . $e->getMessage() . "\n";
        }
    }

    protected function setupMemoryManagement(): void
    {
        Timer::add(30, function() {
            $this->performMemoryCleanup();
        });
    }

    public function onMessage(TcpConnection $connection, WorkermanRequest $request): void
    {
        $this->stats['requests']++;

        try {
            // 直接处理请求
            $this->handleRequestDirectly($connection, $request);

        } catch (Throwable $e) {
            $this->handleConnectionError($connection, $e);
        } finally {
            // 简单的内存管理
            $this->performSimpleCleanup();
        }
    }

    protected function handleRequestDirectly(TcpConnection $connection, WorkermanRequest $request): void
    {
        // 保存原始全局变量
        $originalGlobals = [
            'GET' => $_GET,
            'POST' => $_POST,
            'FILES' => $_FILES,
            'COOKIE' => $_COOKIE,
            'SERVER' => $_SERVER,
        ];

        try {
            // 设置请求环境
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
            ]);

            // 直接使用现有应用实例处理请求
            $psr7Request = $this->convertWorkermanRequestToPsr7($request);
            $psr7Response = $this->handleRequest($psr7Request);
            $workermanResponse = $this->convertPsr7ToWorkermanResponse($psr7Response);
            
            $connection->send($workermanResponse);

        } finally {
            // 恢复全局变量
            $_GET = $originalGlobals['GET'];
            $_POST = $originalGlobals['POST'];
            $_FILES = $originalGlobals['FILES'];
            $_COOKIE = $originalGlobals['COOKIE'];
            $_SERVER = $originalGlobals['SERVER'];
        }
    }

    protected function performSimpleCleanup(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        // 定期垃圾回收
        if ($this->stats['requests'] % $config['memory']['gc_interval'] === 0) {
            if ($config['memory']['aggressive_gc']) {
                for ($i = 0; $i < 3; $i++) {
                    gc_collect_cycles();
                }
            } else {
                gc_collect_cycles();
            }
            
            $this->stats['gc_count']++;
        }
        
        // 更新内存峰值
        $currentMemory = memory_get_usage(true);
        if ($currentMemory > $this->stats['memory_peak']) {
            $this->stats['memory_peak'] = $currentMemory;
        }
    }

    protected function performMemoryCleanup(): void
    {
        $beforeMemory = memory_get_usage(true);
        
        // 强制垃圾回收
        for ($i = 0; $i < 5; $i++) {
            gc_collect_cycles();
        }
        
        $afterMemory = memory_get_usage(true);
        $freed = $beforeMemory - $afterMemory;
        
        echo sprintf(
            "Stats: Requests=%d, Memory=%.2fMB, Peak=%.2fMB, GC=%d, Debug=%s\n",
            $this->stats['requests'],
            $afterMemory / 1024 / 1024,
            $this->stats['memory_peak'] / 1024 / 1024,
            $this->stats['gc_count'],
            $this->isDebugMode ? 'ON' : 'OFF'
        );
    }

    // 实现必要的方法
    public function start(array $options = []): void
    {
        if (!$this->worker) {
            $this->boot();
        }

        echo "Starting Smart Optimized Workerman Server...\n";
        echo "Features: Smart debug detection + Auto optimization\n";
        echo "Listening on: {$this->worker->getSocketName()}\n\n";

        Worker::$command = 'start';
        Worker::runAll();
    }

    public function getName(): string { return 'smart-optimized'; }
    public function isAvailable(): bool { return $this->isSupported(); }
    public function isSupported(): bool { return class_exists(Worker::class); }
    public function getPriority(): int { return 120; }

    public function onWorkerStop(Worker $worker): void
    {
        echo "Smart Optimized Worker #{$worker->id} stopped\n";
        echo "Final stats: " . json_encode($this->stats) . "\n";
    }

    protected function handleConnectionError(TcpConnection $connection, Throwable $e): void
    {
        $response = new WorkermanResponse(500);
        $response->withHeader('Content-Type', 'application/json');
        $response->withBody(json_encode([
            'error' => $e->getMessage(),
            'debug_mode' => $this->isDebugMode,
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
        ]));
        $connection->send($response);
    }

    // 转换方法
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

    public function terminate(): void { $this->stop(); }
    public function stop(): void { if ($this->worker !== null) Worker::stopAll(); }
    public function run(): void { $this->start(); }
}
