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
        // 内存管理配置
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 100, // 每100个请求GC一次
            'cleanup_interval' => 60, // 60秒清理一次临时文件
            'max_temp_files' => 1000, // 最大临时文件数量
        ],
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
        $startTime = microtime(true);

        // 增加请求计数
        $this->requestCount++;
        $this->memoryStats['request_count']++;

        // 定期强制垃圾回收
        $this->performPeriodicGC();

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

            // 复用现有应用实例，避免每次请求都克隆
            // 克隆应用实例仍然会导致内存问题

            // 更新应用中的 Request 对象信息
            if ($this->app->has('request')) {
                $appRequest = $this->app->request;
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

            try {
                // 处理请求
                $response = $this->handleRequest($request);

                // 构建运行时头部
                $runtimeHeaders = $this->buildRuntimeHeaders($request);

                // 使用头部去重服务处理所有头部
                $finalHeaders = $this->processResponseHeaders($response, $runtimeHeaders);

                // 返回ReactPHP Response
                return resolve(
                    new Response(
                        $response->getStatusCode(),
                        $finalHeaders,
                        (string) $response->getBody()
                    )
                );
            } finally {
                // 恢复原始全局变量
                $_GET = $originalGet;
                $_POST = $originalPost;
                $_FILES = $originalFiles;
                $_COOKIE = $originalCookie;
                $_SERVER = $originalServer;
                // 清理临时上传文件
                $this->cleanupTempUploadFiles();
                // 更新内存统计
                $this->updateMemoryStats();
                // 定期清理
                $this->performPeriodicCleanup();
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
                    // 检查上传文件是否有错误
                    $uploadError = $uploadedFile->getError();
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        $files[$name] = [
                            'name' => $uploadedFile->getClientFilename() ?? '',
                            'type' => $uploadedFile->getClientMediaType() ?? '',
                            'size' => $uploadedFile->getSize() ?? 0,
                            'tmp_name' => '',
                            'error' => $uploadError,
                        ];
                        continue;
                    }

                    // 创建临时文件
                    $tmpName = tempnam(sys_get_temp_dir(), 'reactphp_upload_');
                    if ($tmpName === false) {
                        throw new \RuntimeException('Failed to create temporary file');
                    }

                    // 将上传内容写入临时文件
                    $stream = $uploadedFile->getStream();
                    if ($stream->isSeekable()) {
                        $stream->rewind(); // 确保从头开始读取
                    }
                    
                    $content = $stream->getContents();
                    $bytesWritten = file_put_contents($tmpName, $content);
                    
                    if ($bytesWritten === false) {
                        @unlink($tmpName); // 清理失败的临时文件
                        throw new \RuntimeException('Failed to write uploaded file content');
                    }

                    // 记录临时文件路径，用于后续清理
                    $tempFiles[] = $tmpName;

                    // 构建 $_FILES 格式的数组
                    $files[$name] = [
                        'name' => $uploadedFile->getClientFilename() ?? '',
                        'type' => $uploadedFile->getClientMediaType() ?? '',
                        'size' => $uploadedFile->getSize() ?? strlen($content),
                        'tmp_name' => $tmpName,
                        'error' => UPLOAD_ERR_OK,
                    ];
                } catch (\Throwable $e) {
                    // 记录错误但不中断请求处理
                    error_log("ReactPHP: Failed to process uploaded file '{$name}': " . $e->getMessage());
                    
                    // 如果处理失败，设置错误状态
                    $files[$name] = [
                        'name' => $uploadedFile->getClientFilename() ?? '',
                        'type' => $uploadedFile->getClientMediaType() ?? '',
                        'size' => $uploadedFile->getSize() ?? 0,
                        'tmp_name' => '',
                        'error' => UPLOAD_ERR_CANT_WRITE,
                    ];
                }
            } elseif (is_array($uploadedFile)) {
                // 处理多文件上传的情况
                try {
                    $files[$name] = $this->convertUploadedFiles($uploadedFile);
                } catch (\Throwable $e) {
                    // 记录错误但不中断请求处理
                    error_log("ReactPHP: Failed to process uploaded file array '{$name}': " . $e->getMessage());
                    $files[$name] = [];
                }
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
            $cleaned = 0;
            foreach ($this->tempUploadFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                    $cleaned++;
                }
            }
            $this->tempUploadFiles = [];

            if ($cleaned > 0) {
                echo "Cleaned {$cleaned} temporary upload files\n";
            }
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

        // 构建错误响应的头部
        $errorHeaders = [
            'Content-Type' => 'application/json',
            'Content-Length' => (string) strlen($content),
        ];

        // 构建运行时头部
        $runtimeHeaders = $this->buildRuntimeHeaders();

        // 使用头部去重服务处理所有头部
        $finalHeaders = $this->headerService->mergeHeaders($errorHeaders, $runtimeHeaders);
        $finalHeaders = $this->headerService->deduplicateHeaders($finalHeaders);

        return new Response(
            500,
            $finalHeaders,
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
                echo "ReactPHP GC freed " . round($freed / 1024 / 1024, 2) . "MB memory\n";
            }
        }
    }

    /**
     * 更新内存统计
     *
     * @return void
     */
    protected function updateMemoryStats(): void
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        if ($peakMemory > $this->memoryStats['peak_usage']) {
            $this->memoryStats['peak_usage'] = $peakMemory;
        }
    }

    /**
     * 执行定期清理
     *
     * @return void
     */
    protected function performPeriodicCleanup(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $cleanupInterval = $config['memory']['cleanup_interval'] ?? 60;

        $now = time();
        if ($now - $this->memoryStats['last_cleanup'] > $cleanupInterval) {
            $this->forceCleanupTempFiles();
            $this->memoryStats['last_cleanup'] = $now;
        }
    }

    /**
     * 强制清理临时文件
     *
     * @return void
     */
    protected function forceCleanupTempFiles(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $maxTempFiles = $config['memory']['max_temp_files'] ?? 1000;

        if (count($this->tempUploadFiles) > $maxTempFiles) {
            $toRemove = count($this->tempUploadFiles) - (int)($maxTempFiles * 0.8);
            $removed = 0;

            foreach ($this->tempUploadFiles as $index => $tempFile) {
                if ($removed >= $toRemove) {
                    break;
                }
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
                unset($this->tempUploadFiles[$index]);
                $removed++;
            }

            if ($removed > 0) {
                echo "ReactPHP force cleaned {$removed} temp files due to limit\n";
                gc_collect_cycles();
            }
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
            error_log("ReactPHP: Error destroying app instance: " . $e->getMessage());
        }
    }

    /**
     * 构建运行时特定的头部
     *
     * @param mixed $request 请求对象
     * @return array 运行时头部数组
     */
    protected function buildRuntimeHeaders($request = null): array
    {
        $headers = parent::buildRuntimeHeaders($request);
        
        // 添加ReactPHP特定的头部
        $headers['Server'] = 'ReactPHP/1.0';
        $headers['X-Powered-By'] = 'ThinkPHP Runtime (ReactPHP)';
        
        // 添加连接相关头部
        $config = array_merge($this->defaultConfig, $this->config);
        if ($config['enable_keepalive'] ?? true) {
            $headers['Connection'] = 'keep-alive';
            $headers['Keep-Alive'] = 'timeout=' . ($config['keepalive_timeout'] ?? 5);
        } else {
            $headers['Connection'] = 'close';
        }
        
        return $headers;
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
            'temp_files_count' => count($this->tempUploadFiles),
        ]);
    }
}
