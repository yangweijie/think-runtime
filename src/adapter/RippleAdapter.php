<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use Generator;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Ripple\Driver\ThinkPHP\Worker as RippleWorker;
use Ripple\Kernel;
use Ripple\Socket;
use Ripple\Stream;
use Ripple\Worker\Manager as RippleManager;
use RuntimeException;
use Throwable;
use think\App;
use think\Request;
use think\Response;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * Ripple 运行时适配器
 */
class RippleAdapter extends AbstractRuntime implements AdapterInterface
{


    /**
     * Ripple Worker 实例
     *
     * @var RippleWorker|null
     */
    protected ?RippleWorker $worker = null;

    /**
     * Ripple Manager 实例
     *
     * @var RippleManager|null
     */
    protected ?RippleManager $manager = null;

    /**
     * 是否正在运行
     *
     * @var bool
     */
    protected bool $isRunning = false;

    /**
     * 请求计数器
     *
     * @var int
     */
    protected $requestCount = 0;
    
    /**
     * 当前活跃请求数
     *
     * @var int
     */
    protected $activeRequests = 0;
    
    /**
     * 峰值活跃请求数
     *
     * @var int
     */
    protected $peakActiveRequests = 0;
    
    /**
     * 请求时间戳记录
     *
     * @var array
     */
    protected $requestTimestamps = [];
    
    /**
     * 最后请求时间
     *
     * @var float|null
     */
    protected $lastRequestTime = null;
    
    /**
     * 请求处理时间总和（微秒）
     *
     * @var float
     */
    protected $totalRequestTime = 0.0;
    
    /**
     * 最慢请求处理时间（微秒）
     *
     * @var float
     */
    protected $slowestRequestTime = 0.0;
    
    /**
     * 最慢请求的URI
     *
     * @var string
     */
    protected $slowestRequestUri = '';
    
    /**
     * 服务器启动时间
     *
     * @var int
     */
    protected $startTime = 0;
    
    /**
     * 构造函数
     *
     * @param App $app
     * @param array $config
     */
    public function __construct($app, array $config = [])
    {
        $this->app = $app;
        parent::__construct($app, array_merge($this->getDefaultConfig(), $config));
        
        // 初始化请求统计
        $this->resetRequestStats();
    }

    /**
     * 获取默认配置
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        // 如果 $this->app 未初始化，返回不依赖 $this->app 的默认配置
        if (!isset($this->app)) {
            return [
                'host' => '0.0.0.0',
                'port' => 8000,
                'workers' => 4,
                'debug' => false,
                'daemonize' => false,
                'ssl' => [
                    'enabled' => false,
                    'cert_file' => '',
                    'key_file' => '',
                    'verify_peer' => false,
                ],
                'max_request' => 10000,
                'max_package_size' => 10 * 1024 * 1024,
                'enable_static_handler' => true,
                'document_root' => '',
                'enable_coroutine' => true,
                'max_coroutine' => 100000,
                'log_file' => 'runtime/ripple.log',
                'pid_file' => 'runtime/ripple.pid',
            ];
        }

        // 如果 $this->app 已初始化，返回完整的默认配置
        return [
            'host' => '0.0.0.0',
            'port' => 8000,
            'workers' => 4,
            'debug' => false,
            'daemonize' => false,
            'ssl' => [
                'enabled' => false,
                'cert_file' => '',
                'key_file' => '',
                'verify_peer' => false,
            ],
            'max_request' => 10000,
            'max_package_size' => 10 * 1024 * 1024,
            'enable_static_handler' => true,
            'document_root' => $this->app->getRootPath() . 'public',
            'enable_coroutine' => true,
            'max_coroutine' => 100000,
            'log_file' => $this->app->getRuntimePath() . 'ripple.log',
            'pid_file' => $this->app->getRuntimePath() . 'ripple.pid',
        ];
    }

    /**
     * 检查运行时是否可用
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return class_exists(RippleWorker::class) && class_exists(Kernel::class);
    }
    
    /**
     * 检查适配器是否支持当前环境
     *
     * @return bool
     */
    public function isSupported(): bool
    {
        return $this->isAvailable();
    }
    
    /**
     * 获取适配器优先级
     * 数值越大优先级越高
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 80; // 比默认的 Swoole 低一些
    }
    
    /**
     * 初始化适配器
     * 
     * @return void
     */
    public function boot(): void
    {
        // 初始化操作
        $this->init();
    }
    
    /**
     * 运行适配器
     * 
     * @return void
     */
    public function run(): void
    {
        $this->start();
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
     * 处理 PSR-7 请求
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        // 将 PSR-7 请求转换为 Ripple 请求
        $rippleRequest = $this->createRippleRequest($request);
        
        // 处理 Ripple 请求并获取响应
        $rippleResponse = $this->handleRippleRequest($rippleRequest);
        
        // 将 Ripple 响应转换为 PSR-7 响应
        return $this->createPsr7Response($rippleResponse);
    }
    
