<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\runtime;

use ReflectionClass;
use think\App;
use think\facade\Debug;
use think\facade\Log;
use think\facade\Trace;
use think\Request;
use think\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use Throwable;
use yangweijie\thinkRuntime\contract\RuntimeInterface;

/**
 * 抽象运行时基类
 * 提供运行时的通用实现
 */
abstract class AbstractRuntime implements RuntimeInterface
{
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
     * 构造函数
     *
     * @param array $config 配置数组
     */
    public function __construct($app, array $config = [])
    {
        $this->app = $app;
        $this->config = $config;
        $this->psr17Factory = new Psr17Factory();
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
            if (!$this->app->initialized()) {
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
        $method = $psrRequest->getMethod();
        $uri = $psrRequest->getUri();
        $headers = $psrRequest->getHeaders();

        // 构建 ThinkPHP 请求对象
        $request = $this->app->make('think\Request', [
            'path' => $uri->getPath(),
            'host' => $uri->getHost(),
            'method' => $method,
            'headers' => $headers,
            'server' => $psrRequest->getServerParams(),
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
        if (!is_array($headers)) {
            $headers = [];
        }

        // 设置默认的 Content-Type 如果未设置
        if (empty($headers['Content-Type']) && empty($headers['content-type'])) {
            $headers['Content-Type'] = 'text/html; charset=utf-8';
        }

        return new Psr7Response(
            $response->getCode(),
            $headers,
            $response->getContent()
        );
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
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);

        return new Psr7Response(500, ['Content-Type' => 'application/json'], $content);
    }
}
