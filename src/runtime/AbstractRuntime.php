<?php

declare (strict_types = 1);

namespace yangweijie\thinkRuntime\runtime;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use think\App;
use think\Request;
use think\Response;
use Throwable;
use yangweijie\thinkRuntime\concerns\ModifyProperty;
use yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface;
use yangweijie\thinkRuntime\contract\RuntimeInterface;
use yangweijie\thinkRuntime\service\HeaderDeduplicationService;

/**
 * 抽象运行时基类
 * 提供运行时的通用实现
 */
abstract class AbstractRuntime implements RuntimeInterface
{
    use ModifyProperty;
    /**
     * ThinkPHP应用实例
     *
     * @var App|object
     */
    protected $app;

    /**
     * 运行时配置
     *
     * @var array
     */
    protected array $config = [];

    /**
     * PSR-17工厂
     *
     * @var Psr17Factory
     */
    protected Psr17Factory $psr17Factory;

    /**
     * 头部去重服务
     *
     * @var HeaderDeduplicationInterface
     */
    protected HeaderDeduplicationInterface $headerService;

    /**
     * 多值响应头列表
     * 这类头部允许在同一次响应中出现多次，禁止使用逗号合并成一个值
     *
     * @var array
     */
    protected const MULTI_VALUE_HEADERS = [
        'Set-Cookie',
    ];

    /**
     * 构造函数
     *
     * @param array $config 配置数组
     */
    public function __construct($app, array $config = [])
    {
        $this->app           = $app;
        $this->config        = $config;
        $this->psr17Factory  = new Psr17Factory();
        $this->headerService = new HeaderDeduplicationService();

        // 根据配置设置调试模式
        if (isset($config['header_deduplication']['debug_logging']) && $config['header_deduplication']['debug_logging']) {
            $this->headerService->setDebugMode(true);
        }
    }

    /**
     * 处理HTTP请求
     *
     * @param ServerRequestInterface $request PSR-7请求对象
     * @return ResponseInterface PSR-7响应对象
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // 检查应用是否已初始化
            if (! $this->app->initialized()) {
                $this->app->initialize();
            }

            // 重置请求相关的运行时状态
            $this->app->delete('think\Request');
            $this->app->delete('think\Response');

            // 将PSR-7请求转换为ThinkPHP请求
            $thinkRequest = $this->convertPsr7ToThinkRequest($request);

            // 处理请求
            $thinkResponse = $this->app->http->run($thinkRequest);

            // 执行必要的结束处理
            $this->app->http->end($thinkResponse);

            // 转换并返回响应
            return $this->convertThinkResponseToPsr7($thinkResponse);
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
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
     * 设置运行时配置
     *
     * @param array $config 配置数组
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 启动运行时
     * 子类必须实现此方法
     *
     * @param array $options 启动选项
     * @return void
     */
    abstract public function start(array $options = []): void;

    /**
     * 停止运行时
     * 子类必须实现此方法
     *
     * @return void
     */
    abstract public function stop(): void;

    /**
     * 获取运行时名称
     * 子类必须实现此方法
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * 检查运行时是否可用
     * 子类必须实现此方法
     *
     * @return bool
     */
    abstract public function isAvailable(): bool;

    /**
     * 将PSR-7请求转换为ThinkPHP请求
     *
     * @param ServerRequestInterface $psrRequest PSR-7请求
     * @return Request ThinkPHP请求
     */
    protected function convertPsr7ToThinkRequest(ServerRequestInterface $psrRequest): Request
    {
        // 解析请求方法、URI、Headers
        $method  = $psrRequest->getMethod();
        $uri     = $psrRequest->getUri();
        $headers = $psrRequest->getHeaders();

        // 构建 ThinkPHP 请求对象
        $request = $this->app->make('think\Request', [
            'path'    => $uri->getPath(),
            'host'    => $uri->getHost(),
            'method'  => $method,
            'headers' => $headers,
            'server'  => $psrRequest->getServerParams(),
        ]);

        // 注入输入数据
        $request->withGet($psrRequest->getQueryParams())

            ->withPost($psrRequest->getParsedBody() ?? [])

            ->withInput($psrRequest->getBody()->getContents());

        return $request;
    }