    /**
     * 创建 Ripple 请求
     *
     * @param ServerRequestInterface $psrRequest
     * @return \Ripple\Http\Server\Request
     * @throws RuntimeException 当创建套接字失败时抛出异常
     */
    protected function createRippleRequest(ServerRequestInterface $psrRequest): \Ripple\Http\Server\Request
    {
        // 获取服务器参数
        $server = $psrRequest->getServerParams();
        
        // 获取查询参数
        $query = $psrRequest->getQueryParams();
        
        // 获取请求体参数
        $post = $psrRequest->getParsedBody() ?? [];
        
        // 获取 Cookie
        $cookies = $psrRequest->getCookieParams();
        
        // 获取上传文件
        $files = $psrRequest->getUploadedFiles();
        
        // 转换 PSR-7 上传文件为 Ripple 格式
        $rippleFiles = [];
        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFileInterface) {
                $rippleFiles[$key] = [
                    'name' => $file->getClientFilename(),
                    'type' => $file->getClientMediaType(),
                    'tmp_name' => $file->getStream()->getMetadata('uri'),
                    'error' => $file->getError(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        // 创建 Ripple 请求
        // 使用 Ripple\Socket::server 创建一个临时的服务器套接字
        $scheme = $this->config['ssl']['enabled'] ? 'tls' : 'tcp';
        $address = $scheme . '://' . $this->config['host'] . ':' . $this->config['port'];
        
        // 创建临时套接字
        $socket = Socket::server($address);
        if ($socket === false) {
            throw new RuntimeException('Failed to create Ripple socket');
        }
        
        // 准备 SERVER 变量
        $server = array_merge($server, [
            'REQUEST_METHOD' => $psrRequest->getMethod(),
            'REQUEST_URI' => (string) $psrRequest->getUri(),
            'SERVER_PROTOCOL' => 'HTTP/' . $psrRequest->getProtocolVersion(),
            'HTTP_HOST' => $psrRequest->getUri()->getHost(),
            'SERVER_PORT' => $psrRequest->getUri()->getPort() ?: ($this->config['ssl']['enabled'] ? 443 : 80),
            'HTTPS' => $this->config['ssl']['enabled'] ? 'on' : 'off',
        ]);
        
        // 添加请求头到 SERVER 变量
        foreach ($psrRequest->getHeaders() as $name => $values) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = implode(', ',  $values);
        }
        
        // 创建 Ripple 请求对象
        return new \Ripple\Http\Server\Request(
            $socket,
            $query, // GET 参数
            $post,  // POST 参数
            $cookies,
            $rippleFiles,
            $server,
            (string) $psrRequest->getBody()
        );
    }
    
    /**
     * 处理 Ripple 请求
     *
     * @param \Ripple\Http\Server\Request $request
     * @return \Ripple\Http\Server\Response
     */
    /**
     * 处理 Ripple 请求
     *
     * @param \Ripple\Http\Server\Request $request
     * @return \Ripple\Http\Server\Response
     */
    /**
     * 处理 Ripple 请求
     *
     * @param \Ripple\Http\Server\Request $request Ripple 请求对象
     * @return \Ripple\Http\Server\Response Ripple 响应对象
     * @throws \Throwable 处理请求时可能抛出的异常
     */
    protected function handleRippleRequest(\Ripple\Http\Server\Request $request): \Ripple\Http\Server\Response
    {
        $startTime = microtime(true);
        
        // 确保请求对象有效
        if (!$request) {
            throw new \InvalidArgumentException('Request object cannot be null');
        }
        
        $response = $request->getResponse();
        if (!$response) {
            throw new \RuntimeException('Failed to get response object from request');
        }
        
        // 初始化请求统计
        $this->requestCount = ($this->requestCount ?? 0) + 1;
        $this->activeRequests = ($this->activeRequests ?? 0) + 1;
        
        // 更新峰值活跃请求数
        if ($this->activeRequests > ($this->peakActiveRequests ?? 0)) {
            $this->peakActiveRequests = $this->activeRequests;
        }
        
        // 记录请求开始时间
        $this->lastRequestTime = $startTime;
        $this->requestTimestamps[] = $startTime;
        
        // 检查内存使用情况，如果内存使用率超过60%则尝试清理（降低阈值）
        $memoryInfo = $this->checkMemoryUsage();
        if (isset($memoryInfo['usage_percent'])) {
            $usagePercent = (float)rtrim($memoryInfo['usage_percent'], '%');
            if ($usagePercent > 60) { // 从80%降低到60%
                $this->logWarning('High memory usage detected before processing request', [
                    'usage_percent' => $memoryInfo['usage_percent'],
                    'current_usage' => $memoryInfo['current_usage'],
                    'memory_limit' => $memoryInfo['memory_limit']
                ]);

                // 主动触发垃圾回收
                gc_collect_cycles();
            }
        }
        
        // 清理过期的请求时间戳（保留最近10分钟的记录，减少内存占用）
        $tenMinutesAgo = $startTime - 600; // 从1小时改为10分钟
        $this->requestTimestamps = array_filter($this->requestTimestamps, function($timestamp) use ($tenMinutesAgo) {
            return $timestamp > $tenMinutesAgo;
        });

        // 限制时间戳数组大小，防止无限增长
        if (count($this->requestTimestamps) > 1000) {
            $this->requestTimestamps = array_slice($this->requestTimestamps, -500); // 只保留最近500个
        }
        sort($this->requestTimestamps);

        // 定期强制垃圾回收
        if ($this->requestCount % 100 === 0) {
            gc_collect_cycles();
        }
        
        // 获取请求信息
        $requestUri = $request->getRequestUri() ?? '/';
        $requestMethod = $request->getMethod() ?? 'GET';
        $clientIp = $request->getServerParam('remote_addr') ?? 'unknown';
        $requestId = uniqid('req_', true);
        
        // 设置请求ID到请求头
        $request->withHeader('X-Request-ID', $requestId);
        
        // 记录请求开始日志
        $this->logInfo('Request started', [
            'request_id' => $requestId,
            'method' => $requestMethod,
            'uri' => $requestUri,
            'ip' => $clientIp,
            'time' => date('Y-m-d H:i:s')
        ]);
        
        try {
            // 记录请求开始时的内存使用情况
            $startMemory = memory_get_usage(true);
            $startMemoryPeak = memory_get_peak_usage(true);
            
            // 将 Ripple 请求转换为 ThinkPHP 请求
            $thinkRequest = $this->createThinkRequest($request);
            
            // 处理请求并获取响应
            $thinkResponse = $this->app->http->run($thinkRequest);
            
            // 计算处理时间和内存使用
            $endTime = microtime(true);
            $processTimeMs = round(($endTime - $startTime) * 1000, 2); // 毫秒
            $processTimeSec = $endTime - $startTime; // 秒
            $endMemory = memory_get_usage(true);
            $endMemoryPeak = memory_get_peak_usage(true);
            $memoryUsed = $endMemory - $startMemory;
            
            // 更新性能指标
            $this->totalRequestTime += $processTimeSec;
            
            // 更新最慢请求记录
            if ($processTimeSec > $this->slowestRequestTime) {
                $this->slowestRequestTime = $processTimeSec;
                $this->slowestRequestUri = $requestUri;
            }
            
            // 记录内存使用情况
            $memoryUsage = [
                'start' => $this->formatBytes($startMemory),
                'end' => $this->formatBytes($endMemory),
                'peak' => $this->formatBytes($endMemoryPeak),
                'used' => $this->formatBytes($memoryUsed),
                'used_bytes' => $memoryUsed,
                'peak_bytes' => $endMemoryPeak
            ];
            
            // 记录请求完成日志
            $statusCode = $thinkResponse->getCode() ?? 200;
            $logData = [
                'request_id' => $requestId,
                'method' => $requestMethod,
                'uri' => $requestUri,
                'status' => $statusCode,
                'time_taken' => $processTimeMs . 'ms',
                'time_taken_sec' => round($processTimeSec, 4),
                'memory' => $memoryUsage,
                'active_requests' => $this->activeRequests - 1,
                'total_requests' => $this->requestCount,
                'client_ip' => $clientIp,
                'user_agent' => $request->getHeader('user-agent')[0] ?? null,
                'referer' => $request->getHeader('referer')[0] ?? null,
                'peak_memory_ratio' => round(($memoryUsage['peak_bytes'] / $this->getMemoryLimitInBytes()) * 100, 2) . '%'
            ];
            
            // 记录内存使用率警告
            $memoryLimit = $this->getMemoryLimitInBytes();
            $memoryPeakRatio = $memoryUsage['peak_bytes'] / $memoryLimit * 100;
            
            if ($memoryPeakRatio > 70) { // 从90%降低到70%
                $this->logWarning('High memory usage after request processing', [
                    'request_id' => $requestId,
                    'peak_memory' => $memoryUsage['peak'],
                    'memory_limit' => $this->formatBytes($memoryLimit),
                    'peak_memory_ratio' => round($memoryPeakRatio, 2) . '%',
                    'uri' => $requestUri,
                    'time_taken' => $processTimeMs . 'ms'
                ]);

                // 如果内存使用率超过70%，触发内存清理
                $this->checkMemoryUsage(true);

                // 强制垃圾回收
                gc_collect_cycles();
            }
            
            $this->logInfo('Request completed', $logData);
            
            // 添加性能监控头
            $thinkResponse->header([
                'X-Request-ID' => $requestId,
                'X-Process-Time' => $processTimeMs . 'ms',
                'X-Memory-Start' => $memoryUsage['start'],
                'X-Memory-End' => $memoryUsage['end'],
                'X-Memory-Peak' => $memoryUsage['peak'],
                'X-Memory-Used' => $memoryUsage['used'],
                'X-Memory-Used-Bytes' => $memoryUsage['used_bytes'],
                'X-Memory-Peak-Ratio' => round($memoryPeakRatio, 2) . '%',
                'X-Request-Count' => $this->requestCount,
                'X-Active-Requests' => $this->activeRequests - 1,
                'X-Request-Start' => sprintf('t=%f %s', $startTime, date('Y-m-d H:i:s', (int)$startTime)),
                'X-Request-End' => sprintf('t=%f %s', $endTime, date('Y-m-d H:i:s', (int)$endTime)),
            ]);
            
            // 减少活跃请求计数
            $this->activeRequests = max(0, ($this->activeRequests ?? 1) - 1);
            
            // 将 ThinkPHP 响应转换为 Ripple 响应并返回
            return $this->createRippleResponse($response, $thinkResponse);
            
        } catch (Throwable $e) {
            // 计算处理时间和内存使用
            $endTime = microtime(true);
            $processTimeMs = round(($endTime - $startTime) * 1000, 2); // 毫秒
            $processTimeSec = $endTime - $startTime; // 秒
            $endMemory = memory_get_usage(true);
            $endMemoryPeak = memory_get_peak_usage(true);
            $memoryUsed = $endMemory - ($startMemory ?? 0);
            
            // 减少活跃请求计数
            $this->activeRequests = max(0, ($this->activeRequests ?? 1) - 1);
            
            // 记录内存使用情况
            $memoryUsage = [
                'start' => isset($startMemory) ? $this->formatBytes($startMemory) : 'N/A',
                'end' => $this->formatBytes($endMemory),
                'peak' => $this->formatBytes($endMemoryPeak),
                'used' => $this->formatBytes($memoryUsed),
                'used_bytes' => $memoryUsed,
                'peak_bytes' => $endMemoryPeak
            ];
            
            // 准备错误详情
            $errorDetails = [
                'request_id' => $requestId,
                'method' => $requestMethod,
                'uri' => $requestUri,
                'client_ip' => $clientIp,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $this->config['debug'] ?? false ? $e->getTraceAsString() : null
                ],
                'time_taken' => $processTimeMs . 'ms',
                'time_taken_sec' => round($processTimeSec, 4),
                'memory' => $memoryUsage,
                'active_requests' => $this->activeRequests,
                'total_requests' => $this->requestCount,
                'user_agent' => $request->getHeader('user-agent')[0] ?? null,
                'referer' => $request->getHeader('referer')[0] ?? null,
                'peak_memory_ratio' => $this->formatBytes($endMemoryPeak) . ' / ' . $this->formatBytes($this->getMemoryLimitInBytes()) . 
                                      ' (' . round(($endMemoryPeak / $this->getMemoryLimitInBytes()) * 100, 2) . '%)'
            ];
            
            // 记录错误日志
            $this->logError(sprintf(
                'Request failed: %s %s - %s (took %.2fms, memory: %s peak)',
                $requestMethod,
                $requestUri,
                $e->getMessage(),
                $processTimeMs,
                $memoryUsage['peak']
            ), $errorDetails);
            
            // 准备错误响应
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            $errorResponse = [
                'error' => [
                    'code' => $statusCode,
                    'message' => $e->getMessage(),
                    'request_id' => $requestId,
                    'timestamp' => date('c')
                ]
            ];
            
            // 调试模式下添加更多错误详情
            if ($this->config['debug'] ?? false) {
                $errorResponse['error']['file'] = $e->getFile();
                $errorResponse['error']['line'] = $e->getLine();
                $errorResponse['error']['trace'] = explode("\n", $e->getTraceAsString());
            }
            
            try {
                // 设置响应头和状态码
                $response->setStatusCode($statusCode);
                $response->withHeader('Content-Type', 'application/json');
                $response->withHeader('X-Request-ID', $requestId);
                $response->withHeader('X-Process-Time', $processTimeMs . 'ms');
                $response->withHeader('X-Memory-Peak', $memoryUsage['peak']);
                $response->withHeader('X-Memory-Used', $memoryUsage['used']);
                $response->withHeader('X-Error-Message', $e->getMessage());
                $response->setContent(json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                
                // 记录响应
                $this->logInfo('Request error response', [
                    'request_id' => $requestId,
                    'status' => $statusCode,
                    'time_taken' => $processTimeMs . 'ms',
                    'memory_peak' => $memoryUsage['peak'],
                    'error' => $e->getMessage()
                ]);
                
                // 如果内存使用率超过90%，触发内存清理
                $memoryLimit = $this->getMemoryLimitInBytes();
                $memoryPeakRatio = $endMemoryPeak / $memoryLimit * 100;
                if ($memoryPeakRatio > 90) {
                    $this->logWarning('High memory usage after error handling - triggering cleanup', [
                        'request_id' => $requestId,
                        'peak_memory' => $memoryUsage['peak'],
                        'memory_limit' => $this->formatBytes($memoryLimit),
                        'peak_memory_ratio' => round($memoryPeakRatio, 2) . '%'
                    ]);
                    $this->checkMemoryUsage(true);
                }
                
                return $response;
            } catch (Throwable $responseError) {
                // 如果响应处理过程中发生错误，记录并重新抛出
                $this->logError('Failed to send error response', [
                    'request_id' => $requestId,
                    'original_error' => $e->getMessage(),
                    'response_error' => $responseError->getMessage(),
                    'response_error_trace' => $responseError->getTraceAsString()
                ]);
                
                // 重新包装并抛出异常
                throw new \RuntimeException(
                    sprintf('Error processing request: %s (Failed to send error response: %s)', 
                           $e->getMessage(), 
                           $responseError->getMessage()),
                    $e->getCode(),
                    $e
                );
            }
        }
    }
    /**
     * 创建 PSR-7 响应
     *
     * @param \Ripple\Http\Server\Response $rippleResponse
     * @return ResponseInterface
     */
    protected function createPsr7Response(\Ripple\Http\Server\Response $rippleResponse): ResponseInterface
    {
        // 获取响应头
        $headers = [];
        if (method_exists($rippleResponse, 'getHeader')) {
            $headerData = $rippleResponse->getHeader();
            if (is_array($headerData)) {
                $headers = $headerData;
            }
        }
        
        // 获取响应体
        $body = '';
        if (method_exists($rippleResponse, 'getBody')) {
            $body = $rippleResponse->getBody();
        }
        
        // 创建 PSR-7 响应
        return new \GuzzleHttp\Psr7\Response(
            $rippleResponse->getStatusCode(),
            $headers,
            $body
        );
    }

    /**
     * 获取总请求数
     *
     * @return int 总请求数
     */
    public function getRequestCount(): int
    {
        return $this->requestCount ?? 0;
    }
    
    /**
     * 获取当前活跃请求数
     *
     * @return int 活跃请求数
     */
    public function getActiveRequests(): int
    {
        return $this->activeRequests ?? 0;
    }
    
    /**
     * 获取最后一分钟的请求数
     * 
     * @return int 最后一分钟的请求数
     */
    protected function getLastMinuteRequestCount(): int
    {
        // 简单实现：返回总请求数的1/60，实际项目中应该使用时间窗口统计
        // 这里仅作为示例，实际项目中应该使用更精确的统计方法
        $runTimeMinutes = $this->startTime ? (time() - $this->startTime) / 60 : 0;
        if ($runTimeMinutes <= 0) {
            return $this->requestCount ?? 0;
        }
        
        return (int) round(($this->requestCount ?? 0) / $runTimeMinutes);
    }
    
    /**
     * 获取最近N分钟的请求数
     * 
     * @param int $minutes 分钟数
     * @return int 最近N分钟的请求数
     */
    protected function getLastMinutesRequestCount(int $minutes): int
    {
        // 简单实现：返回总请求数除以运行时间的比例，实际项目应该使用时间窗口统计
        // 这里仅作为示例，实际项目中应该使用更精确的统计方法
        $runTimeMinutes = $this->startTime ? (time() - $this->startTime) / 60 : 0;
        if ($runTimeMinutes <= 0) {
            return $this->requestCount ?? 0;
        }
        
        $factor = min($minutes / $runTimeMinutes, 1);
        return (int) round(($this->requestCount ?? 0) * $factor);
    }
    
    /**
     * 重置请求统计信息
     *
     * 重置所有请求计数器、活跃请求数以及相关的性能指标
     * 
     * @return void
     */
    public function resetRequestStats(): void
    {
        $this->requestCount = 0;
        $this->activeRequests = 0;
        
        // 重置请求时间记录
        if (isset($this->requestTimestamps)) {
            $this->requestTimestamps = [];
        }
        
        // 重置性能指标
        $this->lastRequestTime = null;
        $this->peakActiveRequests = 0;
        
        $this->logInfo('请求统计信息已重置', [
            'reset_time' => date('Y-m-d H:i:s'),
            'pid' => getmypid()
        ]);
    }
    
    /**
     * 获取最近N秒内的请求数
     *
     * @param int $seconds 秒数
     * @return int 请求数
     */
    public function getRequestsInLastSeconds(int $seconds = 60): int
    {
        if (empty($this->requestTimestamps)) {
            return 0;
        }
        
        $now = microtime(true);
        $cutoff = $now - $seconds;
        
        // 使用二分查找快速定位时间窗口
        $left = 0;
        $right = count($this->requestTimestamps) - 1;
        $firstIndex = $right + 1; // 初始化为数组长度，表示没有找到
        
        while ($left <= $right) {
            $mid = (int)(($left + $right) / 2);
            if ($this->requestTimestamps[$mid] >= $cutoff) {
                $firstIndex = $mid;
                $right = $mid - 1;
            } else {
                $left = $mid + 1;
            }
        }
        
        return count($this->requestTimestamps) - $firstIndex;
    }
    
    /**
     * 获取请求频率（请求数/秒）
     * 
     * @param int $timeWindow 时间窗口（秒）
     * @return float 请求频率
     */
    public function getRequestRate(int $timeWindow = 60): float
    {
        $requestCount = $this->getRequestsInLastSeconds($timeWindow);
        return $timeWindow > 0 ? $requestCount / $timeWindow : 0;
    }
    
    /**
     * 获取平均请求处理时间（秒）
     * 
     * @return float 平均处理时间（秒）
     */
    public function getAverageRequestTime(): float
    {
        return $this->requestCount > 0 
            ? $this->totalRequestTime / $this->requestCount 
            : 0;
    }
    
    /**
     * 获取最慢的请求信息
     * 
     * @return array 包含处理时间和URI的数组
     */
    public function getSlowestRequest(): array
    {
        return [
            'time' => $this->slowestRequestTime,
            'uri' => $this->slowestRequestUri,
            'formatted_time' => $this->formatSeconds($this->slowestRequestTime, 4) . 's'
        ];
    }
    
    /**
     * 获取当前请求统计信息
     *
     * 返回包含各种请求统计信息的关联数组，可用于监控和分析
     * 
     * @return array 包含请求统计信息的数组
     */
    public function getRequestStats(): array
    {
        return [
            'total_requests' => $this->requestCount,
            'active_requests' => $this->activeRequests,
            'peak_active_requests' => $this->peakActiveRequests,
            'requests_last_minute' => $this->getRequestsInLastSeconds(60),
            'requests_last_5_minutes' => $this->getRequestsInLastSeconds(300),
            'requests_last_15_minutes' => $this->getRequestsInLastSeconds(900),
            'request_rate_1m' => $this->getRequestRate(60),
            'request_rate_5m' => $this->getRequestRate(300),
            'request_rate_15m' => $this->getRequestRate(900),
            'average_request_time' => $this->getAverageRequestTime(),
            'total_request_time' => $this->totalRequestTime,
            'slowest_request' => [
                'time' => $this->slowestRequestTime,
                'uri' => $this->slowestRequestUri,
                'formatted_time' => $this->formatSeconds($this->slowestRequestTime, 4) . 's'
            ],
            'last_request_time' => $this->lastRequestTime,
            'last_request_formatted' => $this->lastRequestTime ? date('Y-m-d H:i:s', (int)$this->lastRequestTime) : null,
            'timestamps_count' => is_array($this->requestTimestamps) ? count($this->requestTimestamps) : 0,
            'timestamps_window' => '1 hour' // 时间戳窗口为1小时
        ];
    }
    
    /**
     * 清理过期的请求时间戳
     * 
     * 此方法会移除所有早于指定时间戳的请求时间戳记录
     * 通常在长时间运行的进程中定期调用，以防止内存泄漏
     * 
     * @param int $maxAge 最大保留时间（秒），默认为1小时
     * @param bool $force 是否强制清理，即使时间戳未过期
     * @return int 被移除的时间戳数量
     */
    public function cleanupExpiredRequestTimestamps(int $maxAge = 3600, bool $force = false): int
    {
        if (empty($this->requestTimestamps) || !is_array($this->requestTimestamps)) {
            return 0;
        }
        
        $countBefore = count($this->requestTimestamps);
        if ($force) {
            // 强制清理，保留最近N个时间戳（例如最近1000个）
            $keepCount = 1000;
            if (count($this->requestTimestamps) > $keepCount) {
                $this->requestTimestamps = array_slice(
                    $this->requestTimestamps, 
                    -$keepCount, 
                    $keepCount, 
                    true
                );
                $removed = $countBefore - count($this->requestTimestamps);
                $this->logInfo(sprintf(
                    'Forced cleanup: kept last %d request timestamps, removed %d',
                    $keepCount,
                    $removed
                ));
                return $removed;
            }
            return 0;
        } else {
            // 正常清理：移除超过最大年龄的时间戳
            $cutoffTime = microtime(true) - $maxAge;
            
            // 使用 array_filter 过滤出大于 cutoffTime 的时间戳
            $this->requestTimestamps = array_values(array_filter(
                $this->requestTimestamps,
                function($timestamp) use ($cutoffTime) {
                    return $timestamp > $cutoffTime;
                }
            ));
        }
        
        $removed = $countBefore - count($this->requestTimestamps);
        
        if ($removed > 0) {
            $this->logDebug(sprintf(
                'Cleaned up %d expired request timestamps (older than %d seconds)',
                $removed,
                $maxAge
            ));
        }
        
        return $removed;
    }
    
    /**
     * 获取运行时名称
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ripple';
    }

    /**
     * 获取运行时配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 启动运行时
     *
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->config = array_merge($this->config, $options);
        $this->init();
        
        // 确保 worker 被正确初始化
        if ($this->worker) {
            // 确保 manager 已初始化
            if (!$this->manager) {
                $this->manager = new RippleManager();
            }
            
            // 调用 register 方法初始化 server 属性
            if (method_exists($this->worker, 'register') && $this->manager) {
                $this->worker->register($this->manager);
            }
            
            // 绑定请求处理器
            $this->bindRequestHandler();
            
            // 启动服务器
            $this->startServer();
            
            // 标记为正在运行
            $this->isRunning = true;
        } else {
            throw new RuntimeException('Failed to initialize Ripple worker');
        }
    }

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void
    {
        if (!$this->isRunning) {
            $this->logInfo('Server is not running');
            return;
        }
        
        if (!$this->worker) {
            $this->logError('Worker is not initialized');
            return;
        }
        
        $this->logInfo('Stopping Ripple server...');
        
        try {
            // 标记为停止中
            $this->isRunning = false;
            
            // 停止 manager
            if ($this->manager) {
                $this->logDebug('Terminating manager...');
                
                try {
                    if (method_exists($this->manager, 'terminate')) {
                        $this->manager->terminate();
                    } elseif (method_exists($this->manager, 'stop')) {
                        $this->manager->stop();
                    } elseif (method_exists($this->manager, 'shutdown')) {
                        $this->manager->shutdown();
                    } else {
                        $this->logWarning('No suitable stop method found on manager');
                    }
                    
                    $this->logDebug('Manager terminated');
                } catch (Throwable $e) {
                    $this->logError('Error while terminating manager: ' . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // 不再抛出异常，记录错误后继续执行
                }
                
                $this->manager = null;
            }
            
            // 停止 worker
            if ($this->worker) {
                $this->logDebug('Stopping worker...');
                
                try {
                    if (method_exists($this->worker, 'stop')) {
                        $this->worker->stop();
                    } elseif (method_exists($this->worker, 'shutdown')) {
                        $this->worker->shutdown();
                    } elseif (method_exists($this->worker, 'close')) {
                        $this->worker->close();
                    }
                    
                    $this->logDebug('Worker stopped');
                } catch (Throwable $e) {
                    $this->logError('Error while stopping worker: ' . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // 不再抛出异常，记录错误后继续执行
                }
                
                $this->worker = null;
            }
            
            // 记录服务器停止时间和运行时长
            $stopTime = time();
            $uptime = $this->startTime ? $stopTime - $this->startTime : 0;
            
            $this->logInfo('Ripple server stopped successfully', [
                'stop_time' => date('Y-m-d H:i:s', $stopTime),
                'start_time' => $this->startTime ? date('Y-m-d H:i:s', $this->startTime) : null,
                'uptime_seconds' => $uptime,
                'uptime_human' => $this->formatSeconds($uptime),
                'total_requests' => $this->requestCount ?? 0,
                'active_requests' => $this->activeRequests ?? 0
            ]);
            
            // 重置启动时间
            $this->startTime = 0;
            
            // 重置请求统计信息
            $this->resetRequestStats();
            
            return;
            
        } catch (Throwable $e) {
            $this->logError('Failed to stop Ripple server: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 无论如何都尝试强制停止服务器
            $this->logWarning('Forcing server stop despite errors');
            $this->isRunning = false;
            $this->manager = null;
            $this->worker = null;
            return;
            
            return;
        }
    }

    /**
     * 获取服务器状态信息
     *
     * @return array 服务器状态信息
     */
    /**
     * 获取服务器状态信息
     *
     * 返回包含服务器运行状态、系统信息、请求统计等详细信息的数组
     * 
     * @return array 包含服务器状态信息的关联数组
     */
    public function getStatus(): array
    {
        $startTime = $this->startTime ?? null;
        $currentTime = time();
        $runTime = $startTime ? $currentTime - $startTime : 0;
        
        $status = [
            'server' => [
                'running' => $this->isRunning,
                'start_time' => $startTime,
                'start_time_formatted' => $startTime ? date('Y-m-d H:i:s', $startTime) : null,
                'run_time' => $runTime,
                'run_time_formatted' => $this->formatSeconds($runTime),
                'host' => $this->config['host'] ?? '0.0.0.0',
                'port' => $this->config['port'] ?? 8000,
                'workers' => $this->config['worker_num'] ?? 4,
                'daemonize' => $this->config['daemonize'] ?? false,
                'debug' => $this->config['debug'] ?? false,
                'max_request' => $this->config['max_request'] ?? 0,
                'max_request_grace' => $this->config['max_request_grace'] ?? 0,
                'reload_async' => $this->config['reload_async'] ?? true,
                'pid_file' => $this->config['pid_file'] ?? null,
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'swoole_version' => extension_loaded('swoole') ? phpversion('swoole') : null,
                'os' => PHP_OS . ' ' . php_uname('r'),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
                'timezone' => date_default_timezone_get(),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                'memory_usage_real' => round(memory_get_usage(false) / 1024 / 1024, 2) . 'MB',
                'memory_peak_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
                'memory_limit' => ini_get('memory_limit'),
                'cpu_cores' => function_exists('swoole_cpu_num') ? swoole_cpu_num() : null,
                'load_avg' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
                'disk_free_space' => function_exists('disk_free_space') ? 
                    round(disk_free_space('/') / 1024 / 1024 / 1024, 2) . 'GB' : null,
                'disk_total_space' => function_exists('disk_total_space') ? 
                    round(disk_total_space('/') / 1024 / 1024 / 1024, 2) . 'GB' : null,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time') . 's',
                'process_id' => getmypid(),
                'user' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown',
            ],
            'manager' => [
                'initialized' => $this->manager !== null,
                'class' => $this->manager ? get_class($this->manager) : null,
                'worker_count' => $this->manager && method_exists($this->manager, 'getWorkers') 
                    ? count($this->manager->getWorkers()) 
                    : null,
            ],
            'worker' => [
                'initialized' => $this->worker !== null,
                'class' => $this->worker ? get_class($this->worker) : null,
                'registered' => $this->worker ? $this->isWorkerRegistered() : false,
            ],
            'requests' => array_merge([
                // 保持向后兼容的字段
                'total' => $this->requestCount ?? 0,
                'active' => $this->activeRequests ?? 0,
                'average_per_second' => $runTime > 0 ? 
                    round(($this->requestCount ?? 0) / $runTime, 2) : 0,
                'last_minute' => $this->getLastMinuteRequestCount(),
                'last_5_minutes' => $this->getLastMinutesRequestCount(5),
                'last_15_minutes' => $this->getLastMinutesRequestCount(15),
                // 添加详细统计信息
                'stats' => $this->getRequestStats()
            ], $this->getRequestStats()),
            'timing' => [
                'start_time' => $startTime,
                'current_time' => $currentTime,
                'uptime' => $runTime,
                'uptime_formatted' => $this->formatSeconds($runTime),
                'datetime' => date('Y-m-d H:i:s'),
                'timestamp' => $currentTime,
            ],
            'extensions' => [
                'swoole' => extension_loaded('swoole'),
                'pcntl' => extension_loaded('pcntl'),
                'posix' => extension_loaded('posix'),
                'pdo' => extension_loaded('pdo'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring'),
                'openssl' => extension_loaded('openssl'),
                'redis' => extension_loaded('redis'),
            ],
        ];
        
        // 添加更多自定义状态信息
        if ($this->manager && method_exists($this->manager, 'getStatus')) {
            $status['manager_status'] = $this->manager->getStatus();
        }
        
        if ($this->worker && method_exists($this->worker, 'getStatus')) {
            $status['worker_status'] = $this->worker->getStatus();
        }
        
        return $status;
    }
    
    /**
     * 获取格式化的状态信息（用于显示）
     *
     * @param bool $asJson 是否返回JSON格式
     * @return string|array 格式化的状态信息
     */
    /**
     * 获取格式化的服务器状态信息
     *
     * 返回易于阅读的服务器状态信息，可选择JSON或文本格式
     * 
     * @param bool $asJson 是否返回JSON格式
     * @return string 格式化的状态信息
     */
    public function getFormattedStatus(bool $asJson = false): string
    {
        $status = $this->getStatus();
        
        if ($asJson) {
            return json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        // 构建格式化的文本输出
        $output = [
            '╔══════════════════════════════════════════════════╗',
            '║             服务器状态信息                       ║',
            '╠══════════════════════════════════════════════════╣',
            sprintf('║  %-18s %s', '运行状态:', $status['server']['running'] ? '✅ 运行中' : '❌ 已停止'),
            sprintf('║  %-18s %s', '启动时间:', $status['server']['start_time_formatted'] ?? 'N/A'),
            sprintf('║  %-18s %s', '运行时长:', $status['server']['run_time_formatted']),
            sprintf('║  %-18s %s', '监听地址:', sprintf('%s:%d', 
                $status['server']['host'], 
                $status['server']['port']
            )),
            sprintf('║  %-18s %s', '工作进程:', $status['server']['workers']),
            sprintf('║  %-18s %s', '守护进程:', $status['server']['daemonize'] ? '是' : '否'),
            '╠══════════════════════════════════════════════════╣',
            sprintf('║  %-18s %s', 'PHP 版本:', $status['system']['php_version']),
            sprintf('║  %-18s %s', 'Swoole 版本:', $status['system']['swoole_version'] ?? '未安装'),
            sprintf('║  %-18s %s', '操作系统:', $status['system']['os']),
            sprintf('║  %-18s %s', '运行用户:', $status['system']['user']),
            sprintf('║  %-18s %s', '进程ID:', $status['system']['process_id']),
            sprintf('║  %-18s %s', 'CPU 核心:', $status['system']['cpu_cores'] ?? '未知'),
            '╠══════════════════════════════════════════════════╣',
            sprintf('║  %-18s %s', '内存使用:', $status['system']['memory_usage']),
            sprintf('║  %-18s %s', '内存峰值:', $status['system']['memory_peak_usage']),
            sprintf('║  %-18s %s', '内存限制:', $status['system']['memory_limit']),
            sprintf('║  %-18s %s', '磁盘空间:', sprintf('可用 %s / 共 %s', 
                $status['system']['disk_free_space'] ?? '未知',
                $status['system']['disk_total_space'] ?? '未知'
            )),
            '╠══════════════════════════════════════════════════╣',
            sprintf('║  %-18s %s', '总请求数:', number_format($status['requests']['total_requests'])),
            sprintf('║  %-18s %s / %s (当前/峰值)', '活跃请求:', 
                $status['requests']['active_requests'], 
                $status['requests']['peak_active_requests']
            ),
            sprintf('║  %-18s %.2f (1m: %.2f, 5m: %.2f, 15m: %.2f)', 'QPS:', 
                $status['requests']['average_per_second'],
                $status['requests']['request_rate_1m'],
                $status['requests']['request_rate_5m'],
                $status['requests']['request_rate_15m']
            ),
            sprintf('║  %-18s %d (1m: %d, 5m: %d, 15m: %d)', '请求统计:', 
                $status['requests']['requests_last_minute'],
                $status['requests']['requests_last_minute'],
                $status['requests']['requests_last_5_minutes'],
                $status['requests']['requests_last_15_minutes']
            ),
            sprintf('║  %-18s %.2f ms (总计: %.2fs)', '平均请求时间:', 
                $status['requests']['average_request_time'] * 1000,
                $status['requests']['total_request_time']
            ),
            sprintf('║  %-18s %s', '最慢请求:', 
                (strlen($status['requests']['slowest_request']['uri']) > 30 ? 
                    '...' . substr($status['requests']['slowest_request']['uri'], -27) : 
                    $status['requests']['slowest_request']['uri']) . 
                ' (' . $status['requests']['slowest_request']['formatted_time'] . ')'
            ),
            '╠══════════════════════════════════════════════════╣',
            sprintf('║  %-18s %s', 'Manager:', $status['manager']['initialized'] ? '已初始化' : '未初始化'),
            sprintf('║  %-18s %s', 'Worker:', $status['worker']['initialized'] ? '已初始化' : '未初始化'),
            sprintf('║  %-18s %s', 'Worker 数量:', $status['manager']['worker_count'] ?? 0),
            '╠══════════════════════════════════════════════════╣',
            sprintf('║  %-18s %s', '当前时间:', $status['datetime']),
            '╚══════════════════════════════════════════════════╝',
        ];
        
        return implode("\n", $output);
    }
    
    /**
     * 格式化秒数为可读格式
     *
     * @param int $seconds 秒数
     * @return string 格式化后的时间
     */
    protected function formatSeconds(int $seconds): string
    {
        $units = [
            '天' => 86400,
            '小时' => 3600,
            '分钟' => 60,
            '秒' => 1
        ];
        
        $result = [];
        $remaining = $seconds;
        
        foreach ($units as $unit => $divisor) {
            if ($remaining < $divisor) continue;
            $value = floor($remaining / $divisor);
            $remaining = $remaining % $divisor;
            $result[] = $value . $unit;
        }
        
        return $result ? implode(' ', $result) : '0秒';
    }
    
    /**
     * 重载服务器配置
     *
     * @param bool $reloadConfig 是否重新加载配置文件
     * @param bool $graceful 是否优雅重载（平滑重启）
     * @return bool 是否成功重载
     */
    public function reload(bool $reloadConfig = true, bool $graceful = true): bool
    {
        if (!$this->isRunning) {
            $this->logWarning('Cannot reload: server is not running');
            return false;
        }
        
        if (!$this->manager) {
            $this->logError('Cannot reload: manager is not initialized');
            return false;
        }
        
        $this->logInfo('Reloading Ripple server...', [
            'reload_config' => $reloadConfig,
            'graceful' => $graceful
        ]);
        
        try {
            // 如果需要重新加载配置文件
            if ($reloadConfig && method_exists($this, 'loadConfig')) {
                $this->logDebug('Reloading configuration...');
                $this->loadConfig();
            }
            
            // 调用 manager 的 reload 方法
            if (method_exists($this->manager, 'reload')) {
                $this->logDebug('Calling manager reload method...');
                $this->manager->reload($graceful);
            } 
            // 如果 manager 没有 reload 方法，但有 workers 属性，则尝试重启所有 worker
            elseif (property_exists($this->manager, 'workers')) {
                $this->logDebug('Restarting workers...');
                $workers = $this->manager->workers;
                foreach ($workers as $worker) {
                    if (is_object($worker) && method_exists($worker, 'reload')) {
                        $worker->reload($graceful);
                    }
                }
            } else {
                $this->logWarning('No suitable reload method found on manager');
                return false;
            }
            
            // 记录重载统计信息
            $reloadTime = time();
            $uptime = $this->startTime ? $reloadTime - $this->startTime : 0;
            
            // 保存当前的请求统计信息
            $savedStats = [
                'requestCount' => $this->requestCount,
                'peakActiveRequests' => $this->peakActiveRequests,
                'requestTimestamps' => $this->requestTimestamps,
                'totalRequestTime' => $this->totalRequestTime,
                'slowestRequestTime' => $this->slowestRequestTime,
                'slowestRequestUri' => $this->slowestRequestUri,
                'lastRequestTime' => $this->lastRequestTime
            ];
            
            $this->logInfo('Ripple server reloaded successfully', [
                'reload_time' => date('Y-m-d H:i:s', $reloadTime),
                'start_time' => $this->startTime ? date('Y-m-d H:i:s', $this->startTime) : null,
                'uptime_before_reload' => $uptime,
                'uptime_human' => $this->formatSeconds($uptime),
                'total_requests' => $this->requestCount ?? 0,
                'active_requests' => $this->activeRequests ?? 0,
                'peak_active_requests' => $this->peakActiveRequests ?? 0,
                'recent_requests' => $this->getRequestsInLastSeconds(60) . ' (last 60s)',
                'request_rate' => round($this->getRequestRate(60), 2) . ' req/s (last 60s)',
                'avg_request_time' => $this->getAverageRequestTime() * 1000 . 'ms',
                'reload_config' => $reloadConfig,
                'graceful' => $graceful
            ]);
            
            // 恢复请求统计信息
            $this->requestCount = $savedStats['requestCount'];
            $this->peakActiveRequests = $savedStats['peakActiveRequests'];
            $this->requestTimestamps = $savedStats['requestTimestamps'];
            $this->totalRequestTime = $savedStats['totalRequestTime'];
            $this->slowestRequestTime = $savedStats['slowestRequestTime'];
            $this->slowestRequestUri = $savedStats['slowestRequestUri'];
            $this->lastRequestTime = $savedStats['lastRequestTime'];
            
            return true;
            
        } catch (Throwable $e) {
            $this->logError('Failed to reload Ripple server: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * 析构函数，确保资源正确释放
     */
    public function __destruct()
    {
        try {
            // 如果服务器仍在运行，尝试优雅停止
            if ($this->isRunning) {
                $this->logInfo('Shutting down Ripple server in destructor...');
                $this->stop(true); // 强制停止
            }
            
            // 清理其他资源
            $this->manager = null;
            $this->worker = null;
            
        } catch (Throwable $e) {
            // 记录错误但不要抛出异常，因为这是在析构函数中
            error_log(sprintf(
                'Error in RippleAdapter destructor: %s in %s:%d\n%s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * 初始化 Ripple Worker
     *
     * @return void
     */
    protected function init(): void
    {
        if ($this->worker === null) {
            $scheme = $this->config['ssl']['enabled'] ? 'https' : 'http';
            $address = "{$scheme}://{$this->config['host']}:{$this->config['port']}";
            
            // 初始化 worker
            $this->worker = new RippleWorker($address, $this->config['workers']);
            
            // 设置 worker 名称
            if (method_exists($this->worker, 'setName')) {
                $this->worker->setName('http-server');
            } else if (property_exists($this->worker, 'name')) {
                $this->worker->name = 'http-server';
            }
            
            // 设置 worker 数量
            $workerCount = $this->config['workers'] ?? 4;
            if (method_exists($this->worker, 'setCount')) {
                $this->worker->setCount($workerCount);
            } else if (property_exists($this->worker, 'count')) {
                $this->worker->count = $workerCount;
            }
            
            // 初始化 manager
            if ($this->manager === null) {
                $this->manager = new RippleManager();
            }
            
            // 注册 worker
            if (method_exists($this->manager, 'add')) {
                $this->manager->add('http', $this->worker);
            }
            
            // 确保 worker 已注册
            if (method_exists($this->worker, 'register') && $this->manager) {
                $this->worker->register($this->manager);
            }
        }
    }

    /**
     * 绑定请求处理器
     *
     * @return void
     */
    protected function bindRequestHandler(): void
    {
        if (!$this->worker) {
            throw new RuntimeException('Ripple worker is not initialized');
        }

        // 确保 worker 已经注册
        if (!method_exists($this->worker, 'register')) {
            throw new RuntimeException('Ripple worker does not have a register() method');
        }

        // 获取 worker 的 server 实例
        $reflection = new \ReflectionClass($this->worker);
        
        // 检查 server 属性是否存在
        if (!$reflection->hasProperty('server')) {
            throw new RuntimeException('Ripple worker does not have a server property');
        }
        
        // 获取 server 属性
        $serverProperty = $reflection->getProperty('server');
        $serverProperty->setAccessible(true);
        
        // 检查 server 属性是否已经初始化
        if (!$serverProperty->isInitialized($this->worker)) {
            // 如果 server 属性未初始化，尝试调用 register 方法
            if (method_exists($this->worker, 'register') && $this->manager) {
                try {
                    $this->worker->register($this->manager);
                } catch (Throwable $e) {
                    throw new RuntimeException('Failed to register Ripple worker: ' . $e->getMessage(), 0, $e);
                }
            } else {
                throw new RuntimeException('Cannot initialize Ripple worker server: register method or manager not available');
            }
        }
        
        // 获取 server 实例
        $server = $serverProperty->getValue($this->worker);
        
        if ($server === null) {
            throw new RuntimeException('Ripple worker server is not initialized. Make sure register() is called before bindRequestHandler().');
        }
        
        // 确保 server 实例有 onRequest 方法
        if (!method_exists($server, 'onRequest')) {
            throw new RuntimeException('Ripple server does not have an onRequest method');
        }
        
        // 绑定请求处理器
        $server->onRequest([$this, 'handleRippleRequest']);
    }

    /**
     * 创建 ThinkPHP 请求对象
     *
     * @param \Ripple\Http\Server\Request $request
     * @return Request
     */
    /**
     * 创建 ThinkPHP 请求对象
     *
     * @param \Ripple\Http\Server\Request $request
     * @return Request
     */
    protected function createThinkRequest(\Ripple\Http\Server\Request $request): Request
    {
        // 确保请求对象有效
        if (!$request) {
            throw new \InvalidArgumentException('Request object cannot be null');
        }
        
        // 获取服务器变量
        $server = $request->SERVER ?? [];
        $headers = $request->header ?? [];
        
        // 设置基本的服务器变量
        $server = array_merge([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_PORT' => $this->config['port'] ?? 8000,
            'SERVER_NAME' => $this->config['host'] ?? '0.0.0.0',
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_PORT' => 0,
            'SERVER_SOFTWARE' => 'Ripple/1.0',
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'HTTPS' => $this->config['ssl']['enabled'] ?? false ? 'on' : 'off',
            'SCRIPT_NAME' => 'index.php',
            'SCRIPT_FILENAME' => 'index.php',
            'PHP_SELF' => '/index.php',
            'QUERY_STRING' => '',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'REQUEST_TIME' => time(),
        ], $server);
        
        // 更新请求方法
        $server['REQUEST_METHOD'] = $request->getMethod() ?? $server['REQUEST_METHOD'];
        
        // 更新请求 URI
        $requestUri = $request->getRequestUri();
        if ($requestUri) {
            $server['REQUEST_URI'] = $requestUri;
            
            // 解析查询字符串
            $queryString = parse_url($requestUri, PHP_URL_QUERY);
            if ($queryString) {
                $server['QUERY_STRING'] = $queryString;
                parse_str($queryString, $get);
                $request->withQueryParams($get);
            }
        }
        
        // 设置 HTTPS
        if (($this->config['ssl']['enabled'] ?? false) || ($server['HTTPS'] ?? '') === 'on') {
            $server['HTTPS'] = 'on';
        } else {
            unset($server['HTTPS']);
        }
        
        // 设置远程地址和端口
        $server['REMOTE_ADDR'] = $request->getServerParam('remote_addr') ?? $server['REMOTE_ADDR'];
        $server['REMOTE_PORT'] = $request->getServerParam('remote_port') ?? $server['REMOTE_PORT'] ?? 0;
        
        // 设置 Content-Type 和 Content-Length
        $contentType = $request->getHeaderLine('content-type');
        if ($contentType) {
            $server['CONTENT_TYPE'] = $contentType;
            $headers['content-type'] = $contentType;
        }
        
        $contentLength = $request->getHeaderLine('content-length');
        if ($contentLength) {
            $server['CONTENT_LENGTH'] = $contentLength;
            $headers['content-length'] = $contentLength;
        }
        
        // 设置 HTTP_ 开头的请求头
        foreach ($headers as $name => $value) {
            if (!is_array($value)) {
                $name = str_replace('-', '_', strtoupper($name));
                $server['HTTP_' . $name] = $value;
            }
        }
        
        // 设置请求体
        $body = $request->getBody();
        if ($body) {
            $content = (string) $body;
            $server['HTTP_CONTENT'] = $content;
            $request->withBody($content);
        }
        $server['REMOTE_PORT'] = $request->SERVER['REMOTE_PORT'] ?? 0;
        $server['SERVER_SOFTWARE'] = 'Ripple/1.0';
        $server['HTTP_HOST'] = $this->config['host'] . ':' . $this->config['port'];
        
        // 合并请求头
        $headers = [];
        foreach ($request->SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }
        
        // 获取请求体内容
        $content = $request->CONTENT ?? '';
        if (is_array($content) || is_object($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }

        // 创建 ThinkPHP 请求
        $thinkRequest = app('request');
        
        // 设置请求方法
        $thinkRequest->setMethod($server['REQUEST_METHOD']);
        
        // 设置请求 URI
        $thinkRequest->setUrl($server['REQUEST_URI']);
        
        // 设置请求参数
        $thinkRequest->withGet($request->GET ?? []);
        $thinkRequest->withPost($request->POST ?? []);
        $thinkRequest->withRequest($request->REQUEST ?? []);
        $thinkRequest->withCookie($request->COOKIE ?? []);
        $thinkRequest->withFiles($request->FILES ?? []);
        
        // 设置服务器变量
        $thinkRequest->withServer($server);
        
        // 设置请求体内容
        if (!empty($content)) {
            $thinkRequest->withInput($content);
        }
        
        // 设置请求头
        $thinkRequest->withHeader($headers);
        
        return $thinkRequest;
    }

    /**
     * 创建 Ripple 响应
     *
     * @param \Ripple\Http\Server\Response $rippleResponse
     * @param Response $thinkResponse
     * @return \Ripple\Http\Server\Response
     * @throws JsonException
     */
    /**
     * 创建 Ripple 响应
     *
     * @param \Ripple\Http\Server\Response $rippleResponse
     * @param Response $thinkResponse
     * @return \Ripple\Http\Server\Response
     * @throws JsonException
     * @throws \RuntimeException
     */
    protected function createRippleResponse(
        \Ripple\Http\Server\Response $rippleResponse,
        Response $thinkResponse
    ): \Ripple\Http\Server\Response {
        // 确保响应对象有效
        if (!$rippleResponse) {
            throw new \InvalidArgumentException('Ripple response object cannot be null');
        }
        
        if (!$thinkResponse) {
            throw new \InvalidArgumentException('ThinkPHP response object cannot be null');
        }
        
        try {
            // 设置状态码
            $statusCode = $thinkResponse->getCode() ?? 200;
            $rippleResponse->setStatusCode($statusCode);
            
            // 设置响应头并获取更新后的响应对象
            $rippleResponse = $this->setResponseHeaders($rippleResponse, $thinkResponse);
            
            // 设置响应内容
            if (method_exists($thinkResponse, 'getContent')) {
                $content = $thinkResponse->getContent();
                $this->processResponseContent($rippleResponse, $content);
                
                // 如果没有设置 Content-Length 头部，自动计算并设置
                if (!$rippleResponse->hasHeader('Content-Length')) {
                    $contentLength = is_string($content) ? strlen($content) : 0;
                    if ($contentLength > 0) {
                        $rippleResponse = $rippleResponse->withHeader('Content-Length', (string) $contentLength);
                    }
                }
            }
            
            return $rippleResponse;
            
        } catch (Throwable $e) {
            // 记录错误
            $errorMessage = sprintf(
                'Failed to create Ripple response: %s in %s:%d\nStack trace:\n%s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            
            if (isset($this->app) && method_exists($this->app, 'log')) {
                $this->app->log->error($errorMessage);
            } else {
                error_log($errorMessage);
            }
            
            // 返回一个简单的错误响应
            $rippleResponse->setStatusCode(500);
            $rippleResponse->withHeader('Content-Type', 'text/plain');
            $rippleResponse->setContent('500 Internal Server Error: Failed to create response');
            
            return $rippleResponse;
        }
    }

    /**
     * 记录信息日志
     *
     * @param string $message 日志消息
     * @param array $context 日志上下文
     * @return void
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
    
    /**
     * 记录错误日志
     *
     * @param string $message 错误消息
     * @param array $context 错误上下文
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
    
    /**
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 日志上下文
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        // 如果有应用实例并且有日志服务，则使用日志服务记录
        if (isset($this->app) && method_exists($this->app, 'log')) {
            $logMessage = $message;
            if (!empty($context)) {
                $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            
            switch (strtolower($level)) {
                case 'error':
                    $this->app->log->error($logMessage);
                    break;
                case 'warning':
                    $this->app->log->warning($logMessage);
                    break;
                case 'info':
                default:
                    $this->app->log->info($logMessage);
                    break;
            }
        } else {
            // 否则输出到标准错误或标准输出
            $logMessage = sprintf(
                '[%s] %s: %s %s\n',
                date('Y-m-d H:i:s'),
                strtoupper($level),
                $message,
                !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
            );
            
            if ($level === 'error') {
                fwrite(STDERR, $logMessage);
            } else {
                echo $logMessage;
            }
        }
    }
    
    /**
     * 检查 worker 是否已经注册
     *
     * @return bool
     * @throws \ReflectionException
     */
    protected function isWorkerRegistered(): bool
    {
        if (!$this->worker) {
            return false;
        }
        
        try {
            $reflection = new \ReflectionClass($this->worker);
            
            // 检查 server 属性是否存在
            if (!$reflection->hasProperty('server')) {
                return false;
            }
            
            // 获取 server 属性
            $serverProperty = $reflection->getProperty('server');
            $serverProperty->setAccessible(true);
            
            // 检查 server 属性是否已经初始化
            if (!$serverProperty->isInitialized($this->worker)) {
                return false;
            }
            
            // 获取 server 实例
            $server = $serverProperty->getValue($this->worker);
            
            return $server !== null;
            
        } catch (\ReflectionException $e) {
            $this->logError('Failed to check if worker is registered: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 启动 Ripple 服务器
     *
     * @return void
     * @throws RuntimeException 当启动失败时抛出异常
     */
    protected function startServer(): void
    {
        if (!$this->worker) {
            throw new RuntimeException('Ripple worker is not initialized');
        }
        
        // 确保 worker 已经注册
        if (!method_exists($this->worker, 'register')) {
            throw new RuntimeException('Ripple worker does not have a register() method');
        }
        
        // 初始化 manager 如果不存在
        if (!$this->manager) {
            try {
                $this->manager = new RippleManager();
                
                // 设置 manager 配置
                if (method_exists($this->manager, 'setConfig')) {
                    $this->manager->setConfig($this->config);
                }
            } catch (Throwable $e) {
                throw new RuntimeException('Failed to initialize Ripple manager: ' . $e->getMessage(), 0, $e);
            }
        }

        // 设置进程标题
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title('think-ripple: master process');
        }

        // 注册信号处理器
        $this->registerSignalHandlers();

        try {
            // 确保 worker 已经注册
            if (method_exists($this->worker, 'register') && !$this->isWorkerRegistered()) {
                $this->worker->register($this->manager);
            }
            
            // 添加 worker 到 manager
            $this->manager->add($this->worker);
            
            // 输出启动信息
            $this->outputStartupInfo();
            
            // 记录启动日志
            $this->logInfo('Starting Ripple server...', [
                'host' => $this->config['host'] ?? '0.0.0.0',
                'port' => $this->config['port'] ?? 8000,
                'workers' => $this->config['worker_num'] ?? 4,
                'daemonize' => $this->config['daemonize'] ?? false,
            ]);
            
            // 记录服务器启动时间
            $this->startTime = time();
            
            // 重置请求统计信息
            $this->resetRequestStats();
            
            // 设置运行状态
            $this->isRunning = true;
            
            // 记录服务器启动日志
            $this->logInfo('Ripple server started successfully', [
                'start_time' => date('Y-m-d H:i:s', $this->startTime),
                'pid' => getmypid(),
                'host' => $this->config['host'] ?? '0.0.0.0',
                'port' => $this->config['port'] ?? 8000,
                'workers' => $this->config['worker_num'] ?? 4,
                'daemonize' => $this->config['daemonize'] ?? false,
                'debug' => $this->config['debug'] ?? false
            ]);
            
            // 启动 worker 的 boot 方法
            if (method_exists($this->worker, 'boot')) {
                try {
                    $this->worker->boot();
                } catch (Throwable $e) {
                    $this->logError('Worker boot failed: ' . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }
            
            // 设置定时器，每小时清理一次过期的请求时间戳
            if (extension_loaded('swoole') && function_exists('\Swoole\Timer::tick')) {
                // 每小时清理一次过期的请求时间戳
                $cleanupInterval = 3600 * 1000; // 1小时，单位毫秒
                \Swoole\Timer::tick($cleanupInterval, function() {
                    $removed = $this->cleanupExpiredRequestTimestamps(3600); // 清理1小时前的记录
                    $this->logDebug(sprintf(
                        'Periodic cleanup: removed %d expired request timestamps',
                        $removed
                    ));
                });
                
                // 每5分钟检查一次内存使用情况
                $memoryCheckInterval = 300 * 1000; // 5分钟，单位毫秒
                \Swoole\Timer::tick($memoryCheckInterval, function() {
                    $memoryInfo = $this->checkMemoryUsage();
                    $this->logDebug('Memory usage check', $memoryInfo);
                    
                    // 如果内存使用率超过90%，记录警告日志
                    if (isset($memoryInfo['usage_percent'])) {
                        $usagePercent = (float)rtrim($memoryInfo['usage_percent'], '%');
                        if ($usagePercent > 90) {
                            $this->logWarning('High memory usage detected', [
                                'usage_percent' => $memoryInfo['usage_percent'],
                                'current_usage' => $memoryInfo['current_usage'],
                                'memory_limit' => $memoryInfo['memory_limit']
                            ]);
                        }
                    }
                });
                
                $this->logDebug('Registered periodic timers for cleanup and memory checks');
            }
            
            // 启动 manager
            try {
                $this->manager->run();
            } catch (Throwable $e) {
                $this->logError('Manager run failed: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            
            // 如果执行到这里，说明服务器已经停止
            $this->isRunning = false;
            $this->logInfo('Ripple server stopped');
            
        } catch (Throwable $e) {
            $this->isRunning = false;
            $errorMessage = sprintf(
                'Failed to start Ripple server: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            
            $this->logError($errorMessage, [
                'trace' => $e->getTraceAsString()
            ]);
            
            echo $errorMessage . "\n";
            throw new RuntimeException('Failed to start Ripple server: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 注册单个信号处理器
     *
     * @param int $signal 信号常量（如 SIGTERM, SIGINT 等）
     * @param callable $handler 信号处理回调
     * @param string $signalName 信号名称（用于日志）
     * @return void
     * @throws \RuntimeException 当信号注册失败时抛出
     */
    protected function registerSignalHandler(int $signal, callable $handler, string $signalName): void
    {
        if (!defined('SIG' . $signalName) || !function_exists('pcntl_signal')) {
            $this->logWarning(sprintf('Signal %s is not defined or PCNTL is not available', $signalName));
            return;
        }
        
        $result = @pcntl_signal($signal, $handler);
        
        if ($result === false) {
            $error = error_get_last();
            $errorMessage = $error ? $error['message'] : 'Unknown error';
            
            $this->logError(sprintf(
                'Failed to register %s handler: %s',
                $signalName,
                $errorMessage
            ));
            
            throw new \RuntimeException(sprintf(
                'Failed to register %s handler: %s',
                $signalName,
                $errorMessage
            ));
        }
        
        $this->logDebug(sprintf('Successfully registered %s handler', $signalName));
    }
    
    /**
     * 记录警告日志
     *
     * @param string $message 警告消息
     * @param array $context 日志上下文
     * @return void
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
    
    /**
     * 记录调试日志
     *
     * @param string $message 调试消息
     * @param array $context 日志上下文
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        // 只有在调试模式下才记录调试日志
        if ($this->config['debug'] ?? false) {
            $this->log('debug', $message, $context);
        }
    }
    
    /**
     * 注册信号处理器
     *
     * @return void
     */
    protected function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            $this->logWarning('PCNTL extension is not available. Signal handling disabled.');
            return;
        }
        
        // 启用异步信号处理
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }

        try {
            // 注册 SIGTERM 信号处理器（优雅停止）
            $this->registerSignalHandler(SIGTERM, function () {
                $this->logInfo('Received SIGTERM signal. Stopping server...');
                $this->stop();
                exit(0);
            }, 'SIGTERM');

            // 注册 SIGINT 信号处理器（Ctrl+C）
            $this->registerSignalHandler(SIGINT, function () {
                $this->logInfo('Received SIGINT signal. Stopping server...');
                $this->stop();
                exit(0);
            }, 'SIGINT');

            // 注册 SIGHUP 信号处理器（重载配置）
            $this->registerSignalHandler(SIGHUP, function () {
                $this->logInfo('Received SIGHUP signal. Reloading configuration...');
                try {
                    $this->reload();
                    $this->logInfo('Configuration reloaded successfully');
                } catch (Throwable $e) {
                    $this->logError('Failed to reload configuration: ' . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }, 'SIGHUP');
            
            // 注册 SIGUSR1 信号处理器（自定义信号）
            if (defined('SIGUSR1')) {
                $this->registerSignalHandler(SIGUSR1, function () {
                    $this->logInfo('Received SIGUSR1 signal. Status report:', [
                        'isRunning' => $this->isRunning,
                        'workerCount' => $this->manager ? count($this->manager->getWorkers()) : 0,
                        'memoryUsage' => memory_get_usage(true) / 1024 / 1024 . 'MB',
                        'peakMemoryUsage' => memory_get_peak_usage(true) / 1024 / 1024 . 'MB',
                    ]);
                }, 'SIGUSR1');
            }
            
            $this->logInfo('Signal handlers registered successfully');
            
        } catch (Throwable $e) {
            $this->logError('Failed to register signal handlers: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 输出启动信息
     *
     * @return void
     */
    /**
     * 输出启动信息
     *
     * @return void
     */
    /**
     * 输出服务器启动信息
     * 
     * 显示服务器配置、环境信息和有用的调试信息
     * 这些信息会同时输出到控制台和日志文件
     */
    protected function outputStartupInfo(): void
    {
        // 基本配置
        $scheme = $this->config['ssl']['enabled'] ?? false ? 'https' : 'http';
        $host = $this->config['host'] ?? '0.0.0.0';
        $port = $this->config['port'] ?? 8000;
        $workers = $this->config['worker_num'] ?? 4;
        $daemonize = $this->config['daemonize'] ?? false;
        $debug = $this->config['debug'] ?? false;
        $pidFile = $this->config['pid_file'] ?? '';
        $maxRequest = $this->config['max_request'] ?? 0;
        $maxRequestGrace = $this->config['max_request_grace'] ?? 0;
        $reloadAsync = $this->config['reload_async'] ?? true;
        
        // 计算URL
        $displayHost = $host === '0.0.0.0' ? '127.0.0.1' : $host;
        $url = "{$scheme}://{$displayHost}:{$port}";
        
        // 获取系统信息
        $phpVersion = PHP_VERSION;
        $swooleVersion = extension_loaded('swoole') ? phpversion('swoole') : 'Not installed';
        $os = PHP_OS . ' ' . php_uname('r');
        $memoryLimit = ini_get('memory_limit');
        $uploadMaxSize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        $maxExecutionTime = ini_get('max_execution_time');
        $timezone = date_default_timezone_get();
        $pid = getmypid();
        $user = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown';
        $cpuCores = function_exists('swoole_cpu_num') ? swoole_cpu_num() : 'unknown';
        
        // 构建信息行
        $lines = [
            '╔══════════════════════════════════════════════════╗',
            '║           ThinkPHP Ripple Server                 ║',
            '╠══════════════════════════════════════════════════╣',
            "║  Server URL:     {$url}",
            '╠══════════════════════════════════════════════════╣',
            "║  Host:           {$host}",
            "║  Port:           {$port}",
            "║  Workers:        {$workers}",
            "║  Daemon:         " . ($daemonize ? 'Yes' : 'No'),
            "║  Debug Mode:     " . ($debug ? 'Enabled' : 'Disabled'),
            "║  PID File:       " . ($pidFile ?: 'Not specified'),
            '╠══════════════════════════════════════════════════╣',
            "║  PHP Version:    {$phpVersion}",
            "║  Swoole:         {$swooleVersion}",
            "║  OS:             {$os}",
            "║  User:           {$user}",
            "║  PID:            {$pid}",
            "║  CPU Cores:      {$cpuCores}",
            '╠══════════════════════════════════════════════════╣',
            "║  Memory Limit:   {$memoryLimit}",
            "║  Upload Max:     {$uploadMaxSize}",
            "║  Post Max:       {$postMaxSize}",
            "║  Max Exec Time:  {$maxExecutionTime}s",
            "║  Timezone:       {$timezone}",
            '╠══════════════════════════════════════════════════╣',
            '║  Server Status:  Running',
            "║  Start Time:     " . date('Y-m-d H:i:s'),
            "║  Max Requests:   " . ($maxRequest ?: 'No limit'),
            "║  Max Req Grace:  " . ($maxRequestGrace ?: 'Default'),
            "║  Reload Async:   " . ($reloadAsync ? 'Yes' : 'No'),
            '╠══════════════════════════════════════════════════╣',
            '║  Press Ctrl+C to stop the server',
            '║  Send SIGUSR1 to show server status',
            '║  Send SIGHUP to reload configuration',
            '╚══════════════════════════════════════════════════╝',
        ];
        
        // 输出到控制台
        echo "\n" . implode("\n", $lines) . "\n\n";
        
        // 记录启动日志
        $this->logInfo('Ripple server started', [
            'server' => [
                'url' => $url,
                'host' => $host,
                'port' => $port,
                'workers' => $workers,
                'daemonize' => $daemonize,
                'debug' => $debug,
                'pid_file' => $pidFile,
                'max_request' => $maxRequest,
                'max_request_grace' => $maxRequestGrace,
                'reload_async' => $reloadAsync,
                'start_time' => date('Y-m-d H:i:s'),
            ],
            'environment' => [
                'php_version' => $phpVersion,
                'swoole_version' => extension_loaded('swoole') ? phpversion('swoole') : null,
                'os' => $os,
                'user' => $user,
                'pid' => $pid,
                'cpu_cores' => $cpuCores,
                'memory_limit' => $memoryLimit,
                'upload_max_filesize' => $uploadMaxSize,
                'post_max_size' => $postMaxSize,
                'max_execution_time' => $maxExecutionTime,
                'timezone' => $timezone,
            ]
        ]);
    }

    /**
     * 设置响应头
     *
     * @param \Ripple\Http\Server\Response $rippleResponse
     * @param Response $thinkResponse
     * @return \Ripple\Http\Server\Response
     */
    /**
     * 设置响应头
     *
     * @param \Ripple\Http\Server\Response $rippleResponse
     * @param Response $thinkResponse
     * @return \Ripple\Http\Server\Response
     */
    private function setResponseHeaders(
        \Ripple\Http\Server\Response $rippleResponse,
        Response $thinkResponse
    ): \Ripple\Http\Server\Response {
        if (!$rippleResponse) {
            throw new \InvalidArgumentException('Ripple response object cannot be null');
        }
        
        if (!$thinkResponse) {
            throw new \InvalidArgumentException('ThinkPHP response object cannot be null');
        }
        
        try {
            // 获取ThinkPHP响应头
            $thinkHeaders = [];
            if (method_exists($thinkResponse, 'getHeader')) {
                $thinkHeaders = $thinkResponse->getHeader();
            }
            
            // 确保 headers 是数组
            if (!is_array($thinkHeaders)) {
                $thinkHeaders = [];
            }
            
            // 设置默认的 Content-Type 如果未设置
            if (empty($thinkHeaders['Content-Type']) && empty($thinkHeaders['content-type'])) {
                $thinkHeaders['Content-Type'] = 'text/html; charset=utf-8';
            }

            // 构建运行时头部
            $runtimeHeaders = $this->buildRuntimeHeaders();
            
            // 添加Ripple特定头部
            $runtimeHeaders['X-Powered-By'] = 'Ripple/ThinkPHP-Runtime';
            $runtimeHeaders['X-Runtime'] = 'Ripple';

            // 创建临时PSR-7响应对象用于头部处理
            $tempPsr7Response = new \Nyholm\Psr7\Response(200, $thinkHeaders);
            
            // 使用统一的头部去重服务处理所有头部
            $finalHeaders = $this->processResponseHeaders($tempPsr7Response, $runtimeHeaders);
            
            // 设置去重后的响应头
            foreach ($finalHeaders as $name => $value) {
                if (is_string($name) && (is_string($value) || is_array($value))) {
                    // 确保头名称是有效的
                    $name = trim($name);
                    if (empty($name)) {
                        continue;
                    }
                    
                    // 如果值是数组，用逗号连接
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    
                    // 使用 withHeader 方法并获取新的响应实例
                    $rippleResponse = $rippleResponse->withHeader($name, $value);
                }
            }
            
            return $rippleResponse;
            
        } catch (Throwable $e) {
            // 记录错误
            $errorMessage = sprintf(
                'Failed to set response headers: %s in %s:%d\nStack trace:\n%s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            
            if (isset($this->app) && method_exists($this->app, 'log')) {
                $this->app->log->error($errorMessage);
            } else {
                error_log($errorMessage);
            }
            
            // 返回原始响应对象
            return $rippleResponse;
        }
    }
    
    /**
     * 处理响应内容
     *
     * @param \Ripple\Http\Server\Response $response
     * @param mixed $content
     * @return void
     * @throws JsonException
     */
    /**
     * 处理响应内容
     *
     * @param \Ripple\Http\Server\Response $response
     * @param mixed $content
     * @return void
     * @throws JsonException
     */
    private function processResponseContent(
        \Ripple\Http\Server\Response $response,
        mixed $content
    ): void {
        if (!$response) {
            throw new \InvalidArgumentException('Response object cannot be null');
        }
        
        try {
            // 如果内容为空，直接返回
            if ($content === null || $content === '') {
                $response->setContent('');
                return;
            }
            
            // 处理不同类型的响应内容
            if ($content instanceof Stream || $content instanceof Generator) {
                $response->setContent($content);
                return;
            }
            
            if (is_string($content)) {
                $response->setContent($content);
                return;
            }
            
            if (is_array($content) || is_object($content)) {
                // 如果对象有 __toString 方法，则转换为字符串
                if (is_object($content) && method_exists($content, '__toString')) {
                    $response->setContent((string) $content);
                    return;
                }
                
                // 否则尝试序列化为 JSON
                $this->setJsonResponse($response, $content);
                return;
            }
            
            if (is_resource($content)) {
                $response->setContent(new Stream($content));
                return;
            }
            
            // 其他类型的响应内容转换为字符串
            $response->setContent((string) $content);
            
        } catch (Throwable $e) {
            // 记录错误
            $errorMessage = sprintf(
                'Failed to process response content: %s in %s:%d\nStack trace:\n%s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            
            if (isset($this->app) && method_exists($this->app, 'log')) {
                $this->app->log->error($errorMessage);
            } else {
                error_log($errorMessage);
            }
            
            // 设置一个错误响应
            $response->setContent('500 Internal Server Error: Failed to process response content');
        }
    }
    
    /**
     * 设置 JSON 响应
     *
     * @param \Ripple\Http\Server\Response $response
     * @param mixed $data
     * @return void
     * @throws JsonException
     */
    private function setJsonResponse(
        \Ripple\Http\Server\Response $response, 
        mixed $data
    ): void {
        if (!$response) {
            throw new \InvalidArgumentException('Response object cannot be null');
        }
        
        try {
            // 设置 Content-Type 头部
            $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            
            // 如果数据是可序列化的对象，则尝试序列化
            if (is_object($data)) {
                // 如果对象实现了 JsonSerializable 接口，则使用 jsonSerialize 方法
                if ($data instanceof \JsonSerializable) {
                    $data = $data->jsonSerialize();
                } 
                // 如果对象有 toArray 方法，则调用它
                elseif (method_exists($data, 'toArray')) {
                    $data = $data->toArray();
                }
                // 如果对象有 toJson 方法，则调用它
                elseif (method_exists($data, 'toJson')) {
                    $json = $data->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $response->setContent($json);
                    return;
                }
            }
            
            // 序列化数据为 JSON 字符串
            $json = json_encode(
                $data, 
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            
            // 设置响应内容
            $response->setContent($json);
            
        } catch (JsonException $e) {
            // 记录 JSON 编码错误
            $errorMessage = sprintf(
                'JSON encode error: %s in %s:%d\nData: %s',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                print_r($data, true)
            );
            
            if (isset($this->app) && method_exists($this->app, 'log')) {
                $this->app->log->error($errorMessage);
            } else {
                error_log($errorMessage);
            }
            
            // 重新抛出异常
            throw $e;
        }
    }
    
    /**
     * 检查内存使用情况并在需要时执行清理
     * 
     * 此方法检查当前内存使用情况，如果接近内存限制，则执行清理操作
     * 
     * @param bool $force 是否强制执行清理
     * @return array 包含内存使用信息和清理结果
     */
    public function checkMemoryUsage(bool $force = false): array
    {
        if (!function_exists('memory_get_usage') || !function_exists('memory_get_peak_usage')) {
            return ['status' => 'unsupported'];
        }
        
        $memoryLimit = $this->getMemoryLimitInBytes();
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $usagePercent = $memoryLimit > 0 ? ($currentUsage / $memoryLimit) * 100 : 0;
        
        $result = [
            'memory_limit' => $this->formatBytes($memoryLimit),
            'current_usage' => $this->formatBytes($currentUsage),
            'peak_usage' => $this->formatBytes($peakUsage),
            'usage_percent' => round($usagePercent, 2) . '%',
            'cleaned' => false,
            'action_taken' => 'none'
        ];
        
        // 如果内存使用率超过80%或者强制清理
        $highMemoryThreshold = 80; // 80% 内存使用率阈值
        if ($force || ($memoryLimit > 0 && $usagePercent > $highMemoryThreshold)) {
            $result['action_taken'] = 'cleanup';
            
            // 首先尝试正常清理
            $removed = $this->cleanupExpiredRequestTimestamps(3600, false);
            
            // 如果内存仍然高，则强制清理更多数据
            $currentUsageAfter = memory_get_usage(true);
            $usagePercentAfter = $memoryLimit > 0 ? ($currentUsageAfter / $memoryLimit) * 100 : 0;
            
            if (($memoryLimit > 0 && $usagePercentAfter > $highMemoryThreshold) || $force) {
                $removed += $this->cleanupExpiredRequestTimestamps(0, true); // 强制清理
                $currentUsageAfter = memory_get_usage(true);
                $usagePercentAfter = $memoryLimit > 0 ? ($currentUsageAfter / $memoryLimit) * 100 : 0;
                $result['action_taken'] = 'force_cleanup';
            }
            
            $result['cleaned'] = true;
            $result['timestamps_removed'] = $removed;
            $result['current_usage_after'] = $this->formatBytes($currentUsageAfter);
            $result['usage_percent_after'] = round($usagePercentAfter, 2) . '%';
            $result['memory_freed'] = $this->formatBytes($currentUsage - $currentUsageAfter);
            
            $this->logInfo('Memory cleanup performed', $result);
        }
        
        return $result;
    }
    
    /**
     * 获取PHP内存限制（字节）
     * 
     * @return int 内存限制（字节）
     */
    private function getMemoryLimitInBytes(): int
    {
        $memoryLimit = ini_get('memory_limit');
        if (empty($memoryLimit) || $memoryLimit === '-1') {
            return -1; // 无限制
        }
        
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'G': return $value * 1024 * 1024 * 1024;
            case 'M': return $value * 1024 * 1024;
            case 'K': return $value * 1024;
            default:  return (int) $memoryLimit;
        }
    }
    
    /**
     * 格式化字节数为易读格式
     * 
     * @param int $bytes 字节数
     * @param int $precision 小数位数
     * @return string 格式化后的字符串
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * 记录内存使用情况统计信息
     * 
     * @param string $context 上下文信息（如 'request', 'background', 'shutdown' 等）
     * @param array $extraData 额外的自定义数据
     * @return array 内存使用统计信息
     */
    public function logMemoryUsage(string $context = 'request', array $extraData = []): array
    {
        if (!function_exists('memory_get_usage') || !function_exists('memory_get_peak_usage')) {
            return ['status' => 'unsupported'];
        }
        
        $memoryLimit = $this->getMemoryLimitInBytes();
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $realUsage = memory_get_usage(false);
        $realPeakUsage = memory_get_peak_usage(false);
        $usagePercent = $memoryLimit > 0 ? ($currentUsage / $memoryLimit) * 100 : 0;
        $peakPercent = $memoryLimit > 0 ? ($peakUsage / $memoryLimit) * 100 : 0;
        
        $memoryInfo = [
            'context' => $context,
            'timestamp' => microtime(true),
            'datetime' => date('Y-m-d H:i:s'),
            'memory_limit' => $this->formatBytes($memoryLimit),
            'memory_limit_bytes' => $memoryLimit,
            'current_usage' => $this->formatBytes($currentUsage),
            'current_usage_bytes' => $currentUsage,
            'peak_usage' => $this->formatBytes($peakUsage),
            'peak_usage_bytes' => $peakUsage,
            'real_usage' => $this->formatBytes($realUsage),
            'real_usage_bytes' => $realUsage,
            'real_peak_usage' => $this->formatBytes($realPeakUsage),
            'real_peak_usage_bytes' => $realPeakUsage,
            'usage_percent' => round($usagePercent, 2) . '%',
            'peak_percent' => round($peakPercent, 2) . '%',
            'active_requests' => $this->activeRequests ?? 0,
            'total_requests' => $this->requestCount ?? 0,
            'request_timestamps_count' => count($this->requestTimestamps ?? []),
            'slowest_request_time' => $this->slowestRequestTime ?? 0,
            'slowest_request_uri' => $this->slowestRequestUri ?? null,
            'total_request_time' => $this->totalRequestTime ?? 0,
            'average_request_time' => $this->requestCount > 0 ? $this->totalRequestTime / $this->requestCount : 0,
        ];
        
        // 合并额外数据
        if (!empty($extraData)) {
            $memoryInfo = array_merge($memoryInfo, $extraData);
        }
        
        // 记录到日志
        $logLevel = $peakPercent > 90 ? 'warning' : 'info';
        $this->log($logLevel, 'Memory usage statistics', $memoryInfo);
        
        // 如果内存使用率超过90%，触发内存清理
        if ($peakPercent > 90) {
            $this->logWarning('High memory usage detected - triggering cleanup', [
                'context' => $context,
                'peak_usage' => $memoryInfo['peak_usage'],
                'memory_limit' => $memoryInfo['memory_limit'],
                'peak_percent' => $memoryInfo['peak_percent']
            ]);
            $this->checkMemoryUsage(true);
        }
        
        return $memoryInfo;
    }
}
