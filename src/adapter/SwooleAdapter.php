<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use ErrorException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Runtime;
use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Nyholm\Psr7Server\ServerRequestCreator;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * Swoole适配器
 * 提供Swoole HTTP服务器支持
 */
class SwooleAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * Swoole HTTP服务器实例
     *
     * @var Server|null
     */
    protected ?Server $server = null;

    /**
     * 请求创建器（复用）
     *
     * @var ServerRequestCreator|null
     */
    protected ?ServerRequestCreator $requestCreator = null;

    /**
     * 协程上下文存储
     *
     * @var array
     */
    protected array $coroutineContext = [];

    /**
     * 请求计数器（用于定期重置调试状态）
     *
     * @var int
     */
    protected int $requestCounter = 0;

    /**
     * 中间件列表
     *
     * @var array
     */
    protected array $middlewares = [];

    /**
     * 静态文件 MIME 类型映射
     *
     * @var array
     */
    protected array $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'html' => 'text/html',
        'htm' => 'text/html',
        'txt' => 'text/plain',
        'json' => 'application/json',
        'xml' => 'application/xml',
    ];

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 9501,
        'mode' => 3, // SWOOLE_PROCESS
        'sock_type' => 1, // SWOOLE_SOCK_TCP
        'settings' => [
            'worker_num' => 4,
            'task_worker_num' => 0,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'debug_mode' => 0,
            'enable_static_handler' => true,
            'document_root' => '',
            'daemonize' => 0,
            'enable_coroutine' => 1,
            'max_coroutine' => 100000,
            'socket_buffer_size' => 2097152,
            'max_wait_time' => 60,
            'reload_async' => true,
            'max_conn' => 1024,
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time' => 600,
            'buffer_output_size' => 2097152,
            'enable_unsafe_event' => false,
            'discard_timeout_request' => true,
            // 协程相关配置
            'hook_flags' => 268435455, // SWOOLE_HOOK_ALL
            'enable_preemptive_scheduler' => true,
        ],
        // 静态文件配置
        'static_file' => [
            'enable' => true,
            'document_root' => 'public',
            'cache_time' => 3600,
            'allowed_extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'html', 'htm', 'txt', 'json', 'xml'],
        ],
        // WebSocket 配置
        'websocket' => [
            'enable' => false,
        ],
        // 性能监控配置
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 1000, // 毫秒
        ],
        // 中间件配置
        'middleware' => [
            'cors' => [
                'enable' => true,
                'allow_origin' => '*',
                'allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'allow_headers' => 'Content-Type, Authorization, X-Requested-With',
            ],
            'security' => [
                'enable' => true,
            ],
        ],
    ];

    /**
     * 启动适配器
     *
     * @return void
     * @throws ErrorException
     */
    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('Swoole extension is not installed');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 设置协程 Hook
        if (isset($config['settings']['hook_flags']) && class_exists('\Swoole\Runtime')) {
            Runtime::enableCoroutine($config['settings']['hook_flags']);
        }

        // 初始化请求创建器（复用实例）
        $this->requestCreator = new ServerRequestCreator(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory
        );

        // 设置文档根目录
        if (empty($config['settings']['document_root'])) {
            $config['settings']['document_root'] = $this->getPublicPath();
        }

        $this->server = new Server(
            $config['host'],
            $config['port'],
            $config['mode'],
            $config['sock_type']
        );

        // 设置服务器配置
        $this->server->set($config['settings']);

        // 绑定事件
        $this->bindEvents();

        // 初始化中间件
        $this->initMiddlewares();

        // 初始化应用
        $this->app->initialize();
    }

    /**
     * 初始化中间件
     *
     * @return void
     */
    protected function initMiddlewares(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 添加 CORS 中间件
        if ($config['middleware']['cors']['enable'] ?? true) {
            $this->addMiddleware([$this, 'corsMiddleware']);
        }

        // 添加安全中间件
        if ($config['middleware']['security']['enable'] ?? true) {
            $this->addMiddleware([$this, 'securityMiddleware']);
        }
    }

    /**
     * 添加中间件
     *
     * @param callable $middleware
     * @return void
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * 运行适配器
     *
     * @return void
     * @throws ErrorException
     */
    public function run(): void
    {
        if ($this->server === null) {
            $this->boot();
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 设置无限执行时间，因为Swoole服务器需要持续运行
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        echo "Swoole HTTP Server starting...\n";
        echo "Listening on: {$config['host']}:{$config['port']}\n";
        echo "Document root: " . ($config['settings']['document_root'] ?? 'not set') . "\n";
        echo "Worker processes: " . ($config['settings']['worker_num'] ?? 4) . "\n";

        $this->server->start();
    }

    /**
     * 启动运行时
     *
     * @param array $options 启动选项
     * @return void
     * @throws ErrorException
     */
    public function start(array $options = []): void
    {
        $this->setConfig($options);
        $this->run();
    }

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->server !== null) {
            $this->server->shutdown();
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
        return 'swoole';
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
        return extension_loaded('swoole') && class_exists(Server::class);
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 100;
    }

    /**
     * 获取配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 动态设置文档根目录（与boot方法保持一致）
        if (!isset($config['settings']['document_root']) || $config['settings']['document_root'] === '/tmp') {
            $config['settings']['document_root'] = getcwd() . '/public';
            // 如果public目录不存在，使用当前目录
            if (!is_dir($config['settings']['document_root'])) {
                $config['settings']['document_root'] = getcwd();
            }
        }

        // 如果有嵌套的settings配置，将其合并到顶层
        if (isset($config['settings']) && is_array($config['settings'])) {
            $config = array_merge($config, $config['settings']);
        }

        // 如果用户配置中有直接的配置项，它们应该覆盖settings中的配置
        if (!empty($this->config)) {
            $config = array_merge($config, $this->config);
        }

        return $config;
    }

    /**
     * 获取Swoole服务器实例
     *
     * @return Server|null
     */
    public function getSwooleServer(): ?Server
    {
        return $this->server;
    }

    /**
     * 绑定Swoole事件
     *
     * @return void
     */
    protected function bindEvents(): void
    {
        // 工作进程启动事件
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);

        // HTTP请求事件
        $this->server->on('Request', [$this, 'onRequest']);

        // 服务器启动事件
        $this->server->on('Start', [$this, 'onStart']);

        // 服务器关闭事件
        $this->server->on('Shutdown', [$this, 'onShutdown']);

        // Worker 错误事件
        $this->server->on('WorkerError', [$this, 'onWorkerError']);

        // Worker 退出事件
        $this->server->on('WorkerExit', [$this, 'onWorkerExit']);

        // WebSocket 事件
        if ($this->isWebSocketEnabled()) {
            $this->server->on('Open', [$this, 'onWebSocketOpen']);
            $this->server->on('Message', [$this, 'onWebSocketMessage']);
            $this->server->on('Close', [$this, 'onWebSocketClose']);
        }

        // 如果启用了Task进程，绑定Task事件
        $config = array_merge($this->defaultConfig, $this->config);
        if (isset($config['settings']['task_worker_num']) && $config['settings']['task_worker_num'] > 0) {
            $this->server->on('Task', [$this, 'onTask']);
            $this->server->on('Finish', [$this, 'onFinish']);
        }
    }

    /**
     * 工作进程启动事件处理
     *
     * @param Server $server Swoole服务器实例
     * @param int $workerId 工作进程ID
     * @return void
     */
    public function onWorkerStart(Server $server, int $workerId): void
    {
        try {
            // 设置进程标题
            if (function_exists('cli_set_process_title')) {
                cli_set_process_title("swoole-worker-{$workerId}");
            }

            // 设置工作进程的执行时间限制和信号处理
            set_time_limit(0);

            // 禁用一些可能导致问题的信号处理
            if (function_exists('pcntl_signal')) {
                pcntl_signal(SIGALRM, SIG_IGN);  // 忽略SIGALRM信号
                pcntl_signal(SIGTERM, SIG_DFL);  // 默认处理SIGTERM
                pcntl_signal(SIGINT, SIG_DFL);   // 默认处理SIGINT
            }

            // 判断是否为Task进程
            $isTaskWorker = $workerId >= $server->setting['worker_num'];

            if (!$isTaskWorker) {
                // 只在HTTP Worker进程中初始化应用
                if ($this->app && method_exists($this->app, 'initialize')) {
                    // 简化的初始化，避免耗时操作
                    try {
                        // 临时禁用错误报告，避免初始化过程中的警告影响进程
                        $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);

                        $this->app->initialize();

                        // 恢复错误报告
                        error_reporting($oldErrorReporting);

                        // 初始化时重置调试状态
                        $this->resetDebugState();

                    } catch (Throwable $e) {
                        echo "Worker #{$workerId} app initialization failed: " . $e->getMessage() . "\n";
                        // 不抛出异常，让进程继续运行
                    }
                }
                echo "HTTP Worker #{$workerId} started\n";
            } else {
                echo "Task Worker #{$workerId} started\n";
            }

        } catch (Throwable $e) {
            echo "Worker #{$workerId} start failed: " . $e->getMessage() . "\n";
            // 不抛出异常，让进程继续运行
        }
    }

    /**
     * HTTP请求事件处理（改进版）
     *
     * @param SwooleRequest $request Swoole请求对象
     * @param SwooleResponse $response Swoole响应对象
     * @return void
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response): void
    {
        $startTime = microtime(true);

        // 使用协程处理请求
        go(function () use ($request, $response, $startTime) {
            try {
                // 设置协程上下文
                $this->setCoroutineContext($request, $startTime);

                // 运行中间件
                if (!$this->runMiddlewares($request, $response)) {
                    return;
                }

                // 处理静态文件
                if ($this->handleStaticFile($request, $response)) {
                    return;
                }

                // 保存原始全局变量
                $originalGet = $_GET;
                $originalPost = $_POST;
                $originalFiles = $_FILES;
                $originalCookie = $_COOKIE;
                $originalServer = $_SERVER;

                // 设置全局变量以兼容传统PHP环境
                $_GET = $request->get ?? [];
                $_POST = $request->post ?? [];
                $_FILES = $request->files ?? [];
                $_COOKIE = $request->cookie ?? [];

                // 构建完整的 host 信息
                $serverPort = $request->server['server_port'] ?? '9501';
                $hostHeader = $request->header['host'] ?? null;

                // 如果 host 头存在且已包含端口，直接使用；否则构建完整的 host:port
                if ($hostHeader && strpos($hostHeader, ':') !== false) {
                    $httpHost = $hostHeader;
                    $serverName = explode(':', $hostHeader)[0];
                } elseif ($hostHeader) {
                    $httpHost = $hostHeader . ':' . $serverPort;
                    $serverName = $hostHeader;
                } else {
                    $httpHost = 'localhost:' . $serverPort;
                    $serverName = 'localhost';
                }

                // 更新$_SERVER变量为HTTP请求信息
                $_SERVER = array_merge($_SERVER, [
                    'REQUEST_METHOD' => $request->server['request_method'] ?? 'GET',
                    'REQUEST_URI' => $request->server['request_uri'] ?? '/',
                    'PATH_INFO' => $request->server['path_info'] ?? '/',
                    'QUERY_STRING' => $request->server['query_string'] ?? '',
                    'HTTP_HOST' => $httpHost,
                    'SERVER_NAME' => $serverName,
                    'HTTP_USER_AGENT' => $request->header['user-agent'] ?? 'Swoole/4.0',
                    'HTTP_ACCEPT' => $request->header['accept'] ?? 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'CONTENT_TYPE' => $request->header['content-type'] ?? '',
                    'CONTENT_LENGTH' => $request->header['content-length'] ?? '',
                    'SERVER_PROTOCOL' => 'HTTP/1.1',
                    'REQUEST_TIME' => time(),
                    'REQUEST_TIME_FLOAT' => microtime(true),
                    'SCRIPT_NAME' => '/index.php',
                    'PHP_SELF' => '/index.php',
                    'GATEWAY_INTERFACE' => 'CGI/1.1',
                    'SERVER_SOFTWARE' => 'Swoole/4.0',
                    'REMOTE_ADDR' => $request->server['remote_addr'] ?? '127.0.0.1',
                    'REMOTE_HOST' => 'localhost',
                    'DOCUMENT_ROOT' => getcwd() . '/public',
                    'REQUEST_SCHEME' => 'http',
                    'SERVER_PORT' => $serverPort,
                    'HTTPS' => '',
                    // 设置命令行相关的变量为安全值（避免 think-trace 报错）
                    'argv' => [],
                    'argc' => 0,
                ]);

                // 在每次请求前创建新的应用实例
                $appClass = get_class($this->app);
                $newApp = new $appClass();

                // 初始化新的应用实例
                if (method_exists($newApp, 'initialize')) {
                    $newApp->initialize();
                }

                // 更新应用中的 Request 对象信息
                if ($newApp->has('request')) {
                    $appRequest = $newApp->request;
                    // 更新 Request 对象的 server 属性
                    if (property_exists($appRequest, 'server')) {
                        $reflection = new \ReflectionClass($appRequest);
                        $serverProperty = $reflection->getProperty('server');
                        $serverProperty->setAccessible(true);
                        $serverProperty->setValue($appRequest, $_SERVER);
                    }
                    // 强制重置 host 缓存
                    if (property_exists($appRequest, 'host')) {
                        $reflection = new \ReflectionClass($appRequest);
                        $hostProperty = $reflection->getProperty('host');
                        $hostProperty->setAccessible(true);
                        $hostProperty->setValue($appRequest, null); // 重置，让它重新从 server 中读取
                    }
                }

                // 临时保存原应用实例
                $originalApp = $this->app;
                // 设置新的应用实例
                $this->app = $newApp;

                try {
                    // 处理动态请求
                    $psr7Request = $this->convertSwooleRequestToPsr7($request);
                    $psr7Response = $this->handleRequest($psr7Request);
                    $this->sendSwooleResponse($response, $psr7Response);
                } finally {
                    // 恢复原应用实例
                    $this->app = $originalApp;
                    // 恢复原始全局变量
                    $_GET = $originalGet;
                    $_POST = $originalPost;
                    $_FILES = $originalFiles;
                    $_COOKIE = $originalCookie;
                    $_SERVER = $originalServer;
                    // 明确销毁克隆的应用实例
                    $this->destroyAppInstance($newApp);
                    unset($newApp);
                }

                // 重置调试状态，防止think-trace累积
                $this->resetDebugState();

                // 记录请求指标
                $this->logRequestMetrics($request, $startTime);

                // 增加请求计数器
                $this->requestCounter++;

                // 每处理100个请求进行一次深度调试状态重置
                if ($this->requestCounter % 100 === 0) {
                    $this->deepResetDebugState();
                }

            } catch (Throwable $e) {
                $this->handleSwooleError($response, $e);
            } finally {
                // 清理协程上下文
                $this->clearCoroutineContext();
            }
        });
    }

    /**
     * 服务器启动事件处理
     *
     * @param Server $server Swoole服务器实例
     * @return void
     */
    public function onStart(Server $server): void
    {
        echo "Swoole HTTP Server started successfully\n";
        echo "Master PID: {$server->master_pid}\n";
        echo "Manager PID: {$server->manager_pid}\n";
    }

    /**
     * 服务器关闭事件处理
     *
     * @param Server $server Swoole服务器实例
     * @return void
     */
    public function onShutdown(Server $server): void
    {
        echo "Swoole HTTP Server shutdown\n";
    }

    /**
     * Worker 错误事件处理
     *
     * @param Server $server Swoole服务器实例
     * @param int $workerId Worker ID
     * @param int $workerPid Worker PID
     * @param int $exitCode 退出码
     * @param int $signal 信号
     * @return void
     */
    public function onWorkerError(Server $server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        echo "Worker Error: Worker #{$workerId} (PID: {$workerPid}) exited with code {$exitCode}, signal {$signal}\n";

        // 记录常见信号的含义
        $signalNames = [
            1 => 'SIGHUP',
            2 => 'SIGINT',
            3 => 'SIGQUIT',
            9 => 'SIGKILL',
            14 => 'SIGALRM',
            15 => 'SIGTERM',
        ];

        $signalName = $signalNames[$signal] ?? "UNKNOWN({$signal})";
        echo "Signal: {$signalName}\n";

        if ($signal === 14) {
            echo "SIGALRM detected - this may be caused by alarm() calls or timer conflicts\n";
        }
    }

    /**
     * Worker 退出事件处理
     *
     * @param Server $server Swoole服务器实例
     * @param int $workerId Worker ID
     * @return void
     */
    public function onWorkerExit(Server $server, int $workerId): void
    {
        echo "Worker #{$workerId} exited normally\n";
    }

    /**
     * Task事件处理
     *
     * @param Server $server Swoole服务器实例
     * @param int $taskId Task ID
     * @param int $reactorId Reactor ID
     * @param mixed $data Task数据
     * @return string
     */
    public function onTask(Server $server, int $taskId, int $reactorId, mixed $data): string
    {
        // 默认的Task处理逻辑
        return "Task {$taskId} completed";
    }

    /**
     * Finish事件处理
     *
     * @param Server $server Swoole服务器实例
     * @param int $taskId Task ID
     * @param mixed $data Task返回数据
     * @return void
     */
    public function onFinish(Server $server, int $taskId, mixed $data): void
    {
        // 默认的Finish处理逻辑
        echo "Task {$taskId} finished with result: {$data}\n";
    }

    /**
     * 将Swoole请求转换为PSR-7请求（优化版）
     *
     * @param SwooleRequest $request Swoole请求
     * @return ServerRequestInterface PSR-7请求
     */
    protected function convertSwooleRequestToPsr7(SwooleRequest $request): ServerRequestInterface
    {
        // 使用复用的工厂实例
        if (!$this->requestCreator) {
            $this->requestCreator = new ServerRequestCreator(
                $this->psr17Factory,
                $this->psr17Factory,
                $this->psr17Factory,
                $this->psr17Factory
            );
        }

        // 构建完整的 host 信息（与 onRequest 中的逻辑保持一致）
        $serverPort = $request->server['server_port'] ?? '9501';
        $hostHeader = $request->header['host'] ?? null;

        // 如果 host 头存在且已包含端口，直接使用；否则构建完整的 host:port
        if ($hostHeader && strpos($hostHeader, ':') !== false) {
            $httpHost = $hostHeader;
            $serverName = explode(':', $hostHeader)[0];
        } elseif ($hostHeader) {
            $httpHost = $hostHeader . ':' . $serverPort;
            $serverName = $hostHeader;
        } else {
            $httpHost = 'localhost:' . $serverPort;
            $serverName = 'localhost';
        }

        // 构建完整的服务器变量（与 onRequest 中的设置保持一致）
        $server = array_merge($_SERVER, [
            'REQUEST_METHOD' => $request->server['request_method'] ?? 'GET',
            'REQUEST_URI' => $request->server['request_uri'] ?? '/',
            'PATH_INFO' => $request->server['path_info'] ?? '/',
            'QUERY_STRING' => $request->server['query_string'] ?? '',
            'HTTP_HOST' => $httpHost,
            'SERVER_NAME' => $serverName,
            'HTTP_USER_AGENT' => $request->header['user-agent'] ?? 'Swoole/4.0',
            'HTTP_ACCEPT' => $request->header['accept'] ?? 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'CONTENT_TYPE' => $request->header['content-type'] ?? '',
            'CONTENT_LENGTH' => $request->header['content-length'] ?? '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php',
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'SERVER_SOFTWARE' => 'Swoole/4.0',
            'REMOTE_ADDR' => $request->server['remote_addr'] ?? '127.0.0.1',
            'REMOTE_HOST' => 'localhost',
            'DOCUMENT_ROOT' => getcwd() . '/public',
            'REQUEST_SCHEME' => 'http',
            'SERVER_PORT' => $serverPort,
            'HTTPS' => '',
            // 设置命令行相关的变量为安全值（避免 think-trace 报错）
            'argv' => [],
            'argc' => 0,
        ]);

        return $this->requestCreator->fromArrays(
            $server,
            $request->header ?? [],
            $request->cookie ?? [],
            $request->get ?? [],
            $request->post ?? [],
            $request->files ?? [],
            $request->rawContent() ?: null
        );
    }

    /**
     * 发送Swoole响应
     *
     * @param SwooleResponse $swooleResponse Swoole响应对象
     * @param ResponseInterface $psr7Response PSR-7响应对象
     * @return void
     */
    protected function sendSwooleResponse(SwooleResponse $swooleResponse, ResponseInterface $psr7Response): void
    {
        // 设置状态码
        $swooleResponse->status($psr7Response->getStatusCode());

        // 设置响应头
        foreach ($psr7Response->getHeaders() as $name => $values) {
            $swooleResponse->header((string) $name, implode(', ', $values));
        }

        // 发送响应体
        $swooleResponse->end((string) $psr7Response->getBody());
    }

    /**
     * 处理Swoole错误
     *
     * @param SwooleResponse $response Swoole响应对象
     * @param Throwable $e 异常
     * @return void
     */
    protected function handleSwooleError(SwooleResponse $response, Throwable $e): void
    {
        $response->status(500);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 设置协程上下文（轻量化存储）
     *
     * @param SwooleRequest $request
     * @param float $startTime
     * @return void
     */
    protected function setCoroutineContext(SwooleRequest $request, float $startTime): void
    {
        if (class_exists('\Swoole\Coroutine')) {
            $cid = Coroutine::getCid();
            // 只存储必要的信息，避免存储完整对象
            $this->coroutineContext[$cid] = [
                'request_id' => uniqid(),
                'start_time' => $startTime,
                'method' => $request->getMethod(),
                'uri' => $request->server['request_uri'] ?? '/',
                'created_at' => time(),
            ];

            // 检查上下文数量，防止无限增长
            $this->checkCoroutineContextSize();
        }
    }

    /**
     * 清理协程上下文
     *
     * @return void
     */
    protected function clearCoroutineContext(): void
    {
        if (class_exists('\Swoole\Coroutine')) {
            $cid = Coroutine::getCid();
            unset($this->coroutineContext[$cid]);
        }
    }

    /**
     * 运行中间件
     *
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @return bool
     */
    protected function runMiddlewares(SwooleRequest $request, SwooleResponse $response): bool
    {
        foreach ($this->middlewares as $middleware) {
            $result = $middleware($request, $response);
            if ($result === false) {
                return false; // 中断请求处理
            }
        }
        return true;
    }

    /**
     * CORS 中间件
     *
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @return bool
     */
    protected function corsMiddleware(SwooleRequest $request, SwooleResponse $response): bool
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $corsConfig = $config['middleware']['cors'];

        $response->header('Access-Control-Allow-Origin', $corsConfig['allow_origin'] ?? '*');
        $response->header('Access-Control-Allow-Methods', $corsConfig['allow_methods'] ?? 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', $corsConfig['allow_headers'] ?? 'Content-Type, Authorization, X-Requested-With');
        $response->header('Access-Control-Allow-Credentials', 'true');

        // 处理 OPTIONS 预检请求
        if ($request->server['request_method'] === 'OPTIONS') {
            $response->status(200);
            $response->end();
            return false;
        }

        return true;
    }

    /**
     * 安全中间件
     *
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @return bool
     */
    protected function securityMiddleware(SwooleRequest $request, SwooleResponse $response): bool
    {
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        return true;
    }

    /**
     * 处理静态文件
     *
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @return bool
     */
    protected function handleStaticFile(SwooleRequest $request, SwooleResponse $response): bool
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if (!($config['static_file']['enable'] ?? true)) {
            return false;
        }

        $uri = $request->server['request_uri'];
        $publicPath = $this->getPublicPath();
        $filePath = $publicPath . $uri;

        // 检查文件是否存在且在允许的目录内
        if (!$this->isValidStaticFile($filePath, $publicPath)) {
            return false;
        }

        // 检查文件扩展名
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $config['static_file']['allowed_extensions'])) {
            return false;
        }

        // 设置 MIME 类型
        $mimeType = $this->getMimeType($extension);
        $response->header('Content-Type', $mimeType);

        // 设置缓存头
        $cacheTime = $config['static_file']['cache_time'];
        $response->header('Cache-Control', "public, max-age={$cacheTime}");
        $response->header('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');

        // 发送文件
        $response->sendfile($filePath);
        return true;
    }

    /**
     * 检查静态文件是否有效
     *
     * @param string $filePath
     * @param string $publicPath
     * @return bool
     */
    protected function isValidStaticFile(string $filePath, string $publicPath): bool
    {
        $realFilePath = realpath($filePath);
        $realPublicPath = realpath($publicPath);

        return $realFilePath &&
               $realPublicPath &&
               strpos($realFilePath, $realPublicPath) === 0 &&
               is_file($realFilePath);
    }

    /**
     * 获取 MIME 类型
     *
     * @param string $extension
     * @return string
     */
    protected function getMimeType(string $extension): string
    {
        return $this->mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * 获取公共目录路径
     *
     * @return string
     */
    protected function getPublicPath(): string
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $documentRoot = $config['static_file']['document_root'];

        if (str_starts_with($documentRoot, '/')) {
            return $documentRoot;
        }

        return getcwd() . '/' . ltrim($documentRoot, '/');
    }

    /**
     * 检查是否启用 WebSocket
     *
     * @return bool
     */
    protected function isWebSocketEnabled(): bool
    {
        $config = array_merge($this->defaultConfig, $this->config);
        return $config['websocket']['enable'] ?? false;
    }

    /**
     * 重置调试状态
     * 防止think-trace等调试工具在常驻内存环境中累积数据导致内存和耗时异常上涨
     *
     * @return void
     */
    protected function resetDebugState(): void
    {
        // 重置think-trace相关的静态变量
        $debugClasses = [
            '\think\Debug',
            '\think\debug\Html',
            '\think\debug\Console',
            '\think\Trace',
            '\think\trace\Html',
            '\think\trace\Console',
            '\think\Log',
            '\think\log\Channel',
            // topthink/think-trace 相关类
            '\think\trace\TraceDebug',
            '\think\trace\Service',
        ];

        foreach ($debugClasses as $className) {
            if (class_exists($className)) {
                try {
                    $reflection = new \ReflectionClass($className);
                    $properties = $reflection->getStaticProperties();

                    foreach ($properties as $name => $value) {
                        // 重置可能累积的调试数据
                        if (str_contains($name, 'time') ||
                            str_contains($name, 'start') ||
                            str_contains($name, 'end') ||
                            str_contains($name, 'log') ||
                            str_contains($name, 'trace') ||
                            str_contains($name, 'debug') ||
                            str_contains($name, 'sql') ||
                            str_contains($name, 'info') ||
                            str_contains($name, 'data')) {

                            $property = $reflection->getProperty($name);
                            if ($property->isStatic() && $property->isPublic()) {
                                if (is_array($value)) {
                                    $property->setValue([]);
                                } elseif (is_numeric($value)) {
                                    $property->setValue(0);
                                } elseif (is_string($value)) {
                                    $property->setValue('');
                                } elseif (is_bool($value)) {
                                    $property->setValue(false);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // 忽略反射错误，确保不影响正常请求处理
                }
            }
        }

        // 重置 ThinkPHP 调试相关的 Facade
        if (class_exists('\think\facade\Debug')) {
            try {
                \think\facade\Debug::clear();
            } catch (\Throwable $e) {
                // 忽略错误
            }
        }

        if (class_exists('\think\facade\Log')) {
            try {
                \think\facade\Log::clear();
            } catch (\Throwable $e) {
                // 忽略错误
            }
        }

        // 重置请求时间，防止累积
        $_SERVER['REQUEST_TIME'] = time();
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);

        // 强制垃圾回收，清理可能的内存泄漏
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * 深度重置调试状态
     * 定期执行的深度清理，包括更彻底的内存清理和状态重置
     *
     * @return void
     */
    protected function deepResetDebugState(): void
    {
        // 执行常规重置
        $this->resetDebugState();

        // 重置应用实例的内部状态
        if ($this->app && method_exists($this->app, 'request')) {
            $this->app->request = null;
        }

        if ($this->app && property_exists($this->app, 'response')) {
            $this->app->response = null;
        }

        // 清理可能的静态缓存
        if (function_exists('opcache_reset')) {
            // 注意：在生产环境中谨慎使用 opcache_reset
            // opcache_reset();
        }

        // 强制多次垃圾回收
        for ($i = 0; $i < 3; $i++) {
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // 重置内存峰值统计
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        echo "Deep debug state reset completed (request #{$this->requestCounter})\n";
    }

    /**
     * 记录请求指标
     *
     * @param SwooleRequest $request
     * @param float $startTime
     * @return void
     */
    protected function logRequestMetrics(SwooleRequest $request, float $startTime): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if (!($config['monitor']['enable'] ?? true)) {
            return;
        }

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // 转换为毫秒

        $metrics = [
            'method' => $request->server['request_method'],
            'uri' => $request->server['request_uri'],
            'duration' => round($duration, 2),
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // 记录慢请求
        if ($duration > ($config['monitor']['slow_request_threshold'] ?? 1000)) {
            error_log("Slow request: " . json_encode($metrics));
        }
    }

    /**
     * WebSocket 连接打开事件
     *
     * @param Server $server
     * @param SwooleRequest $request
     * @return void
     */
    public function onWebSocketOpen(Server $server, SwooleRequest $request): void
    {
        echo "WebSocket connection opened: {$request->fd}\n";
    }

    /**
     * WebSocket 消息事件
     *
     * @param Server $server
     * @param $frame
     * @return void
     */
    public function onWebSocketMessage(Server $server, $frame): void
    {
        echo "WebSocket message received from {$frame->fd}: {$frame->data}\n";

        // 处理 WebSocket 消息
        $response = $this->handleWebSocketMessage($frame);

        if ($response) {
            $server->push($frame->fd, $response);
        }
    }

    /**
     * WebSocket 连接关闭事件
     *
     * @param Server $server
     * @param int $fd
     * @return void
     */
    public function onWebSocketClose(Server $server, int $fd): void
    {
        echo "WebSocket connection closed: {$fd}\n";
    }

    /**
     * 处理 WebSocket 消息
     *
     * @param $frame
     * @return string|null
     */
    protected function handleWebSocketMessage($frame): ?string
    {
        // 默认实现：回显消息
        return "Echo: " . $frame->data;
    }

    /**
     * 检查协程上下文大小
     *
     * @return void
     */
    protected function checkCoroutineContextSize(): void
    {
        $maxSize = 1000; // 最大上下文数量

        if (count($this->coroutineContext) > $maxSize) {
            // 强制清理最旧的上下文
            $this->forceCleanupOldestCoroutineContexts((int)($maxSize * 0.8));
        }
    }

    /**
     * 强制清理最旧的协程上下文
     *
     * @param int $targetSize
     * @return void
     */
    protected function forceCleanupOldestCoroutineContexts(int $targetSize): void
    {
        // 按创建时间排序
        uasort($this->coroutineContext, function($a, $b) {
            return ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0);
        });

        $currentSize = count($this->coroutineContext);
        $toRemove = $currentSize - $targetSize;

        if ($toRemove > 0) {
            $removed = 0;
            foreach ($this->coroutineContext as $cid => $context) {
                if ($removed >= $toRemove) {
                    break;
                }
                unset($this->coroutineContext[$cid]);
                $removed++;
            }

            echo "Swoole force cleaned {$removed} oldest coroutine contexts due to size limit\n";
            gc_collect_cycles();
        }
    }

    /**
     * 销毁应用实例
     *
     * @param mixed $app
     * @return void
     */
    protected function destroyAppInstance($app): void
    {
        if (!$app) {
            return;
        }

        try {
            // 如果应用有销毁方法，调用它
            if (method_exists($app, 'destroy')) {
                $app->destroy();
            } elseif (method_exists($app, 'terminate')) {
                $app->terminate();
            } elseif (method_exists($app, 'shutdown')) {
                $app->shutdown();
            }
        } catch (Throwable $e) {
            // 忽略销毁过程中的错误
            error_log("Swoole: Error destroying app instance: " . $e->getMessage());
        }
    }

    /**
     * 获取内存使用统计
     *
     * @return array
     */
    public function getMemoryStats(): array
    {
        return [
            'current_memory' => memory_get_usage(true),
            'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'coroutine_context_count' => count($this->coroutineContext),
            'request_count' => $this->requestCounter,
        ];
    }
}
