<?php

declare(strict_types=1);

namespace Think\Runtime\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset global state before each test
        $this->resetGlobalState();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->resetGlobalState();
        
        parent::tearDown();
    }

    /**
     * Reset global state to prevent test interference.
     */
    protected function resetGlobalState(): void
    {
        // Reset superglobals
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];
        $_SESSION = [];
        
        // Reset SERVER variables to minimal state
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'SCRIPT_FILENAME' => __FILE__,
        ];
        
        // Reset ENV
        $_ENV = [];
    }

    /**
     * Create a mock ThinkPHP App instance.
     */
    protected function createMockApp(): object
    {
        return new class {
            private array $config = [];
            
            public function handle($request)
            {
                return new class {
                    public function send(): void
                    {
                        echo 'Hello World';
                    }
                    
                    public function getContent(): string
                    {
                        return 'Hello World';
                    }
                    
                    public function getStatusCode(): int
                    {
                        return 200;
                    }
                    
                    public function getHeaders(): array
                    {
                        return [];
                    }
                };
            }
            
            public function getEnvironment(): string
            {
                return 'test';
            }
            
            public function isDebug(): bool
            {
                return true;
            }
            
            public function terminate($request, $response): void
            {
                // Mock terminate
            }
            
            public function setAppPath(string $path): void
            {
                $this->config['app_path'] = $path;
            }
            
            public function setRuntimePath(string $path): void
            {
                $this->config['runtime_path'] = $path;
            }
            
            public function setConfigPath(string $path): void
            {
                $this->config['config_path'] = $path;
            }
            
            public function config(): object
            {
                return new class($this->config) {
                    private array $config;
                    
                    public function __construct(array &$config)
                    {
                        $this->config = &$config;
                    }
                    
                    public function set(array $config): void
                    {
                        $this->config = array_merge($this->config, $config);
                    }
                    
                    public function get(string $key, $default = null)
                    {
                        return $this->config[$key] ?? $default;
                    }
                };
            }
        };
    }

    /**
     * Create a mock console application.
     */
    protected function createMockConsole(): object
    {
        return new class {
            public function run(): int
            {
                return 0;
            }
            
            public function add($command): void
            {
                // Mock add command
            }
        };
    }

    /**
     * Create a mock callable that returns an application.
     */
    protected function createMockCallable(): callable
    {
        return function (array $context = []) {
            return $this->createMockApp();
        };
    }

    /**
     * Set up environment variables for testing.
     */
    protected function setEnvironment(array $env): void
    {
        foreach ($env as $key => $value) {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
        }
    }
}
