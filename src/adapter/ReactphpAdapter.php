<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;
use function React\Promise\resolve;

/**
 * ReactPHP适配器
 * 提供ReactPHP事件驱动异步HTTP服务器支持
 */
class ReactphpAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * ReactPHP事件循环
     *
     * @var LoopInterface|null
     */
    protected ?LoopInterface $loop = null;

    /**
     * ReactPHP HTTP服务器
     *
     * @var HttpServer|null
     */
    protected ?HttpServer $httpServer = null;

    /**
     * ReactPHP Socket服务器
     *
     * @var SocketServer|null
     */
    protected ?SocketServer $socketServer = null;

    /**
     * 临时上传文件列表
     *
     * @var array
     */
    protected array $tempUploadFiles = [];

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'max_connections' => 1000,
        'timeout' => 30,
        'enable_keepalive' => true,
        'keepalive_timeout' => 5,
        'max_request_size' => '8M',
        'enable_compression' => true,
        'debug' => false,
        'access_log' => true,
        'error_log' => true,
        'websocket' => false,
        'ssl' => [
            'enabled' => false,
            'cert' => '',
            'key' => '',
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
            throw new RuntimeException('ReactPHP is not available');
        }

        // 设置无限执行时间，ReactPHP服务器需要持续运行
        set_time_limit(0);

        // 初始化应用
        $this->app->initialize();

        // 创建事件循环
        $this->loop = Loop::get();

        // 创建HTTP服务器
        $this->createHttpServer();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->loop === null) {
            $this->boot();
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 设置无限执行时间，因为ReactPHP服务器需要持续运行
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        echo "ReactPHP HTTP Server starting...\n";
        echo "Listening on: {$config['host']}:{$config['port']}\n";
        echo "Event-driven: Yes\n";
        echo "Max connections: {$config['max_connections']}\n";
        echo "WebSocket support: " . ($config['websocket'] ? 'Yes' : 'No') . "\n";
        echo "Memory limit: " . ini_get('memory_limit') . "\n";
        echo "Execution time: Unlimited\n";
        echo "Press Ctrl+C to stop the server\n\n";

        // 启动事件循环
        $this->loop->run();
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
        $this->run();
    }

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->socketServer !== null) {
            $this->socketServer->close();
        }

        if ($this->loop !== null) {
            $this->loop->stop();
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
        return 'reactphp';
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
        return class_exists('React\\EventLoop\\Loop') &&
               class_exists('React\\Http\\HttpServer') &&
               class_exists('React\\Socket\\SocketServer') &&
               class_exists('React\\Http\\Message\\Response') &&
               class_exists('React\\Promise\\Promise');
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 92; // 高优先级，在FrankenPHP和RoadRunner之间
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
     * 创建HTTP服务器
     *
     * @return void
     */
    protected function createHttpServer(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 创建HTTP服务器
        $this->httpServer = new HttpServer(
            $this->loop,
            [$this, 'handleReactRequest']
        );

        // 创建Socket服务器
        $listen = $config['host'] . ':' . $config['port'];
        if ($config['ssl']['enabled']) {
            $context = [
                'tls' => [
                    'local_cert' => $config['ssl']['cert'],
                    'local_pk' => $config['ssl']['key'],
                ]
            ];
            $this->socketServer = new SocketServer('tls://' . $listen, $context, $this->loop);
        } else {
            $this->socketServer = new SocketServer($listen, [], $this->loop);
        }

        // 绑定HTTP服务器到Socket
        $this->httpServer->listen($this->socketServer);

        // 设置连接限制
        if ($config['max_connections'] > 0) {
            $this->socketServer->on('connection', function ($connection) use ($config) {
                // ReactPHP Connection 不支持 setTimeout 方法
                // 超时控制应该在 Connector 层面或通过事件循环定时器实现
                // 这里可以添加其他连接相关的配置
            });
        }
    }

    /**
     * 处理HTTP请求（ReactPHP回调）
     *
     * @param ServerRequestInterface $request PSR-7请求
     * @return PromiseInterface
     */
    public function handleReactRequest(ServerRequestInterface $request): PromiseInterface
    {
        try {
            // 保存原始全局变量
            $originalGet = $_GET;
            $originalPost = $_POST;
            $originalFiles = $_FILES;
            $originalCookie = $_COOKIE;
            $originalServer = $_SERVER;

            // 从 PSR-7 请求中提取数据并设置全局变量
            $_GET = $request->getQueryParams();
            $_POST = $request->getParsedBody() ?? [];
            $_FILES = $this->convertUploadedFiles($request->getUploadedFiles());
            $_COOKIE = $request->getCookieParams();

            // 构建完整的 host 信息（参考 Swoole adapter）
            $serverParams = $request->getServerParams();
            $serverPort = $serverParams['SERVER_PORT'] ?? '8080';
            $hostHeader = $request->getHeaderLine('host');

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
            $_SERVER = array_merge($_SERVER, $serverParams, [
                'HTTP_HOST' => $httpHost,
                'SERVER_NAME' => $serverName,
                'REQUEST_METHOD' => $request->getMethod(),
                'REQUEST_URI' => $request->getUri()->getPath() . ($request->getUri()->getQuery() ? '?' . $request->getUri()->getQuery() : ''),
                'PATH_INFO' => $request->getUri()->getPath(),
                'QUERY_STRING' => $request->getUri()->getQuery(),
                'HTTP_USER_AGENT' => $request->getHeaderLine('user-agent') ?: 'ReactPHP/1.0',
                'HTTP_ACCEPT' => $request->getHeaderLine('accept') ?: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'CONTENT_TYPE' => $request->getHeaderLine('content-type'),
                'CONTENT_LENGTH' => $request->getHeaderLine('content-length'),
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'REQUEST_TIME' => time(),
                'REQUEST_TIME_FLOAT' => microtime(true),
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php',
                'GATEWAY_INTERFACE' => 'CGI/1.1',
                'SERVER_SOFTWARE' => 'ReactPHP/1.0',
                'REMOTE_ADDR' => $serverParams['REMOTE_ADDR'] ?? '127.0.0.1',
                'REMOTE_HOST' => 'localhost',
                'DOCUMENT_ROOT' => getcwd() . '/public',
                'REQUEST_SCHEME' => $request->getUri()->getScheme() ?: 'http',
                'SERVER_PORT' => $serverPort,
                'HTTPS' => $request->getUri()->getScheme() === 'https' ? 'on' : '',
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

            // 更新应用中的 Request 对象信息（参考 SwooleAdapter）
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
                // 处理请求
                $response = $this->handleRequest($request);

                // 返回ReactPHP Response
                return resolve(
                    new Response(
                        $response->getStatusCode(),
                        $response->getHeaders(),
                        (string) $response->getBody()
                    )
                );
            } finally {
                // 恢复原应用实例
                $this->app = $originalApp;
                // 恢复原始全局变量
                $_GET = $originalGet;
                $_POST = $originalPost;
                $_FILES = $originalFiles;
                $_COOKIE = $originalCookie;
                $_SERVER = $originalServer;
                // 清理临时上传文件
                $this->cleanupTempUploadFiles();
            }

        } catch (Throwable $e) {
            return resolve(
                $this->handleReactError($e)
            );
        }
    }

    /**
     * 转换 ReactPHP UploadedFile 对象为传统 $_FILES 格式
     *
     * @param array $uploadedFiles PSR-7 UploadedFile 对象数组
     * @return array 传统 $_FILES 格式数组
     */
    protected function convertUploadedFiles(array $uploadedFiles): array
    {
        $files = [];
        $tempFiles = []; // 记录临时文件，用于后续清理

        foreach ($uploadedFiles as $name => $uploadedFile) {
            if ($uploadedFile instanceof \Psr\Http\Message\UploadedFileInterface) {
                try {
                    // 创建临时文件
                    $tmpName = tempnam(sys_get_temp_dir(), 'reactphp_upload_');

                    // 将上传内容写入临时文件
                    $stream = $uploadedFile->getStream();
                    $stream->rewind(); // 确保从头开始读取
                    file_put_contents($tmpName, $stream->getContents());

                    // 记录临时文件路径，用于后续清理
                    $tempFiles[] = $tmpName;

                    // 构建 $_FILES 格式的数组
                    $files[$name] = [
                        'name' => $uploadedFile->getClientFilename() ?? '',
                        'type' => $uploadedFile->getClientMediaType() ?? '',
                        'size' => $uploadedFile->getSize() ?? 0,
                        'tmp_name' => $tmpName,
                        'error' => $uploadedFile->getError() ?? UPLOAD_ERR_OK,
                    ];
                } catch (\Throwable $e) {
                    // 如果处理失败，设置错误状态
                    $files[$name] = [
                        'name' => $uploadedFile->getClientFilename() ?? '',
                        'type' => '',
                        'size' => 0,
                        'tmp_name' => '',
                        'error' => UPLOAD_ERR_CANT_WRITE,
                    ];
                }
            } elseif (is_array($uploadedFile)) {
                // 处理多文件上传的情况
                $files[$name] = $this->convertUploadedFiles($uploadedFile);
            }
        }

        // 将临时文件列表存储到类属性中，用于后续清理
        $this->tempUploadFiles = array_merge($this->tempUploadFiles ?? [], $tempFiles);

        return $files;
    }

    /**
     * 清理临时上传文件
     *
     * @return void
     */
    protected function cleanupTempUploadFiles(): void
    {
        if (!empty($this->tempUploadFiles)) {
            foreach ($this->tempUploadFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
            $this->tempUploadFiles = [];
        }
    }

    /**
     * 处理ReactPHP错误
     *
     * @param Throwable $e 异常
     * @return Response ReactPHP响应
     */
    protected function handleReactError(Throwable $e): Response
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
     * 获取事件循环
     *
     * @return LoopInterface|null
     */
    public function getLoop(): ?LoopInterface
    {
        return $this->loop;
    }

    /**
     * 添加定时器
     *
     * @param float $interval 间隔时间（秒）
     * @param callable $callback 回调函数
     * @return TimerInterface
     */
    public function addTimer(float $interval, callable $callback): TimerInterface
    {
        if ($this->loop === null) {
            throw new RuntimeException('Event loop not initialized');
        }

        return $this->loop->addTimer($interval, $callback);
    }

    /**
     * 添加周期性定时器
     *
     * @param float $interval 间隔时间（秒）
     * @param callable $callback 回调函数
     * @return TimerInterface
     */
    public function addPeriodicTimer(float $interval, callable $callback): TimerInterface
    {
        if ($this->loop === null) {
            throw new RuntimeException('Event loop not initialized');
        }

        return $this->loop->addPeriodicTimer($interval, $callback);
    }

    /**
     * 取消定时器
     *
     * @param TimerInterface $timer 定时器
     * @return void
     */
    public function cancelTimer(TimerInterface $timer): void
    {
        if ($this->loop !== null) {
            $this->loop->cancelTimer($timer);
        }
    }
}
