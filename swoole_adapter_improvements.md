# SwooleAdapter 改进建议

基于 think-swoole 官方库的最佳实践，当前 `SwooleAdapter.php` 有以下改进空间：

## 1. 请求处理优化

### 当前问题
- 每次请求都创建新的 PSR-7 工厂实例
- 缺少请求上下文管理
- 没有充分利用 Swoole 的协程特性

### 改进建议
```php
// 添加请求上下文管理
protected array $requestContext = [];

// 复用 PSR-7 工厂
protected Psr17Factory $psr17Factory;
protected ServerRequestCreator $requestCreator;

public function boot(): void
{
    // 初始化时创建工厂实例
    $this->psr17Factory = new Psr17Factory();
    $this->requestCreator = new ServerRequestCreator(
        $this->psr17Factory,
        $this->psr17Factory,
        $this->psr17Factory,
        $this->psr17Factory
    );
    // ...
}
```

## 2. 协程支持增强

### 当前问题
- 缺少协程上下文隔离
- 没有协程异常处理机制
- 缺少协程资源管理

### 改进建议
```php
public function onRequest(SwooleRequest $request, SwooleResponse $response): void
{
    // 使用协程处理请求
    go(function () use ($request, $response) {
        try {
            // 设置协程上下文
            $this->setCoroutineContext($request);
            
            // 处理请求
            $psr7Request = $this->convertSwooleRequestToPsr7($request);
            $psr7Response = $this->handleRequest($psr7Request);
            
            // 发送响应
            $this->sendSwooleResponse($response, $psr7Response);
            
        } catch (\Throwable $e) {
            $this->handleSwooleError($response, $e);
        } finally {
            // 清理协程上下文
            $this->clearCoroutineContext();
        }
    });
}

protected function setCoroutineContext(SwooleRequest $request): void
{
    $cid = \Swoole\Coroutine::getCid();
    $this->requestContext[$cid] = [
        'request_id' => uniqid(),
        'start_time' => microtime(true),
        'request' => $request,
    ];
}

protected function clearCoroutineContext(): void
{
    $cid = \Swoole\Coroutine::getCid();
    unset($this->requestContext[$cid]);
}
```

## 3. 连接池支持

### 当前问题
- 缺少数据库连接池
- 没有 Redis 连接池
- 缺少 HTTP 客户端连接池

### 改进建议
```php
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;

protected ?PDOPool $dbPool = null;

public function boot(): void
{
    // 初始化数据库连接池
    $this->initDatabasePool();
    // ...
}

protected function initDatabasePool(): void
{
    $config = $this->app->config->get('database.connections.mysql', []);
    
    if (!empty($config)) {
        $pdoConfig = (new PDOConfig())
            ->withHost($config['hostname'] ?? '127.0.0.1')
            ->withPort($config['hostport'] ?? 3306)
            ->withDbName($config['database'] ?? '')
            ->withCharset($config['charset'] ?? 'utf8mb4')
            ->withUsername($config['username'] ?? '')
            ->withPassword($config['password'] ?? '');
            
        $this->dbPool = new PDOPool($pdoConfig, 64);
    }
}

public function getDbConnection()
{
    return $this->dbPool?->get();
}

public function putDbConnection($connection): void
{
    $this->dbPool?->put($connection);
}
```

## 4. 静态文件处理

### 当前问题
- 缺少静态文件服务功能
- 没有文件缓存机制
- 缺少 MIME 类型处理

### 改进建议
```php
protected function handleStaticFile(SwooleRequest $request, SwooleResponse $response): bool
{
    $uri = $request->server['request_uri'];
    $publicPath = $this->getPublicPath();
    $filePath = $publicPath . $uri;
    
    // 检查文件是否存在且在允许的目录内
    if (!$this->isValidStaticFile($filePath, $publicPath)) {
        return false;
    }
    
    // 设置 MIME 类型
    $mimeType = $this->getMimeType($filePath);
    $response->header('Content-Type', $mimeType);
    
    // 设置缓存头
    $response->header('Cache-Control', 'public, max-age=3600');
    $response->header('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');
    
    // 发送文件
    $response->sendfile($filePath);
    return true;
}

protected function isValidStaticFile(string $filePath, string $publicPath): bool
{
    // 安全检查：确保文件在公共目录内
    $realFilePath = realpath($filePath);
    $realPublicPath = realpath($publicPath);
    
    return $realFilePath && 
           $realPublicPath && 
           strpos($realFilePath, $realPublicPath) === 0 && 
           is_file($realFilePath);
}
```

## 5. WebSocket 支持

### 当前问题
- 缺少 WebSocket 服务器支持
- 没有 WebSocket 事件处理

### 改进建议
```php
protected function bindWebSocketEvents(): void
{
    if ($this->isWebSocketEnabled()) {
        $this->server->on('Open', [$this, 'onWebSocketOpen']);
        $this->server->on('Message', [$this, 'onWebSocketMessage']);
        $this->server->on('Close', [$this, 'onWebSocketClose']);
    }
}

public function onWebSocketOpen(Server $server, SwooleRequest $request): void
{
    echo "WebSocket connection opened: {$request->fd}\n";
}

public function onWebSocketMessage(Server $server, $frame): void
{
    echo "WebSocket message received from {$frame->fd}: {$frame->data}\n";
    
    // 处理 WebSocket 消息
    $response = $this->handleWebSocketMessage($frame);
    
    if ($response) {
        $server->push($frame->fd, $response);
    }
}

public function onWebSocketClose(Server $server, int $fd): void
{
    echo "WebSocket connection closed: {$fd}\n";
}
```

## 6. 中间件支持

### 当前问题
- 缺少 Swoole 特定的中间件处理
- 没有请求/响应拦截机制

### 改进建议
```php
protected array $middlewares = [];

public function addMiddleware(callable $middleware): void
{
    $this->middlewares[] = $middleware;
}

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

public function onRequest(SwooleRequest $request, SwooleResponse $response): void
{
    try {
        // 运行中间件
        if (!$this->runMiddlewares($request, $response)) {
            return;
        }
        
        // 处理静态文件
        if ($this->handleStaticFile($request, $response)) {
            return;
        }
        
        // 处理动态请求
        $psr7Request = $this->convertSwooleRequestToPsr7($request);
        $psr7Response = $this->handleRequest($psr7Request);
        $this->sendSwooleResponse($response, $psr7Response);
        
    } catch (\Throwable $e) {
        $this->handleSwooleError($response, $e);
    }
}
```

## 7. 性能监控

### 改进建议
```php
protected function logRequestMetrics(SwooleRequest $request, float $startTime): void
{
    $endTime = microtime(true);
    $duration = ($endTime - $startTime) * 1000; // 转换为毫秒
    
    $metrics = [
        'method' => $request->server['request_method'],
        'uri' => $request->server['request_uri'],
        'duration' => round($duration, 2),
        'memory' => memory_get_usage(true),
        'peak_memory' => memory_get_peak_usage(true),
    ];
    
    // 记录慢请求
    if ($duration > 1000) { // 超过1秒
        error_log("Slow request: " . json_encode($metrics));
    }
}
```

这些改进将使 SwooleAdapter 更加健壮、高效和功能完整。
