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
        // 创建模拟应用实例
        $this->app = new class {
            private array $instances = [];

            public function instance(string $name, $instance): void
            {
                $this->instances[$name] = $instance;
            }

            public function make(string $name)
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
        };

        // 设置基本配置
        $config = new class {
            private array $config = [
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

            public function get(string $key, $default = null)
            {
                return $this->config[$key] ?? $default;
            }

            public function set(array $config): void
            {
                $this->config = array_merge($this->config, $config);
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
    }
}
