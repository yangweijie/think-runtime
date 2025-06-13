<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use RuntimeException;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * Vercel适配器
 * 提供Vercel Serverless Functions支持
 */
class VercelAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * Vercel请求数据
     *
     * @var array|null
     */
    protected ?array $vercelRequest = null;

    /**
     * 是否为Vercel环境
     *
     * @var bool
     */
    protected bool $isVercelEnvironment = false;

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        // Vercel函数配置
        'vercel' => [
            'timeout' => 10, // Vercel默认超时10秒
            'memory' => 1024, // 默认内存1GB
            'region' => 'auto', // 自动选择区域
            'runtime' => 'php-8.1',
        ],
        // HTTP处理配置
        'http' => [
            'enable_cors' => true,
            'cors_origin' => '*',
            'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
            'max_body_size' => '5mb', // Vercel请求体限制
        ],
        // 错误处理配置
        'error' => [
            'display_errors' => false,
            'log_errors' => true,
            'error_reporting' => E_ALL & ~E_NOTICE,
        ],
        // 性能监控配置
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 1000, // 毫秒
            'memory_threshold' => 80, // 内存使用阈值百分比
        ],
        // 静态文件配置
        'static' => [
            'enable' => false, // Vercel通常由CDN处理静态文件
            'extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'],
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
            throw new RuntimeException('Vercel runtime is not available');
        }

        $config = $this->getConfig();

        // 设置错误处理
        if (isset($config['error']['error_reporting'])) {
            error_reporting($config['error']['error_reporting']);
        }

        if (!($config['error']['display_errors'] ?? false)) {
            ini_set('display_errors', '0');
        }

        if ($config['error']['log_errors'] ?? true) {
            ini_set('log_errors', '1');
        }

        // 设置内存限制
        if (isset($config['vercel']['memory'])) {
            ini_set('memory_limit', $config['vercel']['memory'] . 'M');
        }

        // 设置执行时间限制
        if (isset($config['vercel']['timeout'])) {
            set_time_limit($config['vercel']['timeout']);
        }

        // 检测Vercel环境
        $this->detectVercelEnvironment();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        $this->boot();
        
        // 在Vercel环境中，我们处理单个请求然后退出
        if ($this->isVercelEnvironment) {
            $this->handleVercelRequest();
        } else {
            // 在本地开发环境中，可以模拟Vercel行为
            $this->handleLocalExecution();
        }
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
        // Vercel函数执行完成后会自动停止
        // 这里可以做一些清理工作
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
        return 'vercel';
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
        // 检查是否在Vercel环境中或者有相关的类/函数
        return $this->isVercelEnvironment() || 
               function_exists('vercel_request') ||
               !empty($_ENV['VERCEL']) ||
               !empty($_ENV['VERCEL_ENV']);
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        // 在Vercel环境中优先级很高
        return $this->isVercelEnvironment() ? 180 : 60;
    }

    /**
     * 获取运行时配置（重写父类方法）
     *
     * @return array
     */
    public function getConfig(): array
    {
        $merged = $this->defaultConfig;

        foreach ($this->config as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = array_merge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * 检测Vercel环境
     *
     * @return void
     */
    protected function detectVercelEnvironment(): void
    {
        $this->isVercelEnvironment = $this->isVercelEnvironment();
        
        if ($this->isVercelEnvironment) {
            // 获取Vercel请求数据
            $this->vercelRequest = $this->getVercelRequestData();
        }
    }

    /**
     * 检查是否在Vercel环境中
     *
     * @return bool
     */
    protected function isVercelEnvironment(): bool
    {
        return !empty($_ENV['VERCEL']) || 
               !empty($_ENV['VERCEL_ENV']) ||
               !empty($_ENV['VERCEL_URL']) ||
               isset($_SERVER['HTTP_X_VERCEL_ID']);
    }

    /**
     * 获取Vercel请求数据
     *
     * @return array|null
     */
    protected function getVercelRequestData(): ?array
    {
        // 在Vercel环境中，请求数据通过标准的PHP超全局变量获取
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'headers' => $this->getRequestHeaders(),
            'query' => $_GET ?? [],
            'body' => file_get_contents('php://input') ?: '',
            'server' => $_SERVER ?? [],
        ];
    }

    /**
     * 获取请求头
     *
     * @return array
     */
    protected function getRequestHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($headerName)] = $value;
            }
        }
        
        // 添加一些特殊的头信息
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        return $headers;
    }

    /**
     * 处理Vercel请求
     *
     * @return void
     */
    protected function handleVercelRequest(): void
    {
        try {
            $startTime = microtime(true);
            
            // 将Vercel请求转换为PSR-7请求
            $psr7Request = $this->convertVercelRequestToPsr7($this->vercelRequest);
            
            // 处理请求
            $psr7Response = $this->handleRequest($psr7Request);
            
            // 发送响应
            $this->sendVercelResponse($psr7Response);
            
            // 记录请求指标
            $this->logRequestMetrics($this->vercelRequest, $startTime);
            
        } catch (Throwable $e) {
            $this->handleVercelError($e);
        }
    }

    /**
     * 处理本地执行（开发环境）
     *
     * @return void
     */
    protected function handleLocalExecution(): void
    {
        echo "Vercel Adapter running in local development mode\n";
        echo "This adapter is designed for Vercel serverless functions\n";
        echo "For local development, consider using other adapters like Swoole or ReactPHP\n";
        echo "Or use Vercel CLI: vercel dev\n";
    }

    /**
     * 将Vercel请求转换为PSR-7请求
     *
     * @param array|null $request
     * @return ServerRequestInterface
     */
    protected function convertVercelRequestToPsr7(?array $request): ServerRequestInterface
    {
        if (!$request) {
            throw new RuntimeException('No Vercel request data available');
        }

        $factory = new Psr17Factory();

        $method = $request['method'] ?? 'GET';
        $uri = $request['uri'] ?? '/';
        $headers = $request['headers'] ?? [];
        $body = $request['body'] ?? '';

        // 创建URI
        $psr7Uri = $factory->createUri($uri);

        // 创建请求
        $psr7Request = $factory->createServerRequest($method, $psr7Uri);

        // 添加头信息
        foreach ($headers as $name => $value) {
            $psr7Request = $psr7Request->withHeader($name, $value);
        }

        // 添加查询参数
        if (!empty($request['query'])) {
            $psr7Request = $psr7Request->withQueryParams($request['query']);
        }

        // 添加请求体
        if ($body) {
            $stream = $factory->createStream($body);
            $psr7Request = $psr7Request->withBody($stream);

            // 处理POST数据
            if ($method === 'POST' && isset($headers['content-type'])) {
                $contentType = $headers['content-type'];

                if (str_contains($contentType, 'application/json')) {
                    $jsonData = json_decode($body, true);
                    if (is_array($jsonData)) {
                        $psr7Request = $psr7Request->withParsedBody($jsonData);
                    }
                } elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                    parse_str($body, $formData);
                    $psr7Request = $psr7Request->withParsedBody($formData);
                }
            }
        }

        return $psr7Request;
    }

    /**
     * 发送Vercel响应
     *
     * @param ResponseInterface $response
     * @return void
     */
    protected function sendVercelResponse(ResponseInterface $response): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 设置状态码
        http_response_code($response->getStatusCode());

        // 设置响应头
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        // 添加CORS头（如果启用）
        if ($config['http']['enable_cors']) {
            header('Access-Control-Allow-Origin: ' . $config['http']['cors_origin']);
            header('Access-Control-Allow-Methods: ' . $config['http']['cors_methods']);
            header('Access-Control-Allow-Headers: ' . $config['http']['cors_headers']);
            header('Access-Control-Allow-Credentials: true');
        }

        // 添加Vercel特定的头信息
        if ($this->isVercelEnvironment) {
            header('X-Powered-By: ThinkPHP-Vercel-Runtime');

            if (!empty($_ENV['VERCEL_REGION'])) {
                header('X-Vercel-Region: ' . $_ENV['VERCEL_REGION']);
            }
        }

        // 输出响应体
        echo $response->getBody();
    }

    /**
     * 处理Vercel错误
     *
     * @param Throwable $e
     * @return void
     */
    protected function handleVercelError(Throwable $e): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 设置错误状态码
        http_response_code(500);
        header('Content-Type: application/json');

        $errorData = [
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ];

        // 在开发环境中添加更多错误信息
        if ($config['error']['display_errors']) {
            $errorData['file'] = $e->getFile();
            $errorData['line'] = $e->getLine();
            $errorData['trace'] = $e->getTraceAsString();
        }

        // 添加CORS头（如果启用）
        if ($config['http']['enable_cors']) {
            header('Access-Control-Allow-Origin: ' . $config['http']['cors_origin']);
            header('Access-Control-Allow-Methods: ' . $config['http']['cors_methods']);
            header('Access-Control-Allow-Headers: ' . $config['http']['cors_headers']);
        }

        echo json_encode($errorData, JSON_UNESCAPED_UNICODE);

        // 记录错误日志
        if ($config['error']['log_errors']) {
            error_log("Vercel Runtime Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * 记录请求指标
     *
     * @param array|null $request
     * @param float $startTime
     * @return void
     */
    protected function logRequestMetrics(?array $request, float $startTime): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if (!$config['monitor']['enable']) {
            return;
        }

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // 转换为毫秒
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $metrics = [
            'duration' => round($duration, 2),
            'memory' => $memoryUsage,
            'peak_memory' => $peakMemory,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if ($request) {
            $metrics['method'] = $request['method'] ?? 'UNKNOWN';
            $metrics['uri'] = $request['uri'] ?? '/';
        }

        // 检查内存使用
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit !== '-1') {
            $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
            $memoryUsagePercent = ($peakMemory / $memoryLimitBytes) * 100;

            if ($memoryUsagePercent > $config['monitor']['memory_threshold']) {
                $metrics['memory_warning'] = "High memory usage: {$memoryUsagePercent}%";
                error_log("High memory usage in Vercel function: " . json_encode($metrics));
            }
        }

        // 记录慢请求
        if ($duration > $config['monitor']['slow_request_threshold']) {
            error_log("Slow Vercel request: " . json_encode($metrics));
        }
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
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $memoryLimit;
        }
    }
}
