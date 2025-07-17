<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\SwooleAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    expect($adapter->getName())->toBe('swoole');
});

test('has correct priority', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    expect($adapter->getPriority())->toBe(100);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    $config = $adapter->getConfig();
    expect($config)->toBeArray();

    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('supports environment detection', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    // 检查Swoole扩展是否存在
    $supported = $adapter->isSupported();
    $hasExtension = extension_loaded('swoole');

    expect($supported)->toBe($hasExtension);
});

test('has default config', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试默认配置包含必要的键
    $requiredKeys = ['host', 'port', 'worker_num', 'task_worker_num', 'max_request', 'daemonize'];
    foreach ($requiredKeys as $key) {
        expect($config)->toHaveKey($key);
    }

    // 测试默认值
    expect($config['host'])->toBe('0.0.0.0');
    expect($config['port'])->toBe(9501);
    expect($config['worker_num'])->toBe(4);
    expect($config['daemonize'])->toBeIn([false, 0]); // Swoole使用0/1而不是true/false
});

test('can merge custom config', function () {
    $this->createApplication();
    $customConfig = [
        'host' => '127.0.0.1',
        'port' => 8080,
        'worker_num' => 8,
        'debug' => true,
    ];

    $adapter = new SwooleAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();

    expect($config['host'])->toBe('127.0.0.1');
    expect($config['port'])->toBe(8080);
    expect($config['worker_num'])->toBe(8);
    expect($config['debug'])->toBe(true);
});

test('has swoole specific methods', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    // 测试Swoole特定方法存在
    $methods = ['onWorkerStart', 'onRequest', 'onTask', 'onFinish', 'getSwooleServer'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    // 测试方法存在且可调用
    expect(method_exists($adapter, 'handleRequest'))->toBe(true);
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    // 测试所有必需方法存在
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('has swoole server configuration', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试Swoole服务器配置
    expect($config)->toHaveKey('enable_coroutine');
    expect($config)->toHaveKey('max_coroutine');
    expect($config)->toHaveKey('socket_buffer_size');
    expect($config['enable_coroutine'])->toBeIn([true, 1]); // Swoole使用0/1而不是true/false
    expect($config['max_coroutine'])->toBe(100000);
});

test('can configure task workers', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, [
        'task_worker_num' => 8,
        'task_enable_coroutine' => true,
    ]);

    $config = $adapter->getConfig();

    expect($config['task_worker_num'])->toBe(8);
    expect($config['task_enable_coroutine'])->toBe(true);
});

test('can configure ssl', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, [
        'ssl_cert_file' => '/path/to/cert.pem',
        'ssl_key_file' => '/path/to/key.pem',
    ]);

    $config = $adapter->getConfig();

    expect($config['ssl_cert_file'])->toBe('/path/to/cert.pem');
    expect($config['ssl_key_file'])->toBe('/path/to/key.pem');
});

