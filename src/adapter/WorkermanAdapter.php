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
 * Workerman 适配器
 * 基于 Workerman 的高性能 HTTP 服务器适配器
 */
class WorkermanAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * Workerman Worker 实例
     *
     * @var Worker|null
     */
    protected ?Worker $worker = null;

    /**
     * 请求创建器（复用）
     *
     * @var ServerRequestCreator|null
     */
    protected ?ServerRequestCreator $requestCreator = null;

    /**
     * 连接上下文存储
     *
     * @var array
     */
    protected array $connectionContext = [];

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
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
    ];

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'count' => 4,                    // 进程数
        'name' => 'ThinkPHP-Workerman',  // 进程名称
        'user' => '',                    // 运行用户
        'group' => '',                   // 运行用户组
        'reloadable' => true,            // 是否可重载
        'reusePort' => false,            // 端口复用
        'transport' => 'tcp',            // 传输协议
        'context' => [],                 // Socket上下文选项
        'protocol' => 'http',            // 应用层协议
        // 静态文件配置
        'static_file' => [
            'enable' => true,
            'document_root' => 'public',
            'cache_time' => 3600,
            'allowed_extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'html', 'htm', 'txt', 'json', 'xml'],
        ],
        // 性能监控配置
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 1000, // 毫秒
            'memory_limit' => '256M',
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
        // 日志配置
        'log' => [
            'enable' => true,
            'file' => 'runtime/logs/workerman.log',
            'level' => 'info',
        ],
        // 定时器配置
        'timer' => [
            'enable' => false,
            'interval' => 60, // 秒
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
            throw new RuntimeException('Workerman is not installed');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 初始化请求创建器（复用实例）
        $this->requestCreator = new ServerRequestCreator(
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->psr17Factory
        );

        // 创建 Worker 实例
        // 根据协议类型构建正确的监听地址
        if ($config['protocol'] === 'http') {
            $listen = 'http://' . $config['host'] . ':' . $config['port'];
        } else {
            $listen = $config['transport'] . '://' . $config['host'] . ':' . $config['port'];
        }
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

        // 初始化中间件
        $this->initMiddlewares();

        // 设置日志
        $this->setupLogging($config);
    }

    /**
     * 启动服务器
     *
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void
    {
        if (!$this->worker) {
            $this->boot();
        }

        // 合并启动选项
        if (!empty($options)) {
            $this->mergeStartOptions($options);
        }

        echo "Starting Workerman HTTP Server...\n";
        echo "Listening on: {$this->worker->getSocketName()}\n";
        echo "Worker processes: {$this->worker->count}\n";
        echo "Press Ctrl+C to stop the server\n\n";

        // 设置Workerman作为库使用，避免命令行参数冲突
    Worker::$command = 'start';

    // 启动 Workerman
        Worker::runAll();
    }

    /**
     * 合并启动选项
     *
     * @param array $options
     * @return void
     */
    protected function mergeStartOptions(array $options): void
    {
        // 动态调整 Worker 配置
        if (isset($options['count'])) {
            $this->worker->count = (int) $options['count'];
        }

        if (isset($options['name'])) {
            $this->worker->name = (string) $options['name'];
        }

        if (isset($options['user'])) {
            $this->worker->user = (string) $options['user'];
        }

        if (isset($options['group'])) {
            $this->worker->group = (string) $options['group'];
        }

        if (isset($options['reloadable'])) {
            $this->worker->reloadable = (bool) $options['reloadable'];
        }

        if (isset($options['reusePort'])) {
            $this->worker->reusePort = (bool) $options['reusePort'];
        }
    }

    /**
     * 绑定 Workerman 事件
     *
     * @return void
     */
    protected function bindEvents(): void
    {
        $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        $this->worker->onMessage = [$this, 'onMessage'];
        $this->worker->onConnect = [$this, 'onConnect'];
        $this->worker->onClose = [$this, 'onClose'];
        $this->worker->onError = [$this, 'onError'];
        $this->worker->onWorkerStop = [$this, 'onWorkerStop'];
        $this->worker->onWorkerReload = [$this, 'onWorkerReload'];
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
     * Worker 启动事件
     *
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker): void
    {
        try {
            // 设置进程标题
            if (function_exists('cli_set_process_title')) {
                cli_set_process_title("workerman-{$worker->name}-{$worker->id}");
            }

            // 初始化应用
            if ($this->app && method_exists($this->app, 'initialize')) {
                $this->app->initialize();
            }

            // 设置定时器
            $this->setupTimer();

            echo "Worker #{$worker->id} started\n";

        } catch (Throwable $e) {
            echo "Worker #{$worker->id} start failed: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 消息处理事件（HTTP 请求）
     *
     * @param TcpConnection $connection
     * @param WorkermanRequest $request
     * @return void
     */
    public function onMessage(TcpConnection $connection, WorkermanRequest $request): void
    {
        $startTime = microtime(true);
        
        try {
            // 设置连接上下文
            $this->setConnectionContext($connection, $request, $startTime);

            // 运行中间件
            $response = $this->runMiddlewares($request);
            if ($response) {
                $connection->send($response);
                return;
            }

            // 处理静态文件
            if ($this->handleStaticFile($connection, $request)) {
                return;
            }

            // 处理动态请求
            $psr7Request = $this->convertWorkermanRequestToPsr7($request);

            // 保存原始$_SERVER变量
            $originalServer = $_SERVER;

            // 更新$_SERVER变量为HTTP请求信息
        // 调试信息
        error_log("DEBUG: request->uri(): " . $request->uri());
        error_log("DEBUG: request->path(): " . $request->path());
        error_log("DEBUG: parse_url result: " . parse_url($request->uri(), PHP_URL_PATH));
    // 移除命令行相关的变量
    unset($_SERVER['argv']);
    unset($_SERVER['argc']);
            $_SERVER = array_merge($_SERVER, [
                'REQUEST_METHOD' => $request->method(),
                'REQUEST_URI' => parse_url($request->uri(), PHP_URL_PATH) . ($request->queryString() ? '?' . $request->queryString() : ''),
                'PATH_INFO' => $request->path(),
                'QUERY_STRING' => $request->queryString(),
                'HTTP_HOST' => 'localhost:8080',
                'SERVER_NAME' => $request->host() ?: 'localhost',
                'HTTP_USER_AGENT' => $request->header('user-agent', 'Workerman/5.0'),
                'HTTP_ACCEPT' => $request->header('accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
                'CONTENT_TYPE' => $request->header('content-type', ''),
                'CONTENT_LENGTH' => $request->header('content-length', ''),
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'REQUEST_TIME' => time(),
                'REQUEST_TIME_FLOAT' => microtime(true),
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php',                'argv' => [],                'GATEWAY_INTERFACE' => 'CGI/1.1',                'SERVER_SOFTWARE' => 'Workerman/5.0',                'REMOTE_ADDR' => '127.0.0.1',                'REMOTE_HOST' => 'localhost',                'DOCUMENT_ROOT' => getcwd() . '/public',                'REQUEST_SCHEME' => 'http',                'SERVER_PORT' => '8080',                'HTTPS' => '',
            ]);

            // 在每次请求前创建新的应用实例
            $appClass = get_class($this->app);
            $newApp = new $appClass();

            // 初始化新的应用实例
            if (method_exists($newApp, 'initialize')) {
        // 确保Request对象使用正确的server信息
        if ($newApp->has('request')) {
            $newRequest = $newApp->request;
            $newRequest->server = $_SERVER;
        }
                $newApp->initialize();
            }

            // 临时保存原应用实例
            $originalApp = $this->app;
            // 设置新的应用实例
            $this->app = $newApp;

            try {
                // 处理请求
                $psr7Response = $this->handleRequest($psr7Request);
                $workermanResponse = $this->convertPsr7ToWorkermanResponse($psr7Response);

                $connection->send($workermanResponse);
            } finally {
                // 恢复原应用实例
                $this->app = $originalApp;
                // 恢复原始$_SERVER变量
                $_SERVER = $originalServer;
            }

            // 记录请求指标
            $this->logRequestMetrics($request, $startTime);

        } catch (Throwable $e) {
            $this->handleWorkermanError($connection, $e);
        } finally {
            // 清理连接上下文
            $this->clearConnectionContext($connection);
        }
    }

    /**
     * 连接建立事件
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect(TcpConnection $connection): void
    {
        // 连接建立时的处理
    }

    /**
     * 连接关闭事件
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose(TcpConnection $connection): void
    {
        // 清理连接上下文
        $this->clearConnectionContext($connection);
    }

    /**
     * 错误事件
     *
     * @param TcpConnection $connection
     * @param int $code
     * @param string $msg
     * @return void
     */
    public function onError(TcpConnection $connection, int $code, string $msg): void
    {
        echo "Connection error: {$code} - {$msg}\n";
    }

    /**
     * Worker 停止事件
     *
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStop(Worker $worker): void
    {
        echo "Worker #{$worker->id} stopped\n";
    }

    /**
     * Worker 重载事件
     *
     * @param Worker $worker
     * @return void
     */
    public function onWorkerReload(Worker $worker): void
    {
        echo "Worker #{$worker->id} reloaded\n";
    }

    /**
     * 设置连接上下文
     *
     * @param TcpConnection $connection
     * @param WorkermanRequest $request
     * @param float $startTime
     * @return void
     */
    protected function setConnectionContext(TcpConnection $connection, WorkermanRequest $request, float $startTime): void
    {
        $connectionId = spl_object_id($connection);
        $this->connectionContext[$connectionId] = [
            'request_id' => uniqid(),
            'start_time' => $startTime,
            'request' => $request,
            'connection' => $connection,
        ];
    }

    /**
     * 清理连接上下文
     *
     * @param TcpConnection $connection
     * @return void
     */
    protected function clearConnectionContext(TcpConnection $connection): void
    {
        $connectionId = spl_object_id($connection);
        unset($this->connectionContext[$connectionId]);
    }

    /**
     * 运行中间件
     *
     * @param WorkermanRequest $request
     * @return WorkermanResponse|null
     */
    protected function runMiddlewares(WorkermanRequest $request): ?WorkermanResponse
    {
        foreach ($this->middlewares as $middleware) {
            $result = $middleware($request);
            if ($result instanceof WorkermanResponse) {
                return $result; // 中断请求处理
            }
        }
        return null;
    }

    /**
     * CORS 中间件
     *
     * @param WorkermanRequest $request
     * @return WorkermanResponse|null
     */
    protected function corsMiddleware(WorkermanRequest $request): ?WorkermanResponse
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $corsConfig = $config['middleware']['cors'];

        // 处理 OPTIONS 预检请求
        if ($request->method() === 'OPTIONS') {
            $response = new WorkermanResponse(200);
            $response->header('Access-Control-Allow-Origin', $corsConfig['allow_origin'] ?? '*');
            $response->header('Access-Control-Allow-Methods', $corsConfig['allow_methods'] ?? 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', $corsConfig['allow_headers'] ?? 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Allow-Credentials', 'true');
            return $response;
        }

        return null;
    }

    /**
     * 安全中间件
     *
     * @param WorkermanRequest $request
     * @return WorkermanResponse|null
     */
    protected function securityMiddleware(WorkermanRequest $request): ?WorkermanResponse
    {
        // 安全中间件不需要返回响应，只是设置安全头
        return null;
    }

    /**
     * 处理静态文件
     *
     * @param TcpConnection $connection
     * @param WorkermanRequest $request
     * @return bool
     */
    protected function handleStaticFile(TcpConnection $connection, WorkermanRequest $request): bool
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if (!($config['static_file']['enable'] ?? true)) {
            return false;
        }

        $uri = $request->uri();
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

        // 创建响应
        $response = new WorkermanResponse(200);

        // 设置 MIME 类型
        $mimeType = $this->getMimeType($extension);
        $response->header('Content-Type', $mimeType);

        // 设置缓存头
        $cacheTime = $config['static_file']['cache_time'];
        $response->header('Cache-Control', "public, max-age={$cacheTime}");
        $response->header('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');

        // 发送文件内容
        $response->withFile($filePath);
        $connection->send($response);

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
               $realPublicPath && str_starts_with($realFilePath, $realPublicPath) &&
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
     * 将 Workerman 请求转换为 PSR-7 请求
     *
     * @param WorkermanRequest $request
     * @return ServerRequestInterface
     */
    protected function convertWorkermanRequestToPsr7(WorkermanRequest $request): ServerRequestInterface
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

        // 构建服务器变量
        $server = array_merge($_SERVER, [
            'REQUEST_METHOD' => $request->method(),
            'REQUEST_URI' => parse_url($request->uri(), PHP_URL_PATH) . ($request->queryString() ? '?' . $request->queryString() : ''),
            'PATH_INFO' => $request->path(),
            'QUERY_STRING' => $request->queryString(),
            'HTTP_HOST' => 'localhost:8080',
                'SERVER_NAME' => $request->host() ?: 'localhost',
                'HTTP_USER_AGENT' => $request->header('user-agent', 'Workerman/5.0'),
                'HTTP_ACCEPT' => $request->header('accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
            'CONTENT_TYPE' => $request->header('content-type', ''),
            'CONTENT_LENGTH' => $request->header('content-length', ''),
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ]);

        return $this->requestCreator->fromArrays(
            $server,
            $request->header() ?? [],
            $request->cookie() ?? [],
            $request->get() ?? [],
            $request->post() ?? [],
            $request->file() ?? [],
            $request->rawBody() ?: null
        );
    }

    /**
     * 将 PSR-7 响应转换为 Workerman 响应
     *
     * @param ResponseInterface $psr7Response
     * @return WorkermanResponse
     */
    protected function convertPsr7ToWorkermanResponse(ResponseInterface $psr7Response): WorkermanResponse
    {
        $response = new WorkermanResponse($psr7Response->getStatusCode());

        // 设置响应头
        foreach ($psr7Response->getHeaders() as $name => $values) {
            $response->header($name, implode(', ', $values));
        }

        // 添加 CORS 和安全头
        $this->addSecurityHeaders($response);

        // 设置响应体
        $response->withBody((string) $psr7Response->getBody());

        return $response;
    }

    /**
     * 添加安全响应头
     *
     * @param WorkermanResponse $response
     * @return void
     */
    protected function addSecurityHeaders(WorkermanResponse $response): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // CORS 头
        if ($config['middleware']['cors']['enable'] ?? true) {
            $corsConfig = $config['middleware']['cors'];
            $response->header('Access-Control-Allow-Origin', $corsConfig['allow_origin'] ?? '*');
            $response->header('Access-Control-Allow-Methods', $corsConfig['allow_methods'] ?? 'GET, POST, PUT, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', $corsConfig['allow_headers'] ?? 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        // 安全头
        if ($config['middleware']['security']['enable'] ?? true) {
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-Frame-Options', 'DENY');
            $response->header('X-XSS-Protection', '1; mode=block');
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    /**
     * 处理 Workerman 连接错误
     *
     * @param TcpConnection $connection
     * @param Throwable $e
     * @return void
     */
    protected function handleWorkermanError(TcpConnection $connection, Throwable $e): void
    {
        $response = new WorkermanResponse(500);
        $response->header('Content-Type', 'application/json');

        $errorData = [
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ];

        $response->withBody(json_encode($errorData, JSON_UNESCAPED_UNICODE));
        $connection->send($response);

        // 记录错误日志
        error_log("Workerman Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }

    /**
     * 记录请求指标
     *
     * @param WorkermanRequest $request
     * @param float $startTime
     * @return void
     */
    protected function logRequestMetrics(WorkermanRequest $request, float $startTime): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if (!($config['monitor']['enable'] ?? true)) {
            return;
        }

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // 转换为毫秒

        $metrics = [
            'method' => $request->method(),
            'uri' => $request->uri(),
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
     * 设置日志
     *
     * @param array $config
     * @return void
     */
    protected function setupLogging(array $config): void
    {
        if ($config['log']['enable'] ?? true) {
            $logFile = $config['log']['file'] ?? 'runtime/logs/workerman.log';
            $logDir = dirname($logFile);

            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            Worker::$logFile = $logFile;
        }
    }

    /**
     * 设置定时器
     *
     * @return void
     */
    protected function setupTimer(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if ($config['timer']['enable'] ?? false) {
            $interval = $config['timer']['interval'] ?? 60;

            Timer::add($interval, function() {
                // 定时任务：清理过期连接上下文、垃圾回收等
                $this->cleanupExpiredContext();

                // 内存使用检查
                $this->checkMemoryUsage();
            });
        }
    }

    /**
     * 清理过期上下文
     *
     * @return void
     */
    protected function cleanupExpiredContext(): void
    {
        $now = time();
        foreach ($this->connectionContext as $id => $context) {
            // 清理超过5分钟的上下文
            if ($now - $context['start_time'] > 300) {
                unset($this->connectionContext[$id]);
            }
        }
    }

    /**
     * 检查内存使用
     *
     * @return void
     */
    protected function checkMemoryUsage(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $memoryLimit = $config['monitor']['memory_limit'] ?? '256M';

        $currentMemory = memory_get_usage(true);
        $limitBytes = $this->parseMemoryLimit($memoryLimit);

        if ($currentMemory > $limitBytes * 0.9) { // 90% 阈值
            echo "Warning: Memory usage is high: " . round($currentMemory / 1024 / 1024, 2) . "MB\n";
        }
    }

    /**
     * 解析内存限制
     *
     * @param string $limit
     * @return int
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $unit = strtoupper(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (int) $limit;
        }
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
     * 检查适配器是否可用
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
        return class_exists(Worker::class);
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 85; // 介于 ReactPHP(92) 和 RoadRunner(90) 之间
    }

    /**
     * 获取运行时配置（重写父类方法）
     *
     * @return array
     */
    public function getConfig(): array
    {
        return array_merge($this->defaultConfig, $this->config);
    }

    /**
     * 停止运行时
     *
     * @param array $options 停止选项
     * @return void
     */
    public function stop(array $options = []): void
    {
        if ($this->worker) {
            Worker::stopAll();
        }
    }

    /**
     * 运行适配器
     *
     * @param array $options 运行选项
     * @return void
     */
    public function run(array $options = []): void
    {
        $this->boot();
        $this->start($options);
    }

    /**
     * 终止适配器
     *
     * @return void
     */
    public function terminate(): void
    {
        $this->stop();
    }
}
