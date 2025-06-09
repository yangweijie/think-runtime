<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use think\App;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * FPM适配器
 * 提供传统PHP-FPM环境支持
 */
class FpmAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'auto_start' => true,
        'handle_errors' => true,
    ];

    /**
     * 启动适配器
     *
     * @return void
     */
    public function boot(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 初始化应用
        $this->app->initialize();

        // 自动处理请求
        if ($config['auto_start']) {
            $this->handleCurrentRequest();
        }
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        $this->boot();
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
        // FPM环境下无需特殊停止操作
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
        return 'fpm';
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
        return php_sapi_name() === 'fpm-fcgi' ||
               php_sapi_name() === 'cgi-fcgi' ||
               isset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 10; // 最低优先级，作为兜底方案
    }

    /**
     * 处理当前HTTP请求
     *
     * @return void
     */
    protected function handleCurrentRequest(): void
    {
        try {
            // 创建PSR-7请求
            $psr7Request = $this->createPsr7RequestFromGlobals();

            // 处理请求
            $psr7Response = $this->handleRequest($psr7Request);

            // 发送响应
            $this->sendResponse($psr7Response);

        } catch (\Throwable $e) {
            $this->handleFpmError($e);
        }
    }

    /**
     * 从全局变量创建PSR-7请求
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function createPsr7RequestFromGlobals(): \Psr\Http\Message\ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        return $creator->fromGlobals();
    }

    /**
     * 发送响应
     *
     * @param \Psr\Http\Message\ResponseInterface $response PSR-7响应
     * @return void
     */
    protected function sendResponse(\Psr\Http\Message\ResponseInterface $response): void
    {
        // 发送状态码
        http_response_code($response->getStatusCode());

        // 发送响应头
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', (string) $name, $value), false);
            }
        }

        // 发送响应体
        echo (string) $response->getBody();
    }

    /**
     * 处理FPM错误
     *
     * @param \Throwable $e 异常
     * @return void
     */
    protected function handleFpmError(\Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json');

        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