    /**
     * 将ThinkPHP响应转换为PSR-7响应
     *
     * @param Response $response ThinkPHP响应
     * @return ResponseInterface PSR-7响应
     */
    protected function convertThinkResponseToPsr7(Response $response): ResponseInterface
    {
        $headers = $response->getHeader();
        // 确保 headers 是数组
        if (! is_array($headers)) {
            $headers = [];
        }

        // 设置默认的 Content-Type 如果未设置
        if (empty($headers['Content-Type']) && empty($headers['content-type'])) {
            $headers['Content-Type'] = 'text/html; charset=utf-8';
        }

        // 采集 ThinkPHP 的 Cookie 队列并转换为 Set-Cookie 头部
        // 常驻内存运行时下原生 setcookie() 无效，Cookie 必须在此显式采集
        $cookieHeaders = $this->collectCookieHeaders($response);
        if (! empty($cookieHeaders)) {
            $existing = [];
            foreach (array_keys($headers) as $name) {
                if (strtolower((string) $name) === 'set-cookie') {
                    $existing = array_merge($existing, (array) $headers[$name]);
                    unset($headers[$name]);
                }
            }

            $headers['Set-Cookie'] = array_values(array_unique(array_merge($existing, $cookieHeaders)));
        }

        return new Psr7Response(
            $response->getCode(),
            $headers,
            $response->getContent()
        );
    }

    /**
     * 采集 ThinkPHP 响应中的 Cookie 队列并转换为 Set-Cookie 头部值列表
     *
     * ThinkPHP 的 Cookie 由 Response::send() 中的原生 setcookie() 输出，
     * 在 Swoole/Workerman 等常驻内存运行时下该函数不会生效，必须手动采集。
     *
     * @param Response $response ThinkPHP响应
     * @return array Set-Cookie 头部值列表
     */
    protected function collectCookieHeaders(Response $response): array
    {
        $cookieHeaders = [];

        if (! method_exists($response, 'getCookie')) {
            return $cookieHeaders;
        }

        $cookieJar = $response->getCookie();

        // 兼容两种实现：直接返回队列数组，或返回 Cookie 对象
        if (is_array($cookieJar)) {
            $queue = $cookieJar;
        } elseif (is_object($cookieJar) && method_exists($cookieJar, 'getCookie')) {
            $queue = (array) $cookieJar->getCookie();
        } else {
            return $cookieHeaders;
        }

        foreach ($queue as $name => $item) {
            // 队列结构为 [value, expire, option]
            if (! is_array($item) || count($item) < 2) {
                continue;
            }

            [$value, $expire] = $item;
            $option = $item[2] ?? [];

            $cookieHeaders[] = $this->buildSetCookieHeader(
                (string) $name,
                (string) $value,
                (int) $expire,
                is_array($option) ? $option : []
            );
        }

        // 补充原生 Session 产生的 Cookie（同样无法经由 setcookie() 送达）
        $cookieHeaders = $this->appendSessionCookie($queue, $cookieHeaders);

        // 常驻内存下 Cookie 对象是容器单例，队列必须清空，否则会串到后续请求
        $this->clearCookieQueue($cookieJar, $queue);

        return $cookieHeaders;
    }

    /**
     * 清空 Cookie 队列
     *
     * @param mixed $cookieJar Cookie对象或队列数组
     * @param array $queue 已读取的 Cookie 队列
     * @return void
     */
    protected function clearCookieQueue($cookieJar, array $queue): void
    {
        if (empty($queue) || ! is_object($cookieJar)) {
            return;
        }

        try {
            $reflection = new \ReflectionClass($cookieJar);
            if (! $reflection->hasProperty('cookie')) {
                return;
            }

            $property = $reflection->getProperty('cookie');
            $property->setAccessible(true);
            $property->setValue($cookieJar, []);
        } catch (\Throwable $e) {
            // 忽略反射失败，避免影响正常响应
        }
    }

