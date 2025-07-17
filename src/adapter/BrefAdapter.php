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
 * Bref适配器
 * 提供AWS Lambda上的PHP运行时支持
 */
class BrefAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * Lambda事件数据
     *
     * @var array|null
     */
    protected ?array $lambdaEvent = null;

    /**
     * Lambda上下文
     *
     * @var array|null
     */
    protected ?array $lambdaContext = null;

    /**
     * 是否为HTTP事件
     *
     * @var bool
     */
    protected bool $isHttpEvent = false;

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        // Lambda运行时配置
        'lambda' => [
            'timeout' => 30,
            'memory' => 512,
            'environment' => 'production',
        ],
        // HTTP处理配置
        'http' => [
            'enable_cors' => true,
            'cors_origin' => '*',
            'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
        ],
        // 错误处理配置
        'error' => [
            'display_errors' => false,
            'log_errors' => true,
        ],
        // 性能监控配置
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 1000, // 毫秒
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
            throw new RuntimeException('Bref runtime is not available');
        }

        $config = $this->getConfig();

        // 设置错误处理
        if (!($config['error']['display_errors'] ?? false)) {
            ini_set('display_errors', '0');
        }

        if ($config['error']['log_errors'] ?? true) {
            ini_set('log_errors', '1');
        }

        // 设置内存限制
        if (isset($config['lambda']['memory'])) {
            ini_set('memory_limit', $config['lambda']['memory'] . 'M');
        }

        // 检测Lambda环境
        $this->detectLambdaEnvironment();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        $this->boot();
        
        // 在Lambda环境中，我们不需要启动服务器
        // 而是等待Lambda运行时调用我们的处理函数
        if ($this->isLambdaEnvironment()) {
            $this->handleLambdaExecution();
        } else {
            // 在本地开发环境中，可以模拟Lambda行为
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
        // Lambda环境中不需要显式停止
        // 函数执行完成后会自动停止
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
        return 'bref';
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
        // 检查是否在Lambda环境中或者有bref相关的类
        return $this->isLambdaEnvironment() || 
               class_exists('\Bref\Context\Context') ||
               class_exists('\Runtime\Bref\Runtime');
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        // 在Lambda环境中优先级最高
        return $this->isLambdaEnvironment() ? 200 : 50;
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
     * 检测Lambda环境
     *
     * @return void
     */
    protected function detectLambdaEnvironment(): void
    {
        // 检查Lambda环境变量
        $this->lambdaEvent = $this->getLambdaEvent();
        $this->lambdaContext = $this->getLambdaContext();
        
        // 判断是否为HTTP事件
        $this->isHttpEvent = $this->isHttpLambdaEvent($this->lambdaEvent);
    }

    /**
     * 检查是否在Lambda环境中
     *
     * @return bool
     */
    protected function isLambdaEnvironment(): bool
    {
        return !empty($_ENV['AWS_LAMBDA_FUNCTION_NAME']) || 
               !empty($_ENV['LAMBDA_TASK_ROOT']) ||
               !empty($_ENV['AWS_EXECUTION_ENV']);
    }

    /**
     * 获取Lambda事件数据
     *
     * @return array|null
     */
    protected function getLambdaEvent(): ?array
    {
        // 从环境变量或标准输入获取事件数据
        if (isset($_ENV['LAMBDA_EVENT'])) {
            return json_decode($_ENV['LAMBDA_EVENT'], true);
        }
        
        // 在实际Lambda环境中，事件数据通过其他方式传递
        return null;
    }

    /**
     * 获取Lambda上下文
     *
     * @return array|null
     */
    protected function getLambdaContext(): ?array
    {
        // 从环境变量获取上下文信息
        return [
            'function_name' => $_ENV['AWS_LAMBDA_FUNCTION_NAME'] ?? '',
            'function_version' => $_ENV['AWS_LAMBDA_FUNCTION_VERSION'] ?? '',
            'memory_limit' => $_ENV['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] ?? '',
            'request_id' => $_ENV['AWS_LAMBDA_LOG_GROUP_NAME'] ?? uniqid(),
        ];
    }

    /**
     * 判断是否为HTTP Lambda事件
     *
     * @param array|null $event
     * @return bool
     */
    protected function isHttpLambdaEvent(?array $event): bool
    {
        if (!$event) {
            return false;
        }

        // API Gateway v1.0 事件
        if (isset($event['httpMethod']) && isset($event['path'])) {
            return true;
        }

        // API Gateway v2.0 事件
        if (isset($event['version']) && $event['version'] === '2.0' && 
            isset($event['requestContext']['http'])) {
            return true;
        }

        // Application Load Balancer 事件
        if (isset($event['requestContext']['elb'])) {
            return true;
        }

        return false;
    }

    /**
     * 处理Lambda执行
     *
     * @return void
     */
    protected function handleLambdaExecution(): void
    {
        if ($this->isHttpEvent) {
            $this->handleHttpEvent();
        } else {
            $this->handleCustomEvent();
        }
    }

    /**
     * 处理本地执行（开发环境）
     *
     * @return void
     */
    protected function handleLocalExecution(): void
    {
        echo "Bref Adapter running in local development mode\n";
        echo "This adapter is designed for AWS Lambda environment\n";
        echo "For local development, consider using other adapters like Swoole or ReactPHP\n";
    }

    /**
     * 处理HTTP事件
     *
     * @return void
     */
    protected function handleHttpEvent(): void
    {
        try {
            $startTime = microtime(true);

            // 将Lambda HTTP事件转换为PSR-7请求
            $psr7Request = $this->convertLambdaEventToPsr7($this->lambdaEvent);

            // 处理请求
            $psr7Response = $this->handleRequest($psr7Request);

            // 将PSR-7响应转换为Lambda响应格式
            $lambdaResponse = $this->convertPsr7ToLambdaResponse($psr7Response);

            // 记录请求指标
            $this->logRequestMetrics($this->lambdaEvent, $startTime);

            // 输出响应
            echo json_encode($lambdaResponse);

        } catch (Throwable $e) {
            $this->handleLambdaError($e);
        }
    }

    /**
     * 处理自定义事件
     *
     * @return void
     */
    protected function handleCustomEvent(): void
    {
        try {
            // 处理非HTTP事件，如SQS、S3等
            $result = $this->processCustomEvent($this->lambdaEvent, $this->lambdaContext);

            // 输出结果
            echo json_encode($result);

        } catch (Throwable $e) {
            $this->handleLambdaError($e);
        }
    }

    /**
     * 处理自定义事件的具体逻辑
     *
     * @param array|null $event
     * @param array|null $context
     * @return array
     */
    protected function processCustomEvent(?array $event, ?array $context): array
    {
        // 默认实现：返回事件信息
        return [
            'statusCode' => 200,
            'body' => json_encode([
                'message' => 'Custom event processed successfully',
                'event' => $event,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s'),
            ]),
        ];
    }

    /**
     * 将Lambda HTTP事件转换为PSR-7请求
     *
     * @param array|null $event
     * @return ServerRequestInterface
     */
    protected function convertLambdaEventToPsr7(?array $event): ServerRequestInterface
    {
        if (!$event) {
            throw new RuntimeException('No Lambda event data available');
        }

        $factory = new Psr17Factory();

        // 处理API Gateway v1.0格式
        if (isset($event['httpMethod'])) {
            return $this->convertApiGatewayV1ToPsr7($event, $factory);
        }

        // 处理API Gateway v2.0格式
        if (isset($event['version']) && $event['version'] === '2.0') {
            return $this->convertApiGatewayV2ToPsr7($event, $factory);
        }

        // 处理Application Load Balancer格式
        if (isset($event['requestContext']['elb'])) {
            return $this->convertAlbToPsr7($event, $factory);
        }

        throw new RuntimeException('Unsupported Lambda HTTP event format');
    }

    /**
     * 转换API Gateway v1.0事件为PSR-7请求
     *
     * @param array $event
     * @param Psr17Factory $factory
     * @return ServerRequestInterface
     */
    protected function convertApiGatewayV1ToPsr7(array $event, Psr17Factory $factory): ServerRequestInterface
    {
        $method = $event['httpMethod'] ?? 'GET';
        $path = $event['path'] ?? '/';
        $queryString = http_build_query($event['queryStringParameters'] ?? []);
        $headers = $event['headers'] ?? [];
        $body = $event['body'] ?? '';

        // 构建URI
        $uri = $factory->createUri($path . ($queryString ? '?' . $queryString : ''));

        // 创建请求
        $request = $factory->createServerRequest($method, $uri);

        // 添加头信息
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // 添加请求体
        if ($body) {
            $stream = $factory->createStream($body);
            $request = $request->withBody($stream);
        }

        return $request;
    }

    /**
     * 转换API Gateway v2.0事件为PSR-7请求
     *
     * @param array $event
     * @param Psr17Factory $factory
     * @return ServerRequestInterface
     */
    protected function convertApiGatewayV2ToPsr7(array $event, Psr17Factory $factory): ServerRequestInterface
    {
        $http = $event['requestContext']['http'] ?? [];
        $method = $http['method'] ?? 'GET';
        $path = $http['path'] ?? '/';
        $queryString = $event['rawQueryString'] ?? '';
        $headers = $event['headers'] ?? [];
        $body = $event['body'] ?? '';

        // 构建URI
        $uri = $factory->createUri($path . ($queryString ? '?' . $queryString : ''));

        // 创建请求
        $request = $factory->createServerRequest($method, $uri);

        // 添加头信息
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // 添加请求体
        if ($body) {
            $stream = $factory->createStream($body);
            $request = $request->withBody($stream);
        }

        return $request;
    }

    /**
     * 转换Application Load Balancer事件为PSR-7请求
     *
     * @param array $event
     * @param Psr17Factory $factory
     * @return ServerRequestInterface
     */
    protected function convertAlbToPsr7(array $event, Psr17Factory $factory): ServerRequestInterface
    {
        $method = $event['httpMethod'] ?? 'GET';
        $path = $event['path'] ?? '/';
        $queryString = http_build_query($event['queryStringParameters'] ?? []);
        $headers = $event['headers'] ?? [];
        $body = $event['body'] ?? '';

        // 构建URI
        $uri = $factory->createUri($path . ($queryString ? '?' . $queryString : ''));

        // 创建请求
        $request = $factory->createServerRequest($method, $uri);

        // 添加头信息
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // 添加请求体
        if ($body) {
            $stream = $factory->createStream($body);
            $request = $request->withBody($stream);
        }

        return $request;
    }

    /**
     * 将PSR-7响应转换为Lambda响应格式
     *
     * @param ResponseInterface $response
     * @return array
     */
    protected function convertPsr7ToLambdaResponse(ResponseInterface $response): array
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 构建运行时头部
        $runtimeHeaders = $this->buildRuntimeHeaders();

        // 添加CORS头（如果启用）
        if ($config['http']['enable_cors']) {
            $runtimeHeaders['Access-Control-Allow-Origin'] = $config['http']['cors_origin'];
            $runtimeHeaders['Access-Control-Allow-Methods'] = $config['http']['cors_methods'];
            $runtimeHeaders['Access-Control-Allow-Headers'] = $config['http']['cors_headers'];
        }

        // 添加Bref特定头部
        $runtimeHeaders['X-Powered-By'] = 'Bref/ThinkPHP-Runtime';
        $runtimeHeaders['X-Runtime'] = 'AWS Lambda';

        // 使用头部去重服务处理所有头部
        $finalHeaders = $this->processResponseHeaders($response, $runtimeHeaders);

        $lambdaResponse = [
            'statusCode' => $response->getStatusCode(),
            'headers' => $finalHeaders,
            'body' => (string) $response->getBody(),
        ];

        return $lambdaResponse;
    }

    /**
     * 处理Bref Lambda响应头部
     * 使用头部去重服务处理响应头部，防止重复
     *
     * @param ResponseInterface $response PSR-7响应对象
     * @return ResponseInterface 处理后的PSR-7响应对象
     */
    protected function processBrefResponseHeaders(ResponseInterface $response): ResponseInterface
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        // 构建Bref运行时头部
        $runtimeHeaders = $this->buildRuntimeHeaders();
        
        // 添加CORS头（如果启用）
        if ($config['http']['enable_cors']) {
            $runtimeHeaders['Access-Control-Allow-Origin'] = $config['http']['cors_origin'];
            $runtimeHeaders['Access-Control-Allow-Methods'] = $config['http']['cors_methods'];
            $runtimeHeaders['Access-Control-Allow-Headers'] = $config['http']['cors_headers'];
        }
        
        // 添加Bref特定头部
        $runtimeHeaders['X-Powered-By'] = 'Bref/ThinkPHP-Runtime';
        $runtimeHeaders['X-Runtime'] = 'AWS Lambda';
        
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
     * 处理Lambda错误
     *
     * @param Throwable $e
     * @return void
     */
    protected function handleLambdaError(Throwable $e): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        $errorResponse = [
            'statusCode' => 500,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'error' => true,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], JSON_UNESCAPED_UNICODE),
        ];

        // 在开发环境中添加更多错误信息
        if ($config['error']['display_errors']) {
            $errorResponse['body'] = json_encode([
                'error' => true,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], JSON_UNESCAPED_UNICODE);
        }

        // 添加CORS头（如果启用）
        if ($config['http']['enable_cors']) {
            $errorResponse['headers']['Access-Control-Allow-Origin'] = $config['http']['cors_origin'];
            $errorResponse['headers']['Access-Control-Allow-Methods'] = $config['http']['cors_methods'];
            $errorResponse['headers']['Access-Control-Allow-Headers'] = $config['http']['cors_headers'];
        }

        echo json_encode($errorResponse);
    }

    /**
     * 记录请求指标
     *
     * @param array|null $event
     * @param float $startTime
     * @return void
     */
    protected function logRequestMetrics(?array $event, float $startTime): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        if (!$config['monitor']['enable']) {
            return;
        }

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // 转换为毫秒

        $metrics = [
            'duration' => round($duration, 2),
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if ($event) {
            $metrics['method'] = $event['httpMethod'] ?? $event['requestContext']['http']['method'] ?? 'UNKNOWN';
            $metrics['path'] = $event['path'] ?? $event['requestContext']['http']['path'] ?? '/';
        }

        // 记录慢请求
        if ($duration > $config['monitor']['slow_request_threshold']) {
            error_log("Slow Lambda request: " . json_encode($metrics));
        }
    }
}
