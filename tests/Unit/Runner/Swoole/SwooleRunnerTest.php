<?php

declare(strict_types=1);

use Think\Runtime\Runner\Swoole\SwooleRunner;
use Think\Runtime\Contract\RunnerInterface;

beforeEach(function () {
    // Reset global state
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'HTTP_HOST' => 'localhost',
    ];
});

it('implements RunnerInterface', function () {
    $app = createMockApp();
    $runner = new SwooleRunner($app);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('throws exception when Swoole is not available', function () {
    $app = createMockApp();
    $runner = new SwooleRunner($app);
    
    // This test will only pass when Swoole is not available
    if (!extension_loaded('swoole')) {
        expect(fn() => $runner->run())
            ->toThrow(RuntimeException::class, 'Swoole extension is not available');
    } else {
        // Skip test if Swoole is available
        expect(true)->toBeTrue();
    }
});

it('accepts options in constructor', function () {
    $app = createMockApp();
    $options = [
        'host' => '127.0.0.1',
        'port' => 8080,
        'worker_num' => 8,
        'enable_coroutine' => false,
        'daemonize' => true,
    ];
    
    $runner = new SwooleRunner($app, $options);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('creates request from Swoole request', function () {
    $app = createMockApp();
    $runner = new SwooleRunner($app);
    
    // Mock Swoole request
    $swooleRequest = new class {
        public $server = [
            'request_method' => 'POST',
            'request_uri' => '/test?param=value',
        ];
        public $get = ['param' => 'value'];
        public $post = ['data' => 'test'];
        public $files = ['upload' => 'file'];
        public $header = ['authorization' => 'Bearer token'];
        public $cookie = ['session' => 'abc123'];
        
        public function rawContent(): string
        {
            return '{"json": "data"}';
        }
    };
    
    // Test the protected createRequest method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('createRequest');
    $method->setAccessible(true);
    
    $request = $method->invoke($runner, $swooleRequest);
    
    expect($request)->toBeArray()
        ->and($request['method'])->toBe('POST')
        ->and($request['uri'])->toBe('/test?param=value')
        ->and($request['query'])->toBe(['param' => 'value'])
        ->and($request['body'])->toBe(['data' => 'test'])
        ->and($request['files'])->toBe(['upload' => 'file'])
        ->and($request['headers'])->toBe(['authorization' => 'Bearer token'])
        ->and($request['cookies'])->toBe(['session' => 'abc123'])
        ->and($request['raw_content'])->toBe('{"json": "data"}');
});

it('sends different response types', function () {
    $app = createMockApp();
    $runner = new SwooleRunner($app);
    
    // Mock Swoole response
    $swooleResponse = new class {
        public $status = 200;
        public $headers = [];
        public $content = '';
        
        public function status(int $code): void
        {
            $this->status = $code;
        }
        
        public function header(string $name, string $value): void
        {
            $this->headers[$name] = $value;
        }
        
        public function end(string $content): void
        {
            $this->content = $content;
        }
    };
    
    // Test the protected sendResponse method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('sendResponse');
    $method->setAccessible(true);
    
    // Test string response
    $method->invoke($runner, $swooleResponse, 'Test Response');
    expect($swooleResponse->content)->toBe('Test Response');
    
    // Reset response
    $swooleResponse->content = '';
    $swooleResponse->headers = [];
    
    // Test array response
    $method->invoke($runner, $swooleResponse, ['message' => 'Hello', 'status' => 'success']);
    expect($swooleResponse->content)->toBe('{"message":"Hello","status":"success"}')
        ->and($swooleResponse->headers['Content-Type'])->toBe('application/json');
});

it('handles exceptions correctly', function () {
    $app = createMockApp();
    $runner = new SwooleRunner($app, ['debug' => true]);
    
    // Mock Swoole response
    $swooleResponse = new class {
        public $status = 200;
        public $headers = [];
        public $content = '';
        
        public function status(int $code): void
        {
            $this->status = $code;
        }
        
        public function header(string $name, string $value): void
        {
            $this->headers[$name] = $value;
        }
        
        public function end(string $content): void
        {
            $this->content = $content;
        }
    };
    
    // Test the protected handleException method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('handleException');
    $method->setAccessible(true);
    
    $exception = new Exception('Test exception');
    $method->invoke($runner, $swooleResponse, $exception);
    
    expect($swooleResponse->status)->toBe(500)
        ->and($swooleResponse->content)->toContain('Error: Test exception')
        ->and($swooleResponse->headers['Content-Type'])->toBe('text/plain');
});

it('handles exceptions in production mode', function () {
    $app = createMockApp();
    $runner = new SwooleRunner($app, ['debug' => false]);
    
    // Mock Swoole response
    $swooleResponse = new class {
        public $status = 200;
        public $content = '';
        
        public function status(int $code): void
        {
            $this->status = $code;
        }
        
        public function header(string $name, string $value): void
        {
            // No-op for this test
        }
        
        public function end(string $content): void
        {
            $this->content = $content;
        }
    };
    
    // Test the protected handleException method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('handleException');
    $method->setAccessible(true);
    
    $exception = new Exception('Test exception');
    $method->invoke($runner, $swooleResponse, $exception);
    
    expect($swooleResponse->status)->toBe(500)
        ->and($swooleResponse->content)->toBe('Internal Server Error');
});