    /**
     * 构建单个 Set-Cookie 头部值
     *
     * @param string $name Cookie名称
     * @param string $value Cookie值
     * @param int $expire 过期时间戳（0 表示会话 Cookie）
     * @param array $option Cookie选项（path/domain/secure/httponly/samesite）
     * @return string Set-Cookie 头部值
     */
    protected function buildSetCookieHeader(string $name, string $value, int $expire, array $option): string
    {
        $parts = [$name . '=' . urlencode($value)];

        if ($expire > 0) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s', $expire) . ' GMT';
            $parts[] = 'Max-Age=' . max(0, $expire - time());
        }

        $path = (string) ($option['path'] ?? '/');
        if ($path !== '') {
            $parts[] = 'Path=' . $path;
        }

        $domain = (string) ($option['domain'] ?? '');
        if ($domain !== '') {
            $parts[] = 'Domain=' . $domain;
        }

        if (! empty($option['secure'])) {
            $parts[] = 'Secure';
        }

        if (! empty($option['httponly'])) {
            $parts[] = 'HttpOnly';
        }

        $samesite = (string) ($option['samesite'] ?? '');
        if ($samesite !== '') {
            $parts[] = 'SameSite=' . ucfirst(strtolower($samesite));
        }

        return implode('; ', $parts);
    }

    /**
     * 补充由原生 session_start() 产生的 Session Cookie
     *
     * @param array $queue ThinkPHP Cookie队列
     * @param array $cookieHeaders 已采集的 Set-Cookie 头部值
     * @return array 补充后的 Set-Cookie 头部值
     */
    protected function appendSessionCookie(array $queue, array $cookieHeaders): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return $cookieHeaders;
        }

        $name = session_name();
        $sid  = session_id();

        // 已由 ThinkPHP Cookie 队列覆盖时无需重复下发
        if ($name === '' || $sid === '' || isset($queue[$name])) {
            return $cookieHeaders;
        }

        $params   = session_get_cookie_params();
        $lifetime = (int) ($params['lifetime'] ?? 0);

        $cookieHeaders[] = $this->buildSetCookieHeader(
            $name,
            $sid,
            $lifetime > 0 ? time() + $lifetime : 0,
            [
                'path'     => (string) ($params['path'] ?? '/'),
                'domain'   => (string) ($params['domain'] ?? ''),
                'secure'   => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? false),
                'samesite' => (string) ($params['samesite'] ?? ''),
            ]
        );

        return $cookieHeaders;
    }

    /**
     * 处理错误
     *
     * @param Throwable $e 异常
     * @return ResponseInterface PSR-7响应
     */
    protected function handleError(Throwable $e): ResponseInterface
    {
        $content = json_encode([
            'error'   => true,
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);

        return new Psr7Response(500, ['Content-Type' => 'application/json'], $content);
    }

    /**
     * 处理响应头部，合并PSR-7响应头部和运行时头部
     *
     * @param ResponseInterface $psrResponse PSR-7响应对象
     * @param array $runtimeHeaders 运行时特定的头部
     * @return array 处理后的头部数组
     */
    protected function processResponseHeaders(ResponseInterface $psrResponse, array $runtimeHeaders = []): array
    {
        // 获取PSR-7响应头部
        $psrHeaders = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            // 多值头部（如 Set-Cookie）必须保留为数组，不能用逗号合并
            if ($this->isMultiValueHeader((string) $name)) {
                $psrHeaders[$name] = is_array($values) ? array_values($values) : [$values];
            } else {
                $psrHeaders[$name] = is_array($values) ? implode(', ', $values) : $values;
            }
        }

        // 合并头部，PSR-7头部优先
        $mergedHeaders = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);

        // 去重处理
        return $this->headerService->deduplicateHeaders($mergedHeaders);
    }

    /**
     * 判断头部是否为多值头部
     * 多值头部允许在同一次响应中出现多次，禁止合并为单个值
     *
     * @param string $headerName 头部名称
     * @return bool 是否为多值头部
     */
    protected function isMultiValueHeader(string $headerName): bool
    {
        return in_array(
            $this->headerService->normalizeHeaderName($headerName),
            self::MULTI_VALUE_HEADERS,
            true
        );
    }

    /**
     * 分离响应头中的多值头部
     * 多值头部（如 Set-Cookie）需要拆成多条同名头发送，不能转成单个字符串
     *
     * @param array $headers 响应头数组
     * @return array 第一个元素为单值头部，第二个元素为多值头部
     */
    protected function splitMultiValueHeaders(array $headers): array
    {
        $single = [];
        $multi  = [];

        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $multi[(string) $name] = array_values(
                    array_map(static fn($item): string => (string) $item, $value)
                );
            } else {
                $single[$name] = $value;
            }
        }

        return [$single, $multi];
    }

    /**
     * 判断运行时是否应该跳过设置某个头部
     *
     * @param string $headerName 头部名称
     * @param array $psrHeaders PSR-7响应头部
     * @return bool 是否应该跳过
     */
    protected function shouldSkipRuntimeHeader(string $headerName, array $psrHeaders): bool
    {
        $normalizedName = $this->headerService->normalizeHeaderName($headerName);

        // 检查PSR-7响应中是否已存在该头部
        foreach ($psrHeaders as $name => $value) {
            if ($this->headerService->normalizeHeaderName($name) === $normalizedName) {
                // 对于唯一头部，如果PSR-7响应中已存在，则跳过运行时设置
                if ($this->headerService->isUniqueHeader($normalizedName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 构建运行时特定的头部
     * 子类可以重写此方法来添加特定的头部
     *
     * @param mixed $request 请求对象（可能是不同运行时的请求类型）
     * @return array 运行时头部数组
     */
    protected function buildRuntimeHeaders($request = null): array
    {
        $headers = [];

        // 添加基本的运行时头部
        $headers['Server']       = $this->getName() . '/1.0';
        $headers['X-Powered-By'] = 'ThinkPHP Runtime';

        return $headers;
    }

    /**
     * 获取头部去重服务实例
     *
     * @return HeaderDeduplicationInterface
     */
    protected function getHeaderService(): HeaderDeduplicationInterface
    {
        return $this->headerService;
    }

    /**
     * 设置头部去重服务实例
     *
     * @param HeaderDeduplicationInterface $service 头部去重服务
     * @return void
     */
    public function setHeaderService(HeaderDeduplicationInterface $service): void
    {
        $this->headerService = $service;
    }

    /**
     * 启用或禁用头部调试模式
     *
     * @param bool $enabled 是否启用
     * @return void
     */
    public function setHeaderDebugMode(bool $enabled): void
    {
        $this->headerService->setDebugMode($enabled);
    }

    /**
     * 检查头部冲突
     *
     * @param ResponseInterface $psrResponse PSR-7响应
     * @param array $runtimeHeaders 运行时头部
     * @return array 冲突的头部列表
     */
    protected function detectResponseHeaderConflicts(ResponseInterface $psrResponse, array $runtimeHeaders): array
    {
        $psrHeaders = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $psrHeaders[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $this->headerService->detectHeaderConflicts($psrHeaders, $runtimeHeaders);
    }

    /**
     * 获取公共路径
     * 用于静态文件处理等
     *
     * @return string 公共路径
     */
    protected function getPublicPath(): string
    {
        $publicPath = getcwd() . '/public';

        // 如果public目录不存在，使用当前目录
        if (! is_dir($publicPath)) {
            $publicPath = getcwd();
        }

        return $publicPath;
    }
}
