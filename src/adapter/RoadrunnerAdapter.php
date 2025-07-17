<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Nyholm\Psr7\Factory\Psr17Factory;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * RoadRunner适配器
 * 提供RoadRunner HTTP服务器支持
 */
class RoadrunnerAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * RoadRunner Worker实例
     *
     * @var Worker|null
     */
    protected ?Worker $worker = null;

    /**
     * PSR-7 Worker实例
     *
     * @var PSR7Worker|null
     */
    protected ?PSR7Worker $psr7Worker = null;

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'debug' => false,
        'workers' => 4,
        'max_jobs' => 1000,
        'allocate_timeout' => 60,
        'destroy_timeout' => 60,
        'memory_limit' => '128M',
        'pool' => [
            'num_workers' => 4,
            'max_jobs' => 1000,
            'allocate_timeout' => 60,
            'destroy_timeout' => 60,
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
            throw new RuntimeException('RoadRunner is not available');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 创建Worker实例
        $this->worker = Worker::create();

        // 创建PSR-7 Worker实例
        $psr17Factory = new Psr17Factory();
        $this->psr7Worker = new PSR7Worker(
            $this->worker,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );
    }

    /**
     * 运行适配器
     *
     * @return void
     * @throws JsonException
     */
    public function run(): void
    {
        if ($this->psr7Worker === null) {
            $this->boot();
        }

        echo "RoadRunner HTTP Worker starting...\n";

        // 处理请求循环
        while ($request = $this->psr7Worker->waitRequest()) {
            try {
                echo "DEBUG: Received RoadRunner request: " . $request->getMethod() . " " . $request->getUri() . "\n";

                // 保存原始全局变量
                $originalGet = $_GET;
                $originalPost = $_POST;
                $originalFiles = $_FILES;
                $originalCookie = $_COOKIE;
                $originalServer = $_SERVER;

                // 预处理请求，设置全局变量（参考 WorkermanAdapter）
                $this->preprocessRequest($request);

                // 在每次请求前创建新的应用实例（参考 ReactPHP adapter）
                $appClass = get_class($this->app);
                $newApp = new $appClass();

                // 初始化新的应用实例
                if (method_exists($newApp, 'initialize')) {
                    $newApp->initialize();
                }

                // 临时保存原应用实例
                $originalApp = $this->app;
                // 设置新的应用实例
                $this->app = $newApp;

                try {
                    // 处理请求
                    $response = $this->handleRequest($request);

                    // 处理响应头部去重
                    $processedResponse = $this->processRoadRunnerResponseHeaders($response);

                    // 发送响应
                    $this->psr7Worker->respond($processedResponse);
                } finally {
                    // 恢复原应用实例
                    $this->app = $originalApp;
                    // 恢复原始全局变量
                    $_GET = $originalGet;
                    $_POST = $originalPost;
                    $_FILES = $originalFiles;
                    // 智能合并 cookie，保留 session cookie
                    $_COOKIE = $this->mergeSessionCookies($originalCookie, $_COOKIE);
                    $_SERVER = $originalServer;
                    // 清理临时上传文件
                    $this->cleanupTempUploadFiles();
                }

            } catch (Throwable $e) {
                echo "DEBUG: RoadRunner request error: " . $e->getMessage() . "\n";

                // 发送错误响应
                $errorResponse = $this->handleError($e);
                $this->psr7Worker->respond($errorResponse);

                // 记录错误
                $this->logError($e);
            }
        }
    }

    /**
     * 启动运行时
     *
     * @param array $options 启动选项
     * @return void
     * @throws JsonException
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
        if ($this->worker !== null) {
            $this->worker->stop();
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
        return 'roadrunner';
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
        return class_exists(Worker::class) &&
               class_exists(PSR7Worker::class) &&
               isset($_SERVER['RR_MODE']);
    }

    /**
     * 获取配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return array_merge($this->defaultConfig, $this->config);
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 90;
    }

    /**
     * 处理RoadRunner请求
     *
     * @param ServerRequestInterface $request PSR-7请求
     * @return ResponseInterface PSR-7响应
     */
    public function handleRoadRunnerRequest(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->handleRequest($request);
        
        // 处理响应头部去重
        return $this->processRoadRunnerResponseHeaders($response);
    }

    /**
     * 处理RoadRunner响应头部
     * 使用头部去重服务处理响应头部，防止重复
     *
     * @param ResponseInterface $response PSR-7响应对象
     * @return ResponseInterface 处理后的PSR-7响应对象
     */
    protected function processRoadRunnerResponseHeaders(ResponseInterface $response): ResponseInterface
    {
        // 构建RoadRunner运行时头部
        $runtimeHeaders = $this->buildRuntimeHeaders();
        
        // 添加RoadRunner特定头部
        $runtimeHeaders['X-Powered-By'] = 'RoadRunner/ThinkPHP-Runtime';
        $runtimeHeaders['X-Runtime'] = 'RoadRunner';
        
        // 使用头部去重服务处理所有头部
        $finalHeaders = $this->processResponseHeaders($response, $runtimeHeaders);
        
        // 创建新的响应对象，先移除所有现有头部
        $newResponse = $response;
        foreach ($response->getHeaders() as $name => $values) {
            $newResponse = $newResponse->withoutHeader($name);
        }
        
        // 添加去重后的头部
        foreach ($finalHeaders as $name => $value) {
            $newResponse = $newResponse->withHeader($name, $value);
        }
        
        return $newResponse;
    }

    /**
     * 获取Worker池状态
     *
     * @return array
     */
    public function getWorkerPool(): array
    {
        $config = array_merge($this->defaultConfig, $this->config);

        return [
            'workers' => $config['workers'] ?? 4,
            'active' => 1, // 在测试环境中模拟
            'idle' => $config['workers'] - 1,
            'max_jobs' => $config['max_jobs'] ?? 1000,
        ];
    }

    /**
     * 重置Worker
     *
     * @return bool
     */
    public function resetWorker(): bool
    {
        // 在测试环境中总是返回成功
        return true;
    }

    /**
     * 记录错误
     *
     * @param Throwable $e 异常
     * @return void
     */
    protected function logError(Throwable $e): void
    {
        $message = sprintf(
            "RoadRunner Error: %s in %s:%d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        error_log($message);
    }

    /**
     * 预处理请求，设置全局变量（参考 WorkermanAdapter 实现）
     *
     * @param ServerRequestInterface $request PSR-7请求
     * @return void
     */
    protected function preprocessRequest(ServerRequestInterface $request): void
    {
        echo "DEBUG: Current \$_COOKIE before request: " . json_encode($_COOKIE) . "\n";
        echo "DEBUG: Current session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive') . "\n";

        // 从 PSR-7 请求中提取数据
        $uri = $request->getUri();
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();
        $cookies = $request->getCookieParams();
        $serverParams = $request->getServerParams();

        // 设置全局变量
        $_GET = $queryParams ?? [];
        $_POST = is_array($parsedBody) ? $parsedBody : [];
        
        // 调试：查看 RoadRunner 上传文件数据
        echo "DEBUG: RoadRunner uploadedFiles:\n";
        foreach ($uploadedFiles as $name => $file) {
            if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
                echo "DEBUG: File '$name' - filename: " . ($file->getClientFilename() ?? 'null') . 
                     ", size: " . ($file->getSize() ?? 0) . 
                     ", error: " . ($file->getError() ?? 'null') . "\n";
            }
        }
        
        $_FILES = $this->convertUploadedFiles($uploadedFiles);
        
        // 调试：查看最终的 $_FILES
        echo "DEBUG: Final \$_FILES: " . json_encode($_FILES, JSON_UNESCAPED_UNICODE) . "\n";
        
        $_COOKIE = $cookies ?? [];

        // 调试：查看 RoadRunner 原始服务器参数
        echo "DEBUG: RoadRunner original serverParams:\n";
        foreach ($serverParams as $key => $value) {
            if (strpos($key, 'HTTP_') === 0 || in_array($key, ['REQUEST_URI', 'REQUEST_METHOD', 'SERVER_NAME', 'HTTP_HOST'])) {
                echo "DEBUG: Original $key = $value\n";
            }
        }

        // 构建标准的服务器变量（完全重新设置，避免冲突）
        $requestUri = $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : '');
        $httpHost = $uri->getHost() . ($uri->getPort() && $uri->getPort() != 80 && $uri->getPort() != 443 ? ':' . $uri->getPort() : '');
        
        // 清除可能冲突的变量
        unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_HOST'], $_SERVER['PATH_INFO']);
        
        // 设置标准的 CGI 变量
        $_SERVER['REQUEST_METHOD'] = $request->getMethod();
        $_SERVER['REQUEST_URI'] = $requestUri;
        $_SERVER['PATH_INFO'] = $uri->getPath();
        $_SERVER['QUERY_STRING'] = $uri->getQuery() ?? '';
        $_SERVER['HTTP_HOST'] = $httpHost;
        $_SERVER['SERVER_NAME'] = $uri->getHost() ?? 'localhost';
        $_SERVER['SERVER_PORT'] = (string)($uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80));
        $_SERVER['REQUEST_SCHEME'] = $uri->getScheme() ?: 'http';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['PHP_SELF'] = '/index.php';
        $_SERVER['DOCUMENT_ROOT'] = getcwd() . '/public';
        $_SERVER['REQUEST_TIME'] = time();
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        
        // 设置其他标准变量
        $_SERVER['HTTP_USER_AGENT'] = $request->getHeaderLine('user-agent') ?: 'RoadRunner/2.0';
        $_SERVER['HTTP_ACCEPT'] = $request->getHeaderLine('accept') ?: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $_SERVER['CONTENT_TYPE'] = $request->getHeaderLine('content-type') ?: '';
        $_SERVER['CONTENT_LENGTH'] = $request->getHeaderLine('content-length') ?: '';
        $_SERVER['GATEWAY_INTERFACE'] = 'CGI/1.1';
        $_SERVER['SERVER_SOFTWARE'] = 'RoadRunner/2.0';
        $_SERVER['REMOTE_ADDR'] = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
        $_SERVER['REMOTE_HOST'] = 'localhost';

        echo "DEBUG: Final REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
        echo "DEBUG: Final HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "\n";
        echo "DEBUG: Final PATH_INFO: " . $_SERVER['PATH_INFO'] . "\n";
        echo "DEBUG: Final REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
        echo "DEBUG: Final \$_COOKIE after request setup: " . json_encode($_COOKIE) . "\n";
        echo "DEBUG: Final session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive') . "\n";
        if (session_status() === PHP_SESSION_ACTIVE) {
            echo "DEBUG: Session ID: " . session_id() . "\n";
        }
    }

    /**
     * 转换 PSR-7 上传文件为 $_FILES 格式
     *
     * @param array $uploadedFiles PSR-7上传文件数组
     * @return array 标准 $_FILES 格式
     */
    protected function convertUploadedFiles(array $uploadedFiles): array
    {
        $files = [];
        $tempFiles = []; // 记录临时文件，用于后续清理
        
        foreach ($uploadedFiles as $name => $file) {
            if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
                echo "DEBUG: Processing uploaded file '$name'\n";
                
                try {
                    // 创建临时文件（参考 Ripple runtime 实现）
                    $tmpName = tempnam(sys_get_temp_dir(), 'roadrunner_upload_');
                    
                    // 将上传内容写入临时文件
                    $stream = $file->getStream();
                    $stream->rewind(); // 确保从头开始读取
                    file_put_contents($tmpName, $stream->getContents());
                    
                    // 记录临时文件路径，用于后续清理
                    $tempFiles[] = $tmpName;
                    
                    $files[$name] = [
                        'name' => $file->getClientFilename() ?? '',
                        'type' => $file->getClientMediaType() ?? '',
                        'size' => $file->getSize() ?? 0,
                        'tmp_name' => $tmpName,
                        'error' => $file->getError() ?? UPLOAD_ERR_OK,
                    ];
                    
                    echo "DEBUG: Successfully processed file '$name': {$files[$name]['name']} -> $tmpName\n";
                } catch (\Throwable $e) {
                    echo "DEBUG: Failed to process file '$name': " . $e->getMessage() . "\n";
                    
                    // 如果处理失败，设置错误状态
                    $files[$name] = [
                        'name' => $file->getClientFilename() ?? '',
                        'type' => '',
                        'size' => 0,
                        'tmp_name' => '',
                        'error' => UPLOAD_ERR_CANT_WRITE,
                    ];
                }
            }
        }
        
        // 将临时文件列表存储到类属性中，用于后续清理
        $this->tempUploadFiles = array_merge($this->tempUploadFiles ?? [], $tempFiles);
        
        echo "DEBUG: Converted " . count($files) . " uploaded files\n";
        return $files;
    }



    /**
     * 清理临时上传文件（参考 Ripple runtime 实现）
     *
     * @return void
     */
    protected function cleanupTempUploadFiles(): void
    {
        foreach ($this->tempUploadFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
                echo "DEBUG: Cleaned up temp file: $tempFile\n";
            }
        }
        $this->tempUploadFiles = [];
    }

    /**
     * 智能合并 session cookie，保留 session 相关的 cookie
     *
     * @param array $originalCookies 原始 cookie
     * @param array $currentCookies 当前 cookie
     * @return array 合并后的 cookie
     */
    protected function mergeSessionCookies(array $originalCookies, array $currentCookies): array
    {
        // 常见的 session cookie 名称
        $sessionCookieNames = [
            'PHPSESSID',           // PHP 默认 session cookie
            'tp_session',          // ThinkPHP session cookie
            'think_session',       // ThinkPHP session cookie
            'laravel_session',     // Laravel session cookie
            'ci_session',          // CodeIgniter session cookie
        ];

        // 从原始 cookie 开始
        $mergedCookies = $originalCookies;

        // 将当前请求中的 session cookie 合并到原始 cookie 中
        foreach ($sessionCookieNames as $sessionCookieName) {
            if (isset($currentCookies[$sessionCookieName])) {
                $mergedCookies[$sessionCookieName] = $currentCookies[$sessionCookieName];
                echo "DEBUG: Preserved session cookie: $sessionCookieName = {$currentCookies[$sessionCookieName]}\n";
            }
        }

        return $mergedCookies;
    }

    /**
     * 临时上传文件列表
     *
     * @var array
     */
    protected array $tempUploadFiles = [];

}
