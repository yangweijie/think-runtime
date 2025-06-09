<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\runtime;

use think\App;
use think\Request;
use think\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
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
     * @param App|object $app ThinkPHP应用实例
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
            // 将PSR-7请求转换为ThinkPHP请求
            $thinkRequest = $this->convertPsr7ToThinkRequest($request);

            // 处理请求
            $thinkResponse = $this->app->http->run($thinkRequest);

            // 将ThinkPHP响应转换为PSR-7响应
            return $this->convertThinkResponseToPsr7($thinkResponse);

        } catch (\Throwable $e) {
            // 错误处理
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
     * @param ServerRequestInterface $request PSR-7请求
     * @return Request ThinkPHP请求
     */
    protected function convertPsr7ToThinkRequest(ServerRequestInterface $request): Request
    {
        $server = [];
        $headers = [];

        // 转换请求头
        foreach ($request->getHeaders() as $name => $values) {
            $headerName = (string) $name;
            $headers[$headerName] = implode(', ', $values);
            $server['HTTP_' . strtoupper(str_replace('-', '_', $headerName))] = $headers[$headerName];
        }

        // 设置基本服务器变量
        $server['REQUEST_METHOD'] = $request->getMethod();
        $server['REQUEST_URI'] = (string) $request->getUri();
        $server['SERVER_PROTOCOL'] = 'HTTP/' . $request->getProtocolVersion();
        $server['QUERY_STRING'] = $request->getUri()->getQuery();

        // 创建ThinkPHP请求
        // 注意：这里需要根据实际的ThinkPHP版本调整Request创建方式
        $thinkRequest = $this->app->request;

        // 如果没有现有请求，创建新的
        if (!$thinkRequest) {
            $thinkRequest = new Request();
        }

        // 设置服务器变量
        foreach ($server as $key => $value) {
            $_SERVER[$key] = $value;
        }

        // 设置请求头
        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        // 设置POST数据
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody)) {
            $_POST = $parsedBody;
        }

        // 设置GET数据
        parse_str($request->getUri()->getQuery(), $queryParams);
        $_GET = $queryParams;

        // 设置请求体
        $body = (string) $request->getBody();
        if (!empty($body)) {
            // 对于JSON请求，可能需要特殊处理
            if (strpos($headers['content-type'] ?? '', 'application/json') !== false) {
                $jsonData = json_decode($body, true);
                if (is_array($jsonData)) {
                    $_POST = array_merge($_POST, $jsonData);
                }
            }
        }

        return $thinkRequest;
    }

    /**
     * 将ThinkPHP响应转换为PSR-7响应
     *
     * @param Response $response ThinkPHP响应
     * @return ResponseInterface PSR-7响应
     */
    protected function convertThinkResponseToPsr7(Response $response): ResponseInterface
    {
        $psr7Response = new Psr7Response(
            $response->getCode(),
            $response->getHeader(),
            $response->getContent()
        );

        return $psr7Response;
    }

    /**
     * 处理错误
     *
     * @param \Throwable $e 异常
     * @return ResponseInterface PSR-7响应
     */
    protected function handleError(\Throwable $e): ResponseInterface
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
