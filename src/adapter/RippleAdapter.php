<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use Fiber;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Ripple\Http\Server;
use RuntimeException;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

use function Co\wait;

/**
 * Ripple适配器
 * 提供基于PHP Fiber的高性能协程HTTP服务器支持
 */
class RippleAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * Ripple服务器实例
     *
     * @var object|null
     */
    protected ?object $server = null;

    /**
     * 协程池
     *
     * @var array
     */
    protected array $coroutinePool = [];

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
        'worker_num' => 4,
        'max_connections' => 10000,
        'max_coroutines' => 100000,
        'coroutine_pool_size' => 1000,
        'timeout' => 30,
        'enable_keepalive' => true,
        'keepalive_timeout' => 60,
        'max_request_size' => '8M',
        'enable_compression' => true,
        'compression_level' => 6,
        'debug' => false,
        'access_log' => true,
        'error_log' => true,
        'enable_fiber' => true,
        'fiber_stack_size' => 8192,
        'ssl' => [
            'enabled' => false,
            'cert_file' => '',
            'key_file' => '',
            'verify_peer' => false,
        ],
        'database' => [
            'pool_size' => 10,
            'max_idle_time' => 3600,
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
            throw new RuntimeException('Ripple is not available');
        }

        // 初始化应用
        $this->app->initialize();
        
        // 创建Ripple服务器
        $this->createRippleServer();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->server === null) {
            $this->boot();
        }

        $config = array_merge($this->defaultConfig, $this->config);
        
        echo "Ripple HTTP Server starting...\n";
        echo "Listening on: {$config['host']}:{$config['port']}\n";
        echo "Workers: {$config['worker_num']}\n";
        echo "Max Coroutines: {$config['max_coroutines']}\n";
        echo "Fiber Support: " . ($config['enable_fiber'] ? 'Yes' : 'No') . "\n";
        echo "Coroutine Pool: {$config['coroutine_pool_size']}\n";
        echo "Press Ctrl+C to stop the server\n\n";
        
        // 启动Ripple服务器
        $this->startRippleServer();
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

    // 设置无限执行时间，Ripple服务器需要持续运行
    set_time_limit(0);
        $this->run();
    }

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->server !== null && method_exists($this->server, 'stop')) {
            $this->server->stop();
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
        return 'ripple';
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
        // 检查PHP版本是否支持Fiber
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            return false;
        }
        
        // 检查Ripple相关类是否存在
        return class_exists('Ripple\\Http\\Server') || 
               class_exists('Ripple\\Server\\Server') ||
               function_exists('ripple_server_create');
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 91; // 高优先级，在ReactPHP和RoadRunner之间
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
     * 创建Ripple服务器
     *
     * @return void
     */
    protected function createRippleServer(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        // 根据不同的Ripple版本创建服务器
        if (class_exists('Ripple\\Http\\Server')) {
            $this->server = new Server(
            'http://' . $config['host'] . ':' . $config['port']
            );
        } elseif (class_exists('Ripple\\Server\\Server')) {
            $this->server = new \Ripple\Server\Server([
                'host' => $config['host'],
                'port' => $config['port'],
                'worker_num' => $config['worker_num'],
            ]);
        } else {
            // 模拟Ripple服务器（用于测试环境）
            $this->server = new class($config) {
                private array $config;
                
                public function __construct(array $config)
                {
                    $this->config = $config;
                }
                
                public function on(string $event, callable $callback): void
                {
                    // 模拟事件绑定
                }
                
                public function start(): void
                {
                    // 模拟启动
                    echo "Mock Ripple server started\n";
                }
                
                public function stop(): void
                {
                    // 模拟停止
                    echo "Mock Ripple server stopped\n";
                }
            };
        }
        
        // 绑定请求处理器
        if (method_exists($this->server, 'on')) {
            $this->server->on('request', [$this, 'handleRippleRequest']);
        }
    }

    /**
     * 启动Ripple服务器
     *
     * @return void
     */
    protected function startRippleServer(): void
    {
    // 设置无限执行时间，因为Ripple服务器需要持续运行
    set_time_limit(0);
    ini_set('memory_limit', '512M');
        if ($this->server !== null) {
            echo "Setting up Ripple server...\n";
            
            // 设置请求处理器
            if (method_exists($this->server, 'onRequest')) {
                echo "Setting onRequest handler...\n";
                $this->server->onRequest(function($request) {
                    echo "Received request: " . ($request->SERVER['REQUEST_URI'] ?? '/') . "\n";
                    $this->handleRippleRequest($request);
                });
            }
            
            // 启动服务器监听
            if (method_exists($this->server, 'listen')) {
                echo "Starting server listen...\n";
                $this->server->listen();
                
                // 保持事件循环运行
                if (function_exists('Co\\wait')) {
                    echo "Calling wait()...\n";
                    wait();
                }
            }
        }
    }

    /**
     * 处理Ripple请求
     *
     * @param mixed $request Ripple请求对象
     * @param mixed $response Ripple响应对象
     * @return void
     */
    public function handleRippleRequest(mixed $request): void
    {
        try {
            echo "Processing request through ThinkPHP...\n";

            // 保存原始全局变量
            $originalGet = $_GET;
            $originalPost = $_POST;
            $originalFiles = $_FILES;
            $originalCookie = $_COOKIE;
            $originalServer = $_SERVER;

            // 从 Ripple Request 对象中提取数据并设置全局变量

            if (isset($request->GET)) {
                $_GET = $request->GET;
            }
            if (isset($request->POST)) {
                $_POST = $request->POST;
            }
            if (isset($request->FILES)) {
                echo "DEBUG: Ripple request has FILES property\n";
                echo "DEBUG: Raw FILES data: " . json_encode($request->FILES, JSON_UNESCAPED_UNICODE) . "\n";
                
                // 检查是否有其他可能的文件数据源
                echo "DEBUG: Checking Ripple request properties:\n";
                $requestVars = get_object_vars($request);
                foreach ($requestVars as $key => $value) {
                    if (stripos($key, 'file') !== false || stripos($key, 'upload') !== false) {
                        echo "DEBUG: Found potential file property '$key': " . json_encode($value) . "\n";
                    }
                }
                
                // 转换文件格式，参考 WorkermanAdapter 实现
                $_FILES = $this->convertRippleFiles($request->FILES);
                
                // 如果没有处理到文件，尝试从 POST 数据中查找
                if (empty($_FILES) && !empty($request->POST)) {
                    echo "DEBUG: Checking POST data for file information\n";
                    foreach ($request->POST as $key => $value) {
                        if (stripos($key, 'file') !== false && is_array($value)) {
                            echo "DEBUG: Found file data in POST['$key']: " . json_encode($value) . "\n";
                            $_FILES[$key] = [
                                'name' => $value['name'] ?? 'uploaded_file',
                                'type' => $value['type'] ?? 'application/octet-stream',
                                'size' => isset($value['size']) ? (int)$value['size'] : 0,
                                'tmp_name' => $value['tmp_name'] ?? '',
                                'error' => isset($value['error']) ? (int)$value['error'] : UPLOAD_ERR_OK,
                            ];
                        }
                    }
                }
                
                // 输出最终的 $_FILES 结果
                echo "DEBUG: Final \$_FILES: " . json_encode($_FILES, JSON_UNESCAPED_UNICODE) . "\n";
                if (!empty($_FILES)) {
                    echo "DEBUG: Successfully processed " . count($_FILES) . " file(s)\n";
                } else {
                    echo "DEBUG: No valid files processed\n";
                }
            } else {
                echo "DEBUG: Ripple request has no FILES property\n";
                $_FILES = [];
            }
            if (isset($request->COOKIE)) {
                $_COOKIE = $request->COOKIE;
            }

            // 构建完整的 host 信息（参考 Swoole adapter）
            $serverPort = $request->SERVER['SERVER_PORT'] ?? '8080';
            $hostHeader = $request->SERVER['HTTP_HOST'] ?? null;

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
            if (isset($request->SERVER)) {
                $_SERVER = array_merge($_SERVER, $request->SERVER, [
                    'HTTP_HOST' => $httpHost,
                    'SERVER_NAME' => $serverName,
                    'SERVER_PROTOCOL' => 'HTTP/1.1',
                    'REQUEST_TIME' => time(),
                    'REQUEST_TIME_FLOAT' => microtime(true),
                    'SCRIPT_NAME' => '/index.php',
                    'PHP_SELF' => '/index.php',
                    'GATEWAY_INTERFACE' => 'CGI/1.1',
                    'SERVER_SOFTWARE' => 'Ripple/1.0',
                    'REMOTE_ADDR' => $request->SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'REMOTE_HOST' => 'localhost',
                    'DOCUMENT_ROOT' => getcwd() . '/public',
                    'REQUEST_SCHEME' => 'http',
                    'SERVER_PORT' => $serverPort,
                    'HTTPS' => '',
                    // 设置命令行相关的变量为安全值（避免 think-trace 报错）
                    'argv' => [],
                    'argc' => 0,
                ]);
            }

            // 在每次请求前创建新的应用实例（像 ReactPHP 适配器一样）
            $appClass = get_class($this->app);
            $newApp = new $appClass();

            // 初始化新的应用实例
            if (method_exists($newApp, 'initialize')) {
                $newApp->initialize();
            }

            // 更新应用中的 Request 对象信息（参考 SwooleAdapter）
            if ($newApp->has('request')) {
                $appRequest = $newApp->request;
                $reflection = new \ReflectionClass($appRequest);

                // 更新 Request 对象的 server 属性
                if (property_exists($appRequest, 'server')) {
                    $serverProperty = $reflection->getProperty('server');
                    $serverProperty->setAccessible(true);
                    $serverProperty->setValue($appRequest, $_SERVER);
                }

                // 强制重置 host 缓存
                if (property_exists($appRequest, 'host')) {
                    $hostProperty = $reflection->getProperty('host');
                    $hostProperty->setAccessible(true);
                    $hostProperty->setValue($appRequest, null); // 重置，让它重新从 server 中读取
                }

                // 强制更新 Request 对象的文件信息
                try {
                    $fileProperty = $reflection->getProperty('file');
                    $fileProperty->setAccessible(true);
                    $fileProperty->setValue($appRequest, $_FILES);
                } catch (\Exception $e) {
                    echo "ERROR: Failed to update Request file property: " . $e->getMessage() . "\n";
                }
            }

            // 临时保存原应用实例
            $originalApp = $this->app;
            // 设置新的应用实例
            $this->app = $newApp;

            echo "Created new app instance: " . $appClass . "\n";

            // 确保新应用实例也设置无限执行时间
            set_time_limit(0);

            try {
                // 转换为PSR-7请求
                $psr7Request = $this->convertRippleRequestToPsr7($request);

                // 通过 ThinkPHP 完整流程处理请求（包括中间件、trace等）
                $psr7Response = $this->handleRequest($psr7Request);

                // 发送响应
                $this->sendRippleResponse($psr7Response, $request);
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

            echo "Restored original app instance\n";

        } catch (Throwable $e) {
            echo "Error: " . $e->getMessage() . "\n";
            $this->handleRippleError($e, $request);
        }
    }
    /**
     * 在协程中处理请求
     *
     * @param mixed $request Ripple请求对象
     * @param mixed $response Ripple响应对象
     * @return void
     * @throws Throwable
     */
    protected function handleRequestInCoroutine(mixed $request, mixed $response): void
    {
        // 使用Fiber处理请求
        if (class_exists('Fiber')) {
            $fiber = new Fiber(function () use ($request, $response) {
                $this->processRequest($request, $response);
            });
            $fiber->start();
        } else {
            // 降级到同步处理
            $this->processRequest($request, $response);
        }
    }

    /**
     * 同步处理请求
     *
     * @param mixed $request Ripple请求对象
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function handleRequestSync(mixed $request, mixed $response): void
    {
        $this->processRequest($request, $response);
    }

    /**
     * 处理请求核心逻辑
     *
     * @param mixed $request Ripple请求对象
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function processRequest(mixed $request, mixed $response): void
    {
        // 转换为PSR-7请求
        $psr7Request = $this->convertRippleRequestToPsr7($request);
        
        // 处理请求
        $psr7Response = $this->handleRequest($psr7Request);
        
        // 发送响应
        $this->sendRippleResponse($psr7Response, $response);
    }

    /**
     * 转换 Ripple 文件对象为传统 $_FILES 格式（参考 WorkermanAdapter 实现）
     *
     * @param mixed $files Ripple 文件数据
     * @return array 标准 PHP $_FILES 格式数组
     */
    protected function convertRippleFiles($files): array
    {
        if (!is_array($files)) {
            echo "DEBUG: Ripple FILES is not array, type: " . gettype($files) . "\n";
            return [];
        }

        echo "DEBUG: Processing Ripple FILES with " . count($files) . " items\n";
        
        $validFiles = [];
        $tempFiles = []; // 记录临时文件，用于后续清理

        foreach ($files as $name => $file) {
            echo "DEBUG: Processing file '$name', type: " . gettype($file) . "\n";
            
            // 检查是否是 PSR-7 UploadedFileInterface 对象（类似 ReactPHP）
            if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
                echo "DEBUG: File '$name' is PSR-7 UploadedFileInterface\n";
                try {
                    // 创建临时文件
                    $tmpName = tempnam(sys_get_temp_dir(), 'ripple_upload_');

                    // 将上传内容写入临时文件
                    $stream = $file->getStream();
                    $stream->rewind(); // 确保从头开始读取
                    file_put_contents($tmpName, $stream->getContents());

                    // 记录临时文件路径，用于后续清理
                    $tempFiles[] = $tmpName;

                    // 构建 $_FILES 格式的数组
                    $validFiles[$name] = [
                        'name' => $file->getClientFilename() ?? '',
                        'type' => $file->getClientMediaType() ?? '',
                        'size' => $file->getSize() ?? 0,
                        'tmp_name' => $tmpName,
                        'error' => $file->getError() ?? UPLOAD_ERR_OK,
                    ];
                    echo "DEBUG: Successfully processed PSR-7 file '$name'\n";
                } catch (\Throwable $e) {
                    echo "DEBUG: Failed to process PSR-7 file '$name': " . $e->getMessage() . "\n";
                    // 如果处理失败，设置错误状态
                    $validFiles[$name] = [
                        'name' => $file->getClientFilename() ?? '',
                        'type' => '',
                        'size' => 0,
                        'tmp_name' => '',
                        'error' => UPLOAD_ERR_CANT_WRITE,
                    ];
                }
            } elseif (is_array($file)) {
                echo "DEBUG: File '$name' is array format\n";
                
                // 检查是否是 Ripple 特有的格式：数组包含对象
                if (is_numeric(array_keys($file)[0] ?? null)) {
                    echo "DEBUG: File '$name' is Ripple indexed array format\n";
                    // Ripple 格式：{"file":[{}]} - 处理数组中的第一个元素
                    $fileData = $file[0] ?? [];
                    if (is_array($fileData) || is_object($fileData)) {
                        echo "DEBUG: Processing Ripple file data: " . json_encode($fileData) . "\n";
                        
                        // 转换对象为数组
                        if (is_object($fileData)) {
                            $fileData = (array)$fileData;
                        }
                        
                        // 构建标准文件数组
                        $standardFile = [
                            'name' => $fileData['name'] ?? $fileData['filename'] ?? '',
                            'type' => $fileData['type'] ?? $fileData['mime'] ?? $fileData['content-type'] ?? '',
                            'size' => isset($fileData['size']) ? (int)$fileData['size'] : 0,
                            'tmp_name' => $fileData['tmp_name'] ?? $fileData['path'] ?? '',
                            'error' => isset($fileData['error']) ? (int)$fileData['error'] : UPLOAD_ERR_OK,
                        ];
                        
                        // 如果没有临时文件路径但有内容，创建临时文件
                        if (empty($standardFile['tmp_name']) && !empty($fileData['content'])) {
                            $tmpName = tempnam(sys_get_temp_dir(), 'ripple_upload_');
                            file_put_contents($tmpName, $fileData['content']);
                            $standardFile['tmp_name'] = $tmpName;
                            $tempFiles[] = $tmpName;
                            echo "DEBUG: Created temp file for content: $tmpName\n";
                        }
                        
                        if (!empty($standardFile['name'])) {
                            $validFiles[$name] = $standardFile;
                            echo "DEBUG: Successfully processed Ripple file '$name': {$standardFile['name']}\n";
                        } else {
                            echo "DEBUG: Ripple file '$name' has no name, data: " . json_encode($fileData) . "\n";
                        }
                    } else {
                        echo "DEBUG: Ripple file '$name' data is not array/object\n";
                    }
                } elseif (isset($file['name']) && is_array($file['name'])) {
                    echo "DEBUG: File '$name' is multi-file upload\n";
                    // 处理多文件上传的情况
                    $validFiles[$name] = $this->convertRippleFiles($file);
                } else {
                    echo "DEBUG: File '$name' is single file array format\n";
                    // 处理传统数组格式
                    $standardFile = [
                        'name' => $file['name'] ?? '',
                        'type' => $file['type'] ?? '',
                        'size' => isset($file['size']) ? (int)$file['size'] : 0,
                        'tmp_name' => $file['tmp_name'] ?? '',
                        'error' => isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_OK,
                    ];

                    // 只要有文件名就认为是有效文件（参考 WorkermanAdapter）
                    if (!empty($standardFile['name'])) {
                        $validFiles[$name] = $standardFile;
                        echo "DEBUG: Successfully processed array file '$name': {$standardFile['name']}\n";
                    } else {
                        echo "DEBUG: Skipped empty file '$name'\n";
                    }
                }
            } else {
                echo "DEBUG: File '$name' has unsupported type: " . gettype($file) . "\n";
                // 尝试直接使用（可能是 Ripple 特有的格式）
                if (is_object($file)) {
                    echo "DEBUG: File '$name' is object of class: " . get_class($file) . "\n";
                }
            }
        }

        // 将临时文件列表存储到类属性中，用于后续清理
        $this->tempUploadFiles = array_merge($this->tempUploadFiles ?? [], $tempFiles);

        echo "DEBUG: Converted " . count($validFiles) . " valid files\n";
        return $validFiles;
    }

    /**
     * 清理临时上传文件（参考 ReactPHP 实现）
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
     * 将Ripple请求转换为PSR-7请求
     *
     * @return ServerRequestInterface PSR-7请求
     */
    protected function convertRippleRequestToPsr7(mixed $request): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        // 直接从全局变量创建 PSR-7 请求（全局变量已在 handleRippleRequest 中设置）
        return $creator->fromGlobals();
    }

    /**
     * 发送Ripple响应
     *
     * @param ResponseInterface $psr7Response PSR-7响应
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function sendRippleResponse(ResponseInterface $psr7Response, mixed $request): void
    {
    // 使用 Ripple Request 的 respond 方法发送响应
    if (method_exists($request, 'respond')) {
        // 构建完整的 HTTP 响应
        $statusCode = $psr7Response->getStatusCode();
        $headers = [];
        
        // 收集响应头
        foreach ($psr7Response->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        
        $body = (string) $psr7Response->getBody();
        
        // 发送响应，包含正确的状态码和响应头（特别是 Content-Type）
        $request->respond($body, $headers, $statusCode);
        
        echo "Response sent with headers: " . json_encode($headers) . "\n";
    }
}
    /**
     * 处理Ripple错误
     *
     * @param Throwable $e 异常
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function handleRippleError(Throwable $e, mixed $response): void
    {
        $content = json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);
        
        if (method_exists($response, 'status')) {
            $response->status(500);
        }
        
        if (method_exists($response, 'header')) {
            $response->header('Content-Type', 'application/json');
        }
        
        if (method_exists($response, 'end')) {
            $response->end($content);
        }
    }

    /**
     * 创建协程
     *
     * @param callable $callback 协程回调函数
     * @return mixed 协程ID或对象
     * @throws Throwable
     */
    public function createCoroutine(callable $callback): mixed
    {
        if (class_exists('Fiber')) {
            $fiber = new Fiber($callback);
            $this->coroutinePool[] = $fiber;
            return $fiber->start();
        } elseif (function_exists('go')) {
            return go($callback);
        } else {
            // 降级到同步执行
            return $callback();
        }
    }

    /**
     * 获取协程池状态
     *
     * @return array
     */
    public function getCoroutinePoolStatus(): array
    {
        return [
            'total' => count($this->coroutinePool),
            'active' => count(array_filter($this->coroutinePool, function ($fiber) {
                return $fiber instanceof Fiber && !$fiber->isTerminated();
            })),
        ];
    }
}
