<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;
use RuntimeException;
use Throwable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * Workerman适配器
 * 基于Workerman提供高性能HTTP服务器支持
 * 参考webman框架的实现模式
 */
class WorkermanAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * Workerman Worker实例
     *
     * @var Worker|null
     */
    protected ?Worker $worker = null;

    /**
     * 请求计数器
     *
     * @var int
     */
    protected int $requestCount = 0;

    /**
     * 内存使用统计
     *
     * @var array
     */
    protected array $memoryStats = [
        'peak_usage' => 0,
        'request_count' => 0,
        'last_cleanup' => 0,
    ];

    /**
     * 连接上下文存储
     *
     * @var array
     */
    protected array $connectionContext = [];

    /**
     * 定时器ID列表
     *
     * @var array
     */
    protected array $timers = [];

    /**
     * 原始全局变量
     *
     * @var array
     */
    protected array $originalGlobals = [];

    /**
     * 当前请求对象
     *
     * @var Request|null
     */
    protected ?Request $currentRequest = null;

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'count' => 4, // 进程数量
        'name' => 'think-workerman',
        'protocol' => 'http',
        'context' => [],
        'reuse_port' => false,
        'transport' => 'tcp',
        
        // 内存管理配置
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 100, // 每100个请求GC一次
            'context_cleanup_interval' => 60, // 60秒清理一次上下文
            'max_context_size' => 1000, // 最大上下文数量
        ],
        
        // 性能监控配置
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 1000, // 毫秒
            'memory_limit' => '256M',
        ],
        
        // 定时器配置
        'timer' => [
            'enable' => false,
            'interval' => 60, // 秒
        ],
        
        // 日志配置
        'log' => [
            'enable' => true,
            'file' => 'runtime/logs/workerman.log',
            'level' => 'info',
        ],
        
        // 静态文件配置
        'static_file' => [
            'enable' => true,
            'document_root' => 'public',
            'enable_negotiation' => false,
        ],

        // 压缩配置
        'compression' => [
            'enable' => true,
            'type' => 'gzip', // gzip, deflate
            'level' => 6, // 压缩级别 1-9
            'min_length' => 1024, // 最小压缩长度 (字节)
            'types' => [
                'text/html',
                'text/css',
                'text/javascript',
                'text/xml',
                'text/plain',
                'application/javascript',
                'application/json',
                'application/xml',
                'application/rss+xml',
                'application/atom+xml',
                'image/svg+xml',
            ],
        ],

        // Keep-Alive 配置
        'keep_alive' => [
            'enable' => true,
            'timeout' => 60,        // keep-alive 超时时间 (秒)
            'max_requests' => 1000, // 每个连接最大请求数
            'close_on_idle' => 300, // 空闲连接关闭时间 (秒)
        ],

        // Socket 配置
        'socket' => [
            'so_reuseport' => true,  // 启用端口复用
            'tcp_nodelay' => true,   // 禁用 Nagle 算法
            'so_keepalive' => true,  // 启用 TCP keep-alive
            'backlog' => 1024,       // 监听队列长度
        ],
    ];

    /**
     * 启动适配器
     *
     * @return void
     */
    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('Workerman is not available');
        }

        // 设置无限执行时间
        set_time_limit(0);

        // 初始化应用
        $this->app->initialize();

        // 创建Worker
        $this->createWorker();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->worker === null) {
            $this->boot();
        }

        $config = array_merge($this->defaultConfig, $this->config);

        echo "Workerman HTTP Server starting...\n";
        echo "Listening on: {$config['host']}:{$config['port']}\n";
        echo "Worker processes: {$config['count']}\n";
        echo "Worker name: {$config['name']}\n";
        echo "Memory limit: " . ini_get('memory_limit') . "\n";
        echo "Press Ctrl+C to stop the server\n\n";

        // 启动Worker
        Worker::runAll();
    }

    /**
     * 启动运行时
     *
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void
    {
        $this->setConfig($options);

        // 设置 Workerman 命令行参数
        global $argv;
        $originalArgv = $argv;

        // 确保有正确的命令行参数
        $argv = [$_SERVER['SCRIPT_NAME'] ?? 'think', 'start'];

        try {
            $this->run();
        } finally {
            // 恢复原始 argv
            $argv = $originalArgv;
        }
    }

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void
    {
        // 清理定时器
        foreach ($this->timers as $timerId) {
            Timer::del($timerId);
        }
        $this->timers = [];

        // 停止Worker
        if ($this->worker !== null) {
            $this->worker->stopAll();
        }
    }

    /**
     * 停止适配器
     *
     * @return void
     */
    public function terminate(): void
    {
        $this->stop();
    }

    /**
     * 获取适配器名称
     *
     * @return string
     */
    public function getName(): string
    {
        return 'workerman';
    }

    /**
     * 检查运行时是否可用
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isSupported();
    }

    /**
     * 检查适配器是否支持当前环境
     *
     * @return bool
     */
    public function isSupported(): bool
    {
        return class_exists('Workerman\\Worker') &&
               class_exists('Workerman\\Connection\\TcpConnection') &&
               class_exists('Workerman\\Protocols\\Http\\Request') &&
               class_exists('Workerman\\Protocols\\Http\\Response');
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 85; // 中等优先级
    }

    /**
     * 获取运行时配置（合并默认配置）
     *
     * @return array
     */
    public function getConfig(): array
    {
        return array_merge($this->defaultConfig, $this->config);
    }

    /**
     * 创建Worker
     *
     * @return void
     */
    protected function createWorker(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        // 创建HTTP Worker
        $listen = $config['protocol'] . '://' . $config['host'] . ':' . $config['port'];
        $this->worker = new Worker($listen, $config['context']);
        
        // 设置Worker属性
        $this->worker->count = $config['count'];
        $this->worker->name = $config['name'];
        $this->worker->reusePort = $config['reuse_port'];
        $this->worker->transport = $config['transport'];
        
        // 设置事件回调
        $this->setupWorkerEvents();
    }

    /**
     * 设置Worker事件回调
     *
     * @return void
     */
    protected function setupWorkerEvents(): void
    {
        // Worker启动事件
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        
        // 处理HTTP请求
        $this->worker->onMessage = [$this, 'onMessage'];
        
        // 连接关闭事件
        $this->worker->onClose = [$this, 'onClose'];
        
        // Worker停止事件
        $this->worker->onWorkerStop = [$this, 'onWorkerStop'];
    }

    /**
     * Worker启动事件处理
     *
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker): void
    {
        echo "Worker #{$worker->id} started (PID: " . getmypid() . ")\n";
        
        $config = array_merge($this->defaultConfig, $this->config);
        
        // 设置定时器
        if ($config['timer']['enable']) {
            $this->setupTimers();
        }
        
        // 设置内存监控
        if ($config['monitor']['enable']) {
            $this->setupMemoryMonitor();
        }
    }

    /**
     * 处理HTTP请求
     *
     * @param TcpConnection $connection
     * @param Request $request
     * @return void
     */
    public function onMessage(TcpConnection $connection, Request $request): void
    {
        $startTime = microtime(true);
        $this->requestCount++;
        $this->memoryStats['request_count']++;
        
        try {
            // 定期垃圾回收
            $this->performPeriodicGC();
            
            // 处理请求
            $response = $this->handleWorkermanRequest($request);
            
            // 发送响应
            $connection->send($response);
            
            // 性能监控
            $this->monitorRequestPerformance($startTime);
            
        } catch (Throwable $e) {
            // 错误处理
            $errorResponse = $this->handleWorkermanError($e);
            $connection->send($errorResponse);
        } finally {
            // 清理连接上下文
            $this->cleanupConnectionContext($connection);
        }
    }

    /**
     * 连接关闭事件处理
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose(TcpConnection $connection): void
    {
        // 清理连接相关的上下文
        $this->cleanupConnectionContext($connection);
    }

    /**
     * Worker停止事件处理
     *
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStop(Worker $worker): void
    {
        echo "Worker #{$worker->id} stopped\n";

        // 清理定时器
        foreach ($this->timers as $timerId) {
            Timer::del($timerId);
        }
        $this->timers = [];
    }

    /**
     * 处理Workerman HTTP请求
     *
     * @param Request $request
     * @return Response
     */
    protected function handleWorkermanRequest(Request $request): Response
    {
        // 保存原始全局变量
        $originalGet = $_GET;
        $originalPost = $_POST;
        $originalFiles = $_FILES;
        $originalCookie = $_COOKIE;
        $originalServer = $_SERVER;

        try {
            // 设置全局变量
            $_GET = $request->get();
            $_POST = $request->post();
            $_FILES = $request->file();
            $_COOKIE = $request->cookie();

            // 构建 $_SERVER 变量
            $_SERVER = array_merge($_SERVER, [
                'REQUEST_METHOD' => $request->method(),
                'REQUEST_URI' => $request->uri(),
                'PATH_INFO' => $request->path(),
                'QUERY_STRING' => $request->queryString(),
                'HTTP_HOST' => $request->host(),
                'HTTP_USER_AGENT' => $request->header('user-agent', 'Workerman/1.0'),
                'HTTP_ACCEPT' => $request->header('accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
                'CONTENT_TYPE' => $request->header('content-type', ''),
                'CONTENT_LENGTH' => $request->header('content-length', '0'),
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'REQUEST_TIME' => time(),
                'REQUEST_TIME_FLOAT' => microtime(true),
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php',
                'GATEWAY_INTERFACE' => 'CGI/1.1',
                'SERVER_SOFTWARE' => 'Workerman/1.0',
                'REMOTE_ADDR' => $this->getClientIp($request),
                'REMOTE_HOST' => $this->getClientIp($request),
                'DOCUMENT_ROOT' => getcwd() . '/public',
                'REQUEST_SCHEME' => 'http',
                'SERVER_PORT' => $this->getConfig()['port'],
                'HTTPS' => '',
            ]);

            // 处理请求 - 直接使用 Workerman 请求处理
            return $this->handleWorkermanDirectRequest($request);

        } finally {
            // 恢复原始全局变量
            $_GET = $originalGet;
            $_POST = $originalPost;
            $_FILES = $originalFiles;
            $_COOKIE = $originalCookie;
            $_SERVER = $originalServer;
        }
    }

    /**
     * 处理Workerman错误
     *
     * @param Throwable $e
     * @return Response
     */
    protected function handleWorkermanError(Throwable $e): Response
    {
        $content = json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);

        return new Response(
            500,
            ['Content-Type' => 'application/json'],
            $content
        );
    }

    /**
     * 设置定时器
     *
     * @return void
     */
    protected function setupTimers(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $interval = $config['timer']['interval'];

        // 添加统计定时器
        $timerId = Timer::add($interval, function() {
            $this->outputStats();
        });

        $this->timers[] = $timerId;
    }

    /**
     * 设置内存监控
     *
     * @return void
     */
    protected function setupMemoryMonitor(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 每30秒检查一次内存使用
        $timerId = Timer::add(30, function() use ($config) {
            $currentMemory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);

            // 更新统计
            if ($peakMemory > $this->memoryStats['peak_usage']) {
                $this->memoryStats['peak_usage'] = $peakMemory;
            }

            // 检查内存限制
            $memoryLimit = $config['monitor']['memory_limit'];
            $limitBytes = $this->parseMemoryLimit($memoryLimit);

            if ($currentMemory > $limitBytes * 0.8) {
                echo "Warning: Memory usage is high: " . round($currentMemory / 1024 / 1024, 2) . "MB\n";

                // 强制垃圾回收
                gc_collect_cycles();
            }
        });

        $this->timers[] = $timerId;
    }

    /**
     * 执行定期垃圾回收
     *
     * @return void
     */
    protected function performPeriodicGC(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if (!($config['memory']['enable_gc'] ?? true)) {
            return;
        }

        $gcInterval = $config['memory']['gc_interval'] ?? 100;

        if ($this->requestCount % $gcInterval === 0) {
            $beforeMemory = memory_get_usage(true);
            gc_collect_cycles();
            $afterMemory = memory_get_usage(true);

            $freed = $beforeMemory - $afterMemory;
            if ($freed > 0) {
                echo "Workerman GC freed " . round($freed / 1024 / 1024, 2) . "MB memory\n";
            }
        }
    }

    /**
     * 监控请求性能
     *
     * @param float $startTime
     * @return void
     */
    protected function monitorRequestPerformance(float $startTime): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if (!($config['monitor']['enable'] ?? true)) {
            return;
        }

        $duration = (microtime(true) - $startTime) * 1000; // 转换为毫秒
        $threshold = $config['monitor']['slow_request_threshold'] ?? 1000;

        if ($duration > $threshold) {
            echo "Slow request detected: {$duration}ms\n";
        }
    }

    /**
     * 清理连接上下文
     *
     * @param TcpConnection $connection
     * @return void
     */
    protected function cleanupConnectionContext(TcpConnection $connection): void
    {
        $connectionId = spl_object_hash($connection);

        if (isset($this->connectionContext[$connectionId])) {
            unset($this->connectionContext[$connectionId]);
        }

        // 定期清理过期上下文
        $config = array_merge($this->defaultConfig, $this->config);
        $cleanupInterval = $config['memory']['context_cleanup_interval'] ?? 60;

        $now = time();
        if ($now - $this->memoryStats['last_cleanup'] > $cleanupInterval) {
            $this->performContextCleanup();
            $this->memoryStats['last_cleanup'] = $now;
        }
    }

    /**
     * 执行上下文清理
     *
     * @return void
     */
    protected function performContextCleanup(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $maxContextSize = $config['memory']['max_context_size'] ?? 1000;

        if (count($this->connectionContext) > $maxContextSize) {
            $toRemove = count($this->connectionContext) - (int)($maxContextSize * 0.8);
            $removed = 0;

            foreach ($this->connectionContext as $key => $context) {
                if ($removed >= $toRemove) {
                    break;
                }
                unset($this->connectionContext[$key]);
                $removed++;
            }

            if ($removed > 0) {
                echo "Workerman cleaned {$removed} connection contexts\n";
                gc_collect_cycles();
            }
        }
    }

    /**
     * 输出统计信息
     *
     * @return void
     */
    protected function outputStats(): void
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        echo "Workerman Stats - ";
        echo "Requests: {$this->memoryStats['request_count']}, ";
        echo "Memory: " . round($currentMemory / 1024 / 1024, 2) . "MB, ";
        echo "Peak: " . round($peakMemory / 1024 / 1024, 2) . "MB, ";
        echo "Contexts: " . count($this->connectionContext) . "\n";
    }

    /**
     * 解析内存限制字符串
     *
     * @param string $memoryLimit
     * @return int
     */
    protected function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (int) $memoryLimit;
        }
    }

    /**
     * 获取内存使用统计
     *
     * @return array
     */
    public function getMemoryStats(): array
    {
        return array_merge($this->memoryStats, [
            'current_memory' => memory_get_usage(true),
            'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round($this->memoryStats['peak_usage'] / 1024 / 1024, 2),
            'connection_contexts' => count($this->connectionContext),
            'active_timers' => count($this->timers),
        ]);
    }

    /**
     * 添加定时器
     *
     * @param float $interval 间隔时间（秒）
     * @param callable $callback 回调函数
     * @param bool $persistent 是否持久化
     * @return int 定时器ID
     */
    public function addTimer(float $interval, callable $callback, bool $persistent = true): int
    {
        $timerId = $persistent ? Timer::add($interval, $callback) : Timer::add($interval, $callback, [], false);
        $this->timers[] = $timerId;
        return $timerId;
    }

    /**
     * 删除定时器
     *
     * @param int $timerId 定时器ID
     * @return bool
     */
    public function delTimer(int $timerId): bool
    {
        $key = array_search($timerId, $this->timers);
        if ($key !== false) {
            unset($this->timers[$key]);
            return Timer::del($timerId);
        }
        return false;
    }

    /**
     * 获取客户端IP地址
     *
     * @param Request $request
     * @return string
     */
    protected function getClientIp(Request $request): string
    {
        // 尝试从连接中获取远程地址
        $connection = $request->connection ?? null;
        if ($connection && isset($connection->getRemoteAddress)) {
            $remoteAddress = $connection->getRemoteAddress();
            if ($remoteAddress) {
                // 解析 IP:PORT 格式，只返回 IP 部分
                $parts = explode(':', $remoteAddress);
                return $parts[0] ?? '127.0.0.1';
            }
        }

        // 从 HTTP 头中获取
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            $ip = $request->header(strtolower(str_replace('HTTP_', '', $header)));
            if ($ip && $ip !== 'unknown') {
                // 处理多个IP的情况，取第一个
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * 直接处理 Workerman 请求（不转换为 PSR-7）
     *
     * @param Request $request
     * @return Response
     */
    protected function handleWorkermanDirectRequest(Request $request): Response
    {
        try {
            // 保存当前请求对象
            $this->currentRequest = $request;

            $path = $request->path();

            // 检查是否是特殊的状态页面路径
            if ($path === '/_workerman_status' || $path === '/_status') {
                return $this->createStatusResponse($request);
            }

            // 设置全局变量以兼容传统PHP环境
            $this->setGlobalVariables($request);

            try {
                // 创建 PSR-7 兼容的请求对象
                $psrRequest = $this->createPsrRequest($request);

                // 调用父类的 handleRequest 方法处理 ThinkPHP 路由
                $psrResponse = $this->handleRequest($psrRequest);

                // 将 PSR-7 响应转换为 Workerman 响应
                return $this->convertPsrResponseToWorkerman($psrResponse);

            } finally {
                // 恢复全局变量
                $this->restoreGlobalVariables();
            }

            // 添加 Keep-Alive 头
            $this->addKeepAliveHeaders($request, $headers);

            // 应用 gzip 压缩
            $compressedData = $this->applyCompression($request, $responseBody, $headers);

            return new Response(200, $headers, $compressedData['body']);

        } catch (Throwable $e) {
            // 错误响应
            $errorData = [
                'error' => true,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            $errorBody = json_encode($errorData, JSON_UNESCAPED_UNICODE);
            $errorHeaders = [
                'Content-Type' => 'application/json; charset=utf-8',
            ];

            // 对错误响应也应用压缩
            $compressedError = $this->applyCompression($request, $errorBody, $errorHeaders);

            return new Response(500, $errorHeaders, $compressedError['body']);
        }
    }

    /**
     * 创建 PSR-7 兼容的请求对象
     *
     * @param Request $workermanRequest
     * @return ServerRequestInterface
     */
    protected function createPsrRequest(Request $workermanRequest): ServerRequestInterface
    {
        // 使用 Nyholm PSR-7 实现创建请求对象
        $psr17Factory = new Psr17Factory();

        // 创建 URI
        $uri = $psr17Factory->createUri($workermanRequest->uri());

        // 创建请求
        $psrRequest = $psr17Factory->createServerRequest($workermanRequest->method(), $uri);

        // 添加头信息
        foreach ($workermanRequest->header() as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        // 添加查询参数
        $psrRequest = $psrRequest->withQueryParams($workermanRequest->get());

        // 添加解析的 body (POST 数据)
        $psrRequest = $psrRequest->withParsedBody($workermanRequest->post());

        // 添加 Cookie
        $psrRequest = $psrRequest->withCookieParams($workermanRequest->cookie());

        // 添加上传文件
        $uploadedFiles = $this->convertWorkermanFiles($workermanRequest->file());
        $psrRequest = $psrRequest->withUploadedFiles($uploadedFiles);

        // 添加服务器参数
        $serverParams = [
            'REQUEST_METHOD' => $workermanRequest->method(),
            'REQUEST_URI' => $workermanRequest->uri(),
            'PATH_INFO' => $workermanRequest->path(),
            'QUERY_STRING' => $workermanRequest->queryString(),
            'HTTP_HOST' => $workermanRequest->host(),
            'REMOTE_ADDR' => $this->getClientIp($workermanRequest),
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ];

        $psrRequest = $psrRequest->withAttribute('serverParams', $serverParams);

        return $psrRequest;
    }

    /**
     * 转换 Workerman 文件格式为 PSR-7 UploadedFile
     *
     * @param array $workermanFiles
     * @return array
     */
    protected function convertWorkermanFiles(array $workermanFiles): array
    {
        $uploadedFiles = [];
        $psr17Factory = new Psr17Factory();

        foreach ($workermanFiles as $name => $file) {
            if (is_array($file) && isset($file['tmp_name'])) {
                $stream = $psr17Factory->createStreamFromFile($file['tmp_name']);
                $uploadedFiles[$name] = $psr17Factory->createUploadedFile(
                    $stream,
                    $file['size'] ?? 0,
                    $file['error'] ?? UPLOAD_ERR_OK,
                    $file['name'] ?? null,
                    $file['type'] ?? null
                );
            }
        }

        return $uploadedFiles;
    }

    /**
     * 设置全局变量以兼容传统PHP环境
     *
     * @param Request $request
     * @return void
     */
    protected function setGlobalVariables(Request $request): void
    {
        // 保存原始全局变量
        $this->originalGlobals = [
            'GET' => $_GET,
            'POST' => $_POST,
            'FILES' => $_FILES,
            'COOKIE' => $_COOKIE,
            'SERVER' => $_SERVER,
        ];

        // 设置新的全局变量
        $_GET = $request->get() ?? [];
        $_POST = $request->post() ?? [];
        $_FILES = $request->file() ?? [];
        $_COOKIE = $request->cookie() ?? [];

        // 构建 $_SERVER 变量
        $serverPort = '8080'; // 默认端口
        $hostHeader = $request->header('host');

        if ($hostHeader && strpos($hostHeader, ':') !== false) {
            $httpHost = $hostHeader;
            $serverName = explode(':', $hostHeader)[0];
            $serverPort = explode(':', $hostHeader)[1];
        } elseif ($hostHeader) {
            $httpHost = $hostHeader;
            $serverName = $hostHeader;
        } else {
            $httpHost = 'localhost:' . $serverPort;
            $serverName = 'localhost';
        }

        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => $request->method(),
            'REQUEST_URI' => $request->uri(),
            'PATH_INFO' => $request->path(),
            'QUERY_STRING' => $request->queryString() ?? '',
            'HTTP_HOST' => $httpHost,
            'SERVER_NAME' => $serverName,
            'SERVER_PORT' => $serverPort,
            'HTTP_USER_AGENT' => $request->header('user-agent', 'Workerman'),
            'HTTP_ACCEPT' => $request->header('accept', '*/*'),
            'CONTENT_TYPE' => $request->header('content-type', ''),
            'CONTENT_LENGTH' => $request->header('content-length', ''),
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php',
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'SERVER_SOFTWARE' => 'Workerman/' . Worker::VERSION,
            'REMOTE_ADDR' => $this->getClientIp($request),
            'REMOTE_HOST' => $this->getClientIp($request),
            'DOCUMENT_ROOT' => getcwd() . '/public',
            'REQUEST_SCHEME' => 'http',
            'HTTPS' => '',
        ]);
    }

    /**
     * 恢复原始全局变量
     *
     * @return void
     */
    protected function restoreGlobalVariables(): void
    {
        if (isset($this->originalGlobals)) {
            $_GET = $this->originalGlobals['GET'];
            $_POST = $this->originalGlobals['POST'];
            $_FILES = $this->originalGlobals['FILES'];
            $_COOKIE = $this->originalGlobals['COOKIE'];
            $_SERVER = $this->originalGlobals['SERVER'];
        }
    }

    /**
     * 将 PSR-7 响应转换为 Workerman 响应
     *
     * @param ResponseInterface $psrResponse
     * @return Response
     */
    protected function convertPsrResponseToWorkerman(ResponseInterface $psrResponse): Response
    {
        // 获取响应状态码
        $statusCode = $psrResponse->getStatusCode();

        // 获取响应头
        $headers = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        // 获取响应体
        $body = (string) $psrResponse->getBody();

        // 添加 Keep-Alive 头
        $this->addKeepAliveHeaders($this->currentRequest, $headers);

        // 应用压缩
        $compressedData = $this->applyCompression($this->currentRequest, $body, $headers);

        return new Response($statusCode, $headers, $compressedData['body']);
    }

    /**
     * 应用响应压缩
     *
     * @param Request $request
     * @param string $body
     * @param array &$headers
     * @return array
     */
    protected function applyCompression(Request $request, string $body, array &$headers): array
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $compressionConfig = $config['compression'] ?? [];

        // 检查是否启用压缩
        if (!($compressionConfig['enable'] ?? true)) {
            return ['body' => $body, 'compressed' => false];
        }

        // 检查内容长度是否达到压缩阈值
        $minLength = $compressionConfig['min_length'] ?? 1024;
        if (strlen($body) < $minLength) {
            return ['body' => $body, 'compressed' => false];
        }

        // 检查内容类型是否支持压缩
        $contentType = $headers['Content-Type'] ?? '';
        $supportedTypes = $compressionConfig['types'] ?? [];

        $shouldCompress = false;
        foreach ($supportedTypes as $type) {
            if (str_contains($contentType, $type)) {
                $shouldCompress = true;
                break;
            }
        }

        if (!$shouldCompress) {
            return ['body' => $body, 'compressed' => false];
        }

        // 检查客户端是否支持压缩
        $acceptEncoding = $request->header('accept-encoding', '');
        $compressionType = $compressionConfig['type'] ?? 'gzip';

        if (!str_contains($acceptEncoding, $compressionType) && !str_contains($acceptEncoding, '*')) {
            return ['body' => $body, 'compressed' => false];
        }

        // 执行压缩
        $compressedBody = $this->compressContent($body, $compressionType, $compressionConfig);

        if ($compressedBody === false) {
            return ['body' => $body, 'compressed' => false];
        }

        // 添加压缩相关头信息
        $headers['Content-Encoding'] = $compressionType;
        $headers['Content-Length'] = strlen($compressedBody);
        $headers['Vary'] = 'Accept-Encoding';

        return ['body' => $compressedBody, 'compressed' => true];
    }

    /**
     * 压缩内容
     *
     * @param string $content
     * @param string $type
     * @param array $config
     * @return string|false
     */
    protected function compressContent(string $content, string $type, array $config)
    {
        $level = $config['level'] ?? 6;

        switch ($type) {
            case 'gzip':
                if (!function_exists('gzencode')) {
                    return false;
                }
                return gzencode($content, $level);

            case 'deflate':
                if (!function_exists('gzdeflate')) {
                    return false;
                }
                return gzdeflate($content, $level);

            default:
                return false;
        }
    }

    /**
     * 检查是否应该压缩响应
     *
     * @param Request $request
     * @param string $contentType
     * @param int $contentLength
     * @return bool
     */
    protected function shouldCompress(Request $request, string $contentType, int $contentLength): bool
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $compressionConfig = $config['compression'] ?? [];

        // 检查是否启用压缩
        if (!($compressionConfig['enable'] ?? true)) {
            return false;
        }

        // 检查内容长度
        $minLength = $compressionConfig['min_length'] ?? 1024;
        if ($contentLength < $minLength) {
            return false;
        }

        // 检查内容类型
        $supportedTypes = $compressionConfig['types'] ?? [];
        foreach ($supportedTypes as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取客户端支持的压缩类型
     *
     * @param Request $request
     * @return array
     */
    protected function getSupportedCompressions(Request $request): array
    {
        $acceptEncoding = $request->header('accept-encoding', '');
        $supported = [];

        if (str_contains($acceptEncoding, 'gzip')) {
            $supported[] = 'gzip';
        }

        if (str_contains($acceptEncoding, 'deflate')) {
            $supported[] = 'deflate';
        }

        return $supported;
    }

    /**
     * 添加 Keep-Alive 响应头
     *
     * @param Request $request
     * @param array &$headers
     * @return void
     */
    protected function addKeepAliveHeaders(Request $request, array &$headers): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $keepAliveConfig = $config['keep_alive'] ?? [];

        // 检查是否启用 keep-alive
        if (!($keepAliveConfig['enable'] ?? true)) {
            $headers['Connection'] = 'close';
            return;
        }

        // 检查客户端是否支持 keep-alive
        $connection = $request->header('connection', '');
        if (strtolower($connection) === 'close') {
            $headers['Connection'] = 'close';
            return;
        }

        // 添加 keep-alive 头
        $timeout = $keepAliveConfig['timeout'] ?? 60;
        $maxRequests = $keepAliveConfig['max_requests'] ?? 1000;

        $headers['Connection'] = 'keep-alive';
        $headers['Keep-Alive'] = "timeout={$timeout}, max={$maxRequests}";
    }

    /**
     * 配置 Worker 的 Socket 选项
     *
     * @return void
     */
    protected function configureSocketOptions(): void
    {
        if ($this->worker === null) {
            return;
        }

        $config = array_merge($this->defaultConfig, $this->config);
        $socketConfig = $config['socket'] ?? [];

        // 获取 socket 资源
        $socket = $this->worker->getMainSocket();
        if (!$socket) {
            return;
        }

        // 设置 socket 选项
        if ($socketConfig['so_reuseport'] ?? true) {
            // 端口复用 (需要 Linux 3.9+ 支持)
            if (defined('SO_REUSEPORT')) {
                socket_set_option($socket, SOL_SOCKET, SO_REUSEPORT, 1);
            }
        }

        if ($socketConfig['tcp_nodelay'] ?? true) {
            // 禁用 Nagle 算法，减少延迟
            if (defined('IPPROTO_TCP') && defined('TCP_NODELAY')) {
                socket_set_option($socket, IPPROTO_TCP, TCP_NODELAY, 1);
            }
        }

        if ($socketConfig['so_keepalive'] ?? true) {
            // 启用 TCP keep-alive
            socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        }
    }

    /**
     * 获取连接统计信息
     *
     * @return array
     */
    public function getConnectionStats(): array
    {
        $stats = [
            'total_connections' => count($this->connectionContext),
            'active_timers' => count($this->timers),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];

        if ($this->worker !== null) {
            $stats['worker_id'] = $this->worker->id ?? 0;
            $stats['worker_name'] = $this->worker->name ?? 'unknown';
        }

        return $stats;
    }

    /**
     * 检查连接是否应该保持活跃
     *
     * @param TcpConnection $connection
     * @return bool
     */
    protected function shouldKeepConnectionAlive(TcpConnection $connection): bool
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $keepAliveConfig = $config['keep_alive'] ?? [];

        if (!($keepAliveConfig['enable'] ?? true)) {
            return false;
        }

        $connectionId = spl_object_hash($connection);
        $context = $this->connectionContext[$connectionId] ?? [];

        // 检查请求数量限制
        $maxRequests = $keepAliveConfig['max_requests'] ?? 1000;
        $requestCount = $context['request_count'] ?? 0;

        if ($requestCount >= $maxRequests) {
            return false;
        }

        // 检查连接时间
        $timeout = $keepAliveConfig['timeout'] ?? 60;
        $lastRequest = $context['last_request'] ?? time();

        if (time() - $lastRequest > $timeout) {
            return false;
        }

        return true;
    }

    /**
     * 创建状态页面响应
     *
     * @param Request $request
     * @return Response
     */
    protected function createStatusResponse(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method();
        $accept = $request->header('accept', '');

        // 检查是否是浏览器请求 (期望 HTML)
        $isBrowserRequest = str_contains($accept, 'text/html') ||
                           str_contains($request->header('user-agent', ''), 'Mozilla');

        if ($isBrowserRequest) {
            // 返回 HTML 响应给浏览器
            $responseBody = $this->createHtmlResponse($path, $method);
            $headers = [
                'Content-Type' => 'text/html; charset=utf-8',
                'Server' => 'Workerman-ThinkPHP-Runtime',
                'X-Powered-By' => 'ThinkPHP-Runtime/Workerman',
            ];
        } else {
            // 返回 JSON 响应给 API 客户端
            $data = [
                'message' => 'Workerman Runtime Status',
                'path' => $path,
                'method' => $method,
                'timestamp' => time(),
                'server' => 'Workerman/' . Worker::VERSION,
                'php_version' => PHP_VERSION,
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                'status' => 'running',
            ];

            $responseBody = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $headers = [
                'Content-Type' => 'application/json; charset=utf-8',
                'Access-Control-Allow-Origin' => '*',
                'Server' => 'Workerman-ThinkPHP-Runtime',
                'X-Powered-By' => 'ThinkPHP-Runtime/Workerman',
            ];
        }

        // 添加 Keep-Alive 头
        $this->addKeepAliveHeaders($request, $headers);

        // 应用压缩
        $compressedData = $this->applyCompression($request, $responseBody, $headers);

        return new Response(200, $headers, $compressedData['body']);
    }

    /**
     * 创建 HTML 响应给浏览器
     *
     * @param string $path
     * @param string $method
     * @return string
     */
    protected function createHtmlResponse(string $path, string $method): string
    {
        $data = [
            'message' => 'Hello from Workerman Runtime!',
            'path' => $path,
            'method' => $method,
            'timestamp' => time(),
            'server' => 'Workerman/' . Worker::VERSION,
            'php_version' => PHP_VERSION,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
        ];

        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workerman Runtime - ThinkPHP</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-item { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
        .stat-label { font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase; }
        .stat-value { font-size: 18px; color: #333; margin-top: 5px; }
        .success { color: #28a745; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Workerman Runtime</h1>

        <div class="info">
            <strong class="success">✅ Workerman Runtime 运行正常！</strong><br>
            这是一个基于 Workerman 的高性能 ThinkPHP 运行时环境。
        </div>

        <div class="stats">
            <div class="stat-item">
                <div class="stat-label">请求路径</div>
                <div class="stat-value">' . htmlspecialchars($data['path']) . '</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">请求方法</div>
                <div class="stat-value">' . htmlspecialchars($data['method']) . '</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">服务器</div>
                <div class="stat-value">' . htmlspecialchars($data['server']) . '</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">PHP 版本</div>
                <div class="stat-value">' . htmlspecialchars($data['php_version']) . '</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">内存使用</div>
                <div class="stat-value">' . htmlspecialchars($data['memory_usage']) . '</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">时间戳</div>
                <div class="stat-value">' . date('Y-m-d H:i:s', $data['timestamp']) . '</div>
            </div>
        </div>

        <h2>🔧 功能特性</h2>
        <ul>
            <li>✅ <strong>高性能</strong>：基于 Workerman 的异步非阻塞架构</li>
            <li>✅ <strong>Keep-Alive</strong>：支持 HTTP 长连接，提升性能</li>
            <li>✅ <strong>Gzip 压缩</strong>：自动压缩响应，节省带宽</li>
            <li>✅ <strong>多进程</strong>：支持多进程并发处理</li>
            <li>✅ <strong>内存管理</strong>：智能垃圾回收，防止内存泄漏</li>
            <li>✅ <strong>跨平台</strong>：支持 Windows、Linux、macOS</li>
        </ul>

        <h2>📊 API 测试</h2>
        <p>您可以使用以下方式测试 API：</p>
        <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;">
# JSON API 响应
curl -H "Accept: application/json" http://127.0.0.1:8080/

# 性能测试
wrk -t4 -c100 -d30s http://127.0.0.1:8080/
        </pre>

        <div class="footer">
            <p>Powered by <strong>ThinkPHP Runtime</strong> + <strong>Workerman</strong></p>
            <p>高性能 PHP 应用运行时环境</p>
        </div>
    </div>
</body>
</html>';
    }
}