// Header deduplication integration tests
test('swoole adapter prevents header duplication in response sending', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    // Skip test if Swoole is not available
    if (!extension_loaded('swoole')) {
        $this->markTestSkipped('Swoole extension is not available');
        return;
    }

    // Create a mock that implements the interface we need
    $swooleResponse = new class {
        private array $headers = [];
        private int $statusCode = 200;

        public function status(int $code): void {
            $this->statusCode = $code;
        }

        public function header(string $name, string $value): void {
            $this->headers[$name] = $value;
        }

        public function getHeaders(): array {
            return $this->headers;
        }

        public function getStatusCode(): int {
            return $this->statusCode;
        }

        public function end(string $content = ''): void {
            // Mock implementation
        }
    };

    // Since we can't easily mock Swoole\Http\Response, let's test the processResponseHeaders method instead
    $psrResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'application/json',
        'Content-Length' => '25',
        'X-Custom-Header' => 'test-value'
    ], '{"message": "success"}');

    // Test the header processing directly
    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    // Verify no duplicate Content-Length headers
    $contentLengthCount = 0;
    foreach ($finalHeaders as $name => $value) {
        if (strtolower($name) === 'content-length') {
            $contentLengthCount++;
        }
    }
    expect($contentLengthCount)->toBe(1);

    // Verify headers are properly processed
    expect($finalHeaders)->toBeArray();
    
    // Find the headers in the final array (case-insensitive)
    $hasContentType = false;
    $hasCustomHeader = false;
    foreach ($finalHeaders as $name => $value) {
        if (strtolower($name) === 'content-type') {
            $hasContentType = true;
        }
        if (strtolower($name) === 'x-custom-header') {
            $hasCustomHeader = true;
        }
    }
    expect($hasContentType)->toBe(true);
    expect($hasCustomHeader)->toBe(true);

    // Create PSR-7 response with headers
    $psrResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'application/json',
        'Content-Length' => '25',
        'X-Custom-Header' => 'test-value'
    ], '{"message": "success"}');

    // Use reflection to call the protected method
    $reflection = new \ReflectionClass($adapter);
    $method = $reflection->getMethod('sendSwooleResponse');
    $method->setAccessible(true);

    $method->invoke($adapter, $swooleResponse, $psrResponse);

    $headers = $swooleResponse->getHeaders();

    // Verify no duplicate Content-Length headers
    $contentLengthCount = 0;
    foreach ($headers as $name => $value) {
        if (strtolower($name) === 'content-length') {
            $contentLengthCount++;
        }
    }
    expect($contentLengthCount)->toBe(1);

    // Verify headers are properly set
    expect($headers)->toHaveKey('Content-Type');
    expect($headers)->toHaveKey('X-Custom-Header');
    expect($headers['X-Custom-Header'])->toBe('test-value');
});

test('swoole adapter handles case-insensitive header deduplication', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    // Mock Swoole Response
    $swooleResponse = new class {
        private array $headers = [];

        public function status(int $code): void {}

        public function header(string $name, string $value): void {
            $this->headers[$name] = $value;
        }

        public function getHeaders(): array {
            return $this->headers;
        }

        public function end(string $content = ''): void {}
    };

    // Create PSR-7 response with mixed case headers
    $psrResponse = $this->createPsr7Response(200, [
        'content-type' => 'application/json',
        'Content-Type' => 'text/html', // Should be deduplicated
        'X-Custom-Header' => 'value1',
        'x-custom-header' => 'value2' // Should be deduplicated
    ], '{"test": true}');

    $reflection = new \ReflectionClass($adapter);
    $method = $reflection->getMethod('sendSwooleResponse');
    $method->setAccessible(true);

    $method->invoke($adapter, $swooleResponse, $psrResponse);

    $headers = $swooleResponse->getHeaders();

    // Count occurrences of each header (case-insensitive)
    $headerCounts = [];
    foreach ($headers as $name => $value) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    // Each header should appear only once after deduplication
    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['x-custom-header'])->toBe(1);
});

test('swoole adapter uses header deduplication service', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    // Verify the adapter has access to header deduplication service
    expect(method_exists($adapter, 'processResponseHeaders'))->toBe(true);
    expect(method_exists($adapter, 'getHeaderService'))->toBe(true);

    // Test that the header service is properly initialized
    $headerService = $adapter->getHeaderService();
    expect($headerService)->not->toBeNull();
    expect($headerService)->toBeInstanceOf(\yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface::class);
});

test('swoole adapter handles compression headers correctly', function () {
    $this->createApplication();
    $adapter = new SwooleAdapter($this->app, []);

    // Mock Swoole Response
    $swooleResponse = new class {
        private array $headers = [];

        public function status(int $code): void {}

        public function header(string $name, string $value): void {
            $this->headers[$name] = $value;
        }

        public function getHeaders(): array {
            return $this->headers;
        }

        public function end(string $content = ''): void {}
    };

    // Create PSR-7 response with compression headers
    $psrResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'text/html',
        'Content-Encoding' => 'gzip',
        'Content-Length' => '100'
    ], str_repeat('test content ', 10));

    $reflection = new \ReflectionClass($adapter);
    $method = $reflection->getMethod('sendSwooleResponse');
    $method->setAccessible(true);

    $method->invoke($adapter, $swooleResponse, $psrResponse);

    $headers = $swooleResponse->getHeaders();

    // Verify no duplicate headers
    $headerCounts = [];
    foreach ($headers as $name => $value) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['content-encoding'])->toBe(1);
    expect($headerCounts['content-length'])->toBe(1);
});
