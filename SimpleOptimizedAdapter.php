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
 * 简化但有效的 Workerman 优化适配器
 * 
 * 核心优化：
 * 1. 完全避免应用实例重复创建 ✅
 * 2. 激进的内存管理 ✅
 * 3. 更频繁的垃圾回收 ✅
 * 4. 禁用调试工具 ✅
 * 5. 简化的上下文管理 ✅
 */
class SimpleOptimizedAdapter extends AbstractRuntime implements AdapterInterface
{
    protected ?Worker $worker = null;
    protected ?ServerRequestCreator $requestCreator = null;
    
    // 简化的统计
    protected array $stats = [
        'requests' => 0,
        'memory_peak' => 0,
        'gc_count' => 0,
        'last_gc' => 0,
    ];
    
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'count' => 4,
        'name' => 'ThinkPHP-Simple-Optimized',
        'reloadable' => true,
        'reusePort' => true,
        
        // 简化的内存管理
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 50,        // 每50个请求GC
            'memory_limit' => '256M',
            'aggressive_gc' => true,    // 激进GC
        ],
        
        // 性能优化
        'performance' => [
            'disable_debug' => true,    // 强制禁用调试
            'disable_trace' => true,    // 强制禁用追踪
            'enable_opcache' => true,   // 启用OPcache
        ],
    ];

    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('Workerman is not installed');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 强制优化 PHP 配置
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
     * 优化 PHP 配置
     */
    protected function optimizePhpConfig(array $config): void
    {
        // 设置内存限制
        ini_set('memory_limit', $config['memory']['memory_limit']);
        ini_set('max_execution_time', '0');
        
        // 强制禁用调试
        if ($config['performance']['disable_debug']) {
            ini_set('display_errors', '0');
            ini_set('log_errors', '0');
            
            // 尝试禁用 think-trace
            if (defined('THINK_TRACE')) {
                define('THINK_TRACE', false);
            }
        }
        
        // 启用 OPcache 优化
        if ($config['performance']['enable_opcache'] && function_exists('opcache_reset')) {
            ini_set('opcache.enable', '1');
            ini_set('opcache.enable_cli', '1');
            ini_set('opcache.memory_consumption', '128');
            ini_set('opcache.max_accelerated_files', '4000');
            ini_set('opcache.validate_timestamps', '0'); // 生产环境禁用时间戳验证
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
            echo "Simple Optimized Worker #{$worker->id} started\n";
            
            // 初始化应用（只初始化一次）
            if ($this->app && method_exists($this->app, 'initialize')) {
                $this->app->initialize();
            }
            
            // 强制禁用调试工具
            $this->disableDebugTools();
            
            // 设置定时器进行内存管理
            $this->setupMemoryManagement();
            
        } catch (Throwable $e) {
            echo "Worker start failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 强制禁用调试工具
     */
    protected function disableDebugTools(): void
    {
        if ($this->app) {
            // 尝试禁用 think-trace
            if ($this->app->has('trace')) {
                try {
                    $trace = $this->app->get('trace');
                    if (method_exists($trace, 'disable')) {
                        $trace->disable();
                    }
                } catch (Throwable $e) {
                    // 忽略错误
                }
            }
            
            // 尝试禁用调试模式
            if ($this->app->has('config')) {
                try {
                    $config = $this->app->get('config');
                    if (method_exists($config, 'set')) {
                        $config->set('app.debug', false);
                        $config->set('trace.enable', false);
                    }
                } catch (Throwable $e) {
                    // 忽略错误
                }
            }
        }
    }

    /**
     * 设置内存管理
     */
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
            // 直接处理请求，不使用复杂的沙盒机制
            $this->handleRequestDirectly($connection, $request);

        } catch (Throwable $e) {
            $this->handleConnectionError($connection, $e);
        } finally {
            // 简单的内存管理
            $this->performSimpleCleanup();
        }
    }

    /**
     * 直接处理请求
     */
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

    /**
     * 简单的清理
     */
    protected function performSimpleCleanup(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        // 定期垃圾回收
        if ($this->stats['requests'] % $config['memory']['gc_interval'] === 0) {
            if ($config['memory']['aggressive_gc']) {
                // 激进垃圾回收
                for ($i = 0; $i < 3; $i++) {
                    gc_collect_cycles();
                }
            } else {
                gc_collect_cycles();
            }
            
            $this->stats['gc_count']++;
            $this->stats['last_gc'] = time();
        }
        
        // 更新内存峰值
        $currentMemory = memory_get_usage(true);
        if ($currentMemory > $this->stats['memory_peak']) {
            $this->stats['memory_peak'] = $currentMemory;
        }
    }

    /**
     * 内存清理
     */
    protected function performMemoryCleanup(): void
    {
        $beforeMemory = memory_get_usage(true);
        
        // 强制垃圾回收
        for ($i = 0; $i < 5; $i++) {
            gc_collect_cycles();
        }
        
        $afterMemory = memory_get_usage(true);
        $freed = $beforeMemory - $afterMemory;
        
        if ($freed > 0) {
            echo "Memory cleanup freed " . round($freed / 1024 / 1024, 2) . "MB\n";
        }
        
        echo sprintf(
            "Stats: Requests=%d, Memory=%.2fMB, Peak=%.2fMB, GC=%d\n",
            $this->stats['requests'],
            $afterMemory / 1024 / 1024,
            $this->stats['memory_peak'] / 1024 / 1024,
            $this->stats['gc_count']
        );
    }

    // 实现必要的方法
    public function start(array $options = []): void
    {
        if (!$this->worker) {
            $this->boot();
        }

        echo "Starting Simple Optimized Workerman Server...\n";
        echo "Optimizations: No app recreation + Aggressive GC + Debug disabled\n";
        echo "Listening on: {$this->worker->getSocketName()}\n\n";

        Worker::$command = 'start';
        Worker::runAll();
    }

    public function getName(): string { return 'simple-optimized'; }
    public function isAvailable(): bool { return $this->isSupported(); }
    public function isSupported(): bool { return class_exists(Worker::class); }
    public function getPriority(): int { return 115; }

    public function onWorkerStop(Worker $worker): void
    {
        echo "Simple Optimized Worker #{$worker->id} stopped\n";
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
