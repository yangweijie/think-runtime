<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use yangweijie\thinkRuntime\config\RuntimeConfig;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

/**
 * 测试基类
 * 提供测试所需的基础设施
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * 模拟应用实例
     *
     * @var object
     */
    protected $app;

    /**
     * 运行时配置
     *
     * @var RuntimeConfig
     */
    protected RuntimeConfig $runtimeConfig;

    /**
     * 运行时管理器
     *
     * @var RuntimeManager
     */
    protected RuntimeManager $runtimeManager;

    /**
     * 设置测试环境
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 只在需要时创建组件
    }

    /**
     * 清理测试环境
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * 创建应用实例
     *
     * @return void
     */
    protected function createApplication(): void
    {
        // 创建模拟应用实例，继承自think\App以满足类型要求
        $this->app = new class extends \think\App {
            protected $instances = [];

            public function __construct()
            {
                // 不调用父类构造函数，避免复杂的初始化
            }

            public function instance(string $name, $instance): void
            {
                $this->instances[$name] = $instance;
            }

            public function make(string $name, array $args = [], bool $newInstance = false)
            {
                return $this->instances[$name] ?? null;
            }

            public function initialize(): void
            {
                // 模拟初始化
            }

            public function __get($name)
            {
                return $this->instances[$name] ?? null;
            }

            public function isDebug(): bool
            {
                return true;
            }

            public function getRootPath(): string
            {
                return __DIR__ . '/../';
            }

            public function getBasePath(): string
            {
                return __DIR__ . '/../';
            }

            public function getAppPath(): string
            {
                return __DIR__ . '/../app/';
            }

            public function getConfigPath(): string
            {
                return __DIR__ . '/../config/';
            }

            public function getRuntimePath(): string
            {
                return __DIR__ . '/../runtime/';
            }

            public function getThinkPath(): string
            {
                return dirname(__DIR__) . '/vendor/topthink/framework/src/';
            }

            public function getNamespace(): string
            {
                return 'app';
            }
        };

        // 设置基本配置
        $config = new class extends \think\Config {
            protected $config = [
                'app' => [
                    'debug' => true,
                    'trace' => false,
                ],
                'runtime' => [
                    'default' => 'fpm',
                    'runtimes' => [
                        'fpm' => [
                            'auto_start' => false,
                        ],
                    ],
                ],
            ];

            public function __construct()
            {
                // 不调用父类构造函数
            }

            public function get(string $name = null, $default = null)
            {
                if (is_null($name)) {
                    return $this->config;
                }

                return $this->config[$name] ?? $default;
            }

            public function set(array $config, string $name = null): array
            {
                if (is_null($name)) {
                    $this->config = array_merge($this->config, $config);
                } else {
                    $this->config[$name] = $config;
                }
                return $this->config;
            }

            public function has(string $name): bool
            {
                return isset($this->config[$name]);
            }

            public function pull(string $name): array
            {
                $value = $this->get($name, []);
                unset($this->config[$name]);
                return is_array($value) ? $value : [];
            }
        };

        $this->app->instance('config', $config);
    }

    /**
     * 创建运行时配置
     *
     * @return void
     */
    protected function createRuntimeConfig(): void
    {
        $config = $this->app->config->get('runtime', []);
        $this->runtimeConfig = new RuntimeConfig($config);
    }

    /**
     * 创建运行时管理器
     *
     * @return void
     */
    protected function createRuntimeManager(): void
    {
        $this->runtimeManager = new RuntimeManager($this->app, $this->runtimeConfig);
    }

    /**
     * 获取测试用的PSR-7请求
     *
     * @param string $method HTTP方法
     * @param string $uri URI
     * @param array $headers 请求头
     * @param string|null $body 请求体
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function createPsr7Request(
        string $method = 'GET',
        string $uri = '/',
        array $headers = [],
        ?string $body = null
    ): \Psr\Http\Message\ServerRequestInterface {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();

        $request = $psr17Factory->createServerRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $stream = $psr17Factory->createStream($body);
            $request = $request->withBody($stream);
        }

        return $request;
    }

    /**
     * 模拟Swoole环境
     *
     * @return void
     */
    protected function mockSwooleEnvironment(): void
    {
        if (!defined('SWOOLE_PROCESS')) {
            define('SWOOLE_PROCESS', 3);
        }
        if (!defined('SWOOLE_SOCK_TCP')) {
            define('SWOOLE_SOCK_TCP', 1);
        }
    }

    /**
     * 模拟RoadRunner环境
     *
     * @return void
     */
    protected function mockRoadRunnerEnvironment(): void
    {
        $_SERVER['RR_MODE'] = 'http';
    }

    /**
     * 清理环境变量
     *
     * @return void
     */
    protected function cleanEnvironment(): void
    {
        unset($_SERVER['RR_MODE']);
        unset($_SERVER['FRANKENPHP_VERSION']);
    }

    /**
     * 创建测试用的HTTP响应
     *
     * @param int $statusCode 状态码
     * @param array $headers 响应头
     * @param string $body 响应体
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function createPsr7Response(
        int $statusCode = 200,
        array $headers = [],
        string $body = ''
    ): \Psr\Http\Message\ResponseInterface {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();

        $response = $psr17Factory->createResponse($statusCode);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        if (!empty($body)) {
            $stream = $psr17Factory->createStream($body);
            $response = $response->withBody($stream);
        }

        return $response;
    }

    /**
     * 断言PSR-7响应
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param int $expectedStatusCode
     * @param array $expectedHeaders
     * @return void
     */
    protected function assertPsr7Response(
        \Psr\Http\Message\ResponseInterface $response,
        int $expectedStatusCode = 200,
        array $expectedHeaders = []
    ): void {
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());

        foreach ($expectedHeaders as $name => $value) {
            $this->assertTrue($response->hasHeader($name));
            $this->assertEquals($value, $response->getHeaderLine($name));
        }
    }

    /**
     * 创建测试用的配置数组
     *
     * @param array $overrides 覆盖的配置
     * @return array
     */
    protected function createTestConfig(array $overrides = []): array
    {
        $defaultConfig = [
            'default' => 'auto',
            'auto_detect_order' => ['swoole', 'frankenphp', 'reactphp', 'ripple', 'roadrunner', 'fpm'],
            'runtimes' => [
                'swoole' => [
                    'host' => '0.0.0.0',
                    'port' => 9501,
                    'worker_num' => 4,
                    'daemonize' => false,
                ],
                'frankenphp' => [
                    'listen' => ':9000',
                    'worker_num' => 4,
                ],
                'reactphp' => [
                    'host' => '0.0.0.0',
                    'port' => 8080,
                    'max_connections' => 1000,
                ],
                'ripple' => [
                    'host' => '0.0.0.0',
                    'port' => 8080,
                    'worker_num' => 4,
                ],
                'roadrunner' => [
                    'workers' => 4,
                    'max_jobs' => 1000,
                ],
                'fpm' => [
                    'auto_start' => false,
                ],
            ],
        ];

        return array_merge_recursive($defaultConfig, $overrides);
    }

    /**
     * 模拟不同的运行时环境
     *
     * @param string $runtime 运行时名称
     * @return void
     */
    protected function mockRuntimeEnvironment(string $runtime): void
    {
        switch ($runtime) {
            case 'swoole':
                $this->mockSwooleEnvironment();
                break;
            case 'frankenphp':
                $_SERVER['FRANKENPHP_VERSION'] = '1.0.0';
                break;
            case 'roadrunner':
                $this->mockRoadRunnerEnvironment();
                break;
            case 'reactphp':
                // ReactPHP通过类存在性检测
                break;
            case 'ripple':
                // Ripple通过PHP版本和类存在性检测
                break;
        }
    }

    /**
     * 创建测试用的异常
     *
     * @param string $message 异常消息
     * @param int $code 异常代码
     * @return \Exception
     */
    protected function createTestException(string $message = 'Test exception', int $code = 500): \Exception
    {
        return new \Exception($message, $code);
    }

    /**
     * 断言数组包含指定的键
     *
     * @param array $array 要检查的数组
     * @param array $keys 期望的键
     * @return void
     */
    protected function assertArrayHasKeys(array $array, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, "Array should have key: {$key}");
        }
    }

    /**
     * 断言方法存在
     *
     * @param object $object 对象实例
     * @param array $methods 方法名数组
     * @return void
     */
    protected function assertMethodsExist(object $object, array $methods): void
    {
        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($object, $method),
                "Method {$method} should exist in " . get_class($object)
            );
        }
    }

    /**
     * 获取对象的私有或受保护属性值
     *
     * @param object $object 对象实例
     * @param string $propertyName 属性名
     * @return mixed
     */
    protected function getPrivateProperty(object $object, string $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * 设置对象的私有或受保护属性值
     *
     * @param object $object 对象实例
     * @param string $propertyName 属性名
     * @param mixed $value 属性值
     * @return void
     */
    protected function setPrivateProperty(object $object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * 调用对象的私有或受保护方法
     *
     * @param object $object 对象实例
     * @param string $methodName 方法名
     * @param array $args 参数数组
     * @return mixed
     */
    protected function callPrivateMethod(object $object, string $methodName, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
