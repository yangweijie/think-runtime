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
            // 在常驻内存运行时中，需要在每次请求前重置全局状态
            $this->resetGlobalState();

            // 重置 ThinkPHP 应用状态
            $this->resetThinkPHPState();

            // 将PSR-7请求转换为ThinkPHP请求
            $thinkRequest = $this->convertPsr7ToThinkRequest($request);

            // 处理请求
            $thinkResponse = $this->app->http->run($thinkRequest);

            // 将ThinkPHP响应转换为PSR-7响应
            return $this->convertThinkResponseToPsr7($thinkResponse);

        } catch (Throwable $e) {
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
            if (str_contains($headers['content-type'] ?? '', 'application/json')) {
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
        return new Psr7Response(
            $response->getCode(),
            $response->getHeader(),
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

    /**
     * 重置全局状态
     * 在常驻内存运行时中，每次请求前需要重置全局变量以避免状态污染
     *
     * @return void
     */
    protected function resetGlobalState(): void
    {
        // 重置超全局变量
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
        $_REQUEST = [];

        // 保留必要的 $_SERVER 变量，重置HTTP相关的变量
        $preserveKeys = [
            'PHP_SELF', 'SCRIPT_NAME', 'SCRIPT_FILENAME', 'SERVER_ADMIN',
            'SERVER_PORT', 'SERVER_SIGNATURE', 'PATH_TRANSLATED', 'DOCUMENT_ROOT',
            'SERVER_SOFTWARE', 'SERVER_NAME', 'SERVER_ADDR', 'REMOTE_ADDR',
            'REMOTE_HOST', 'REMOTE_PORT', 'REMOTE_USER', 'REDIRECT_REMOTE_USER',
            'HTTPS', 'SERVER_PROTOCOL', 'REQUEST_TIME', 'REQUEST_TIME_FLOAT',
            'ARGC', 'ARGV', 'PATH', 'SystemRoot', 'COMSPEC', 'PATHEXT',
            'WINDIR', 'SERVER_SIGNATURE', 'SERVER_SOFTWARE', 'SERVER_NAME',
            'SERVER_ADDR', 'SERVER_PORT', 'REMOTE_ADDR', 'DOCUMENT_ROOT',
            'REQUEST_SCHEME', 'CONTEXT_PREFIX', 'CONTEXT_DOCUMENT_ROOT'
        ];

        $preserved = [];
        foreach ($preserveKeys as $key) {
            if (isset($_SERVER[$key])) {
                $preserved[$key] = $_SERVER[$key];
            }
        }

        // 清理所有HTTP_*头信息和请求相关变量
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') ||
                in_array($key, ['REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING', 'CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                unset($_SERVER[$key]);
            }
        }

        // 恢复保留的变量
        $_SERVER = array_merge($_SERVER, $preserved);

        // 重置 ThinkPHP 相关的静态状态（如果需要）
        if (class_exists('\think\facade\App')) {
            // 重置应用实例的请求状态
            $this->app->request = null;
        }

        // 重置内存使用统计
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
    }

    /**
     * 重置 ThinkPHP 应用状态
     * 重置应用实例的内部状态，包括调试信息、性能统计等
     *
     * @return void
     */
    protected function resetThinkPHPState(): void
    {
        // 重置应用的请求实例
        $this->app->request = null;

        // 重置应用的响应实例
        if (property_exists($this->app, 'response')) {
            $this->app->response = null;
        }

        // 重置日志状态
        if (class_exists('\think\facade\Log')) {
            try {
                $logClass = new ReflectionClass('\think\facade\Log');
                if ($logClass->hasMethod('clear')) {
                    Log::clear();
                }
            } catch (Throwable $e) {
                // 忽略错误
            }
        }

        // 重置应用的容器绑定（保留核心服务）
        $preserveServices = [
            'app', 'config', 'cache', 'db', 'session', 'cookie', 'view', 'template',
            'runtime.manager', 'runtime.config'
        ];

        if (method_exists($this->app, 'getContainer')) {
            $container = $this->app->getContainer();
            if (method_exists($container, 'getBindings')) {
                $bindings = $container->getBindings();
                foreach ($bindings as $abstract => $binding) {
                    if (!in_array($abstract, $preserveServices) &&
                        !str_starts_with($abstract, 'think\\') &&
                        !str_starts_with($abstract, 'app\\')) {
                        try {
                            $container->delete($abstract);
                        } catch (Throwable $e) {
                            // 忽略删除错误
                        }
                    }
                }
            }
        }

        // 重置静态变量（如果存在）
        $this->resetStaticVariables();
    }

    /**
     * 重置静态变量
     * 尝试重置可能影响调试信息的静态变量
     *
     * @return void
     */
    protected function resetStaticVariables(): void
    {
        // 重置可能的调试相关静态变量
        $debugClasses = [
            '\think\Debug',
            '\think\debug\Html',
            '\think\debug\Console',
            '\think\Trace',
            '\think\trace\Html',
            '\think\trace\Console',
            '\think\Log',
            '\think\log\Channel'
        ];

        foreach ($debugClasses as $className) {
            if (class_exists($className)) {
                try {
                    $reflection = new ReflectionClass($className);
                    $properties = $reflection->getStaticProperties();

                    foreach ($properties as $name => $value) {
                        // 重置可能的时间统计变量
                        if (str_contains($name, 'time') ||
                            str_contains($name, 'start') ||
                            str_contains($name, 'end') ||
                            str_contains($name, 'log') ||
                            str_contains($name, 'trace') ||
                            str_contains($name, 'debug')) {

                            $property = $reflection->getProperty($name);
                            if ($property->isStatic() && $property->isPublic()) {
                                if (is_array($value)) {
                                    $property->setValue([]);
                                } elseif (is_numeric($value)) {
                                    $property->setValue(0);
                                } elseif (is_string($value)) {
                                    $property->setValue('');
                                } elseif (is_bool($value)) {
                                    $property->setValue(false);
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    // 忽略反射错误
                }
            }
        }

        // 重置 REQUEST_TIME 相关常量
        $_SERVER['REQUEST_TIME'] = time();
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        if (!defined('REQUEST_TIME_RESET')) {
            define('REQUEST_TIME_RESET', true);
        }
    }
}
