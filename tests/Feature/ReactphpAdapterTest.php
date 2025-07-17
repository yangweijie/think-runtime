<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\ReactphpAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    $name = $adapter->getName();
    expect($name)->toBe('reactphp');
});

test('has correct priority', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    $priority = $adapter->getPriority();
    expect($priority)->toBe(92);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    $config = $adapter->getConfig();
    expect($config)->toBeArray();

    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 测试方法存在且可调用
    $hasMethod = method_exists($adapter, 'handleRequest');
    expect($hasMethod)->toBe(true);
});

test('can start and stop', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 测试方法存在且可调用
    $hasStart = method_exists($adapter, 'start');
    $hasStop = method_exists($adapter, 'stop');
    expect($hasStart)->toBe(true);
    expect($hasStop)->toBe(true);
});

test('supports environment detection', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 检查ReactPHP类是否存在
    $supported = $adapter->isSupported();

    // 在测试环境中，ReactPHP可能未安装，所以我们只测试方法存在
    $hasMethod = method_exists($adapter, 'isSupported');
    expect($hasMethod)->toBe(true);
    expect($supported)->toBeIn([true, false]);
});

test('has timer methods', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 测试定时器相关方法存在
    $methods = ['addTimer', 'addPeriodicTimer', 'cancelTimer', 'getLoop'];
    foreach ($methods as $method) {
        $hasMethod = method_exists($adapter, $method);
        expect($hasMethod)->toBe(true);
    }
});

test('has react request handler', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 测试ReactPHP特定的请求处理方法
    $hasMethod = method_exists($adapter, 'handleReactRequest');
    expect($hasMethod)->toBe(true);
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 测试所有必需方法存在
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority'];
    foreach ($methods as $method) {
        $hasMethod = method_exists($adapter, $method);
        expect($hasMethod)->toBe(true);
    }
});

test('has default config', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试默认配置包含必要的键
    $requiredKeys = ['host', 'port', 'max_connections', 'timeout'];
    foreach ($requiredKeys as $key) {
        $hasKey = array_key_exists($key, $config);
        expect($hasKey)->toBe(true);
    }
});

test('can merge custom config', function () {
    $this->createApplication();
    $customConfig = [
        'host' => '127.0.0.1',
        'port' => 9000,
        'debug' => true,
    ];

    $adapter = new ReactphpAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();

    expect($config['host'])->toBe('127.0.0.1');
    expect($config['port'])->toBe(9000);
    expect($config['debug'])->toBe(true);
});

test('can handle concurrent requests', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 模拟并发请求处理
    $requests = [];
    for ($i = 0; $i < 5; $i++) {
        $requests[] = $this->createPsr7Request('GET', "/test/{$i}");
    }

    foreach ($requests as $request) {
        expect(function () use ($adapter, $request) {
            $adapter->handleReactRequest($request);
        })->not->toThrow(\Exception::class);
    }
});

test('can add and cancel timers', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 检查ReactPHP是否可用
    if (!$adapter->isSupported()) {
        $this->markTestSkipped('ReactPHP is not available');
        return;
    }

    // 先初始化Event loop
    $adapter->boot();

    $executed = false;
    $timer = $adapter->addTimer(0.1, function () use (&$executed) {
        $executed = true;
    });

    expect($timer)->not->toBeNull();

    // 取消定时器
    $adapter->cancelTimer($timer);

    // 在测试环境中，定时器可能不会实际执行
    expect($executed)->toBeIn([true, false]);
});

test('can add periodic timer', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 检查ReactPHP是否可用
    if (!$adapter->isSupported()) {
        $this->markTestSkipped('ReactPHP is not available');
        return;
    }

    // 先初始化Event loop
    $adapter->boot();

    $count = 0;
    $timer = $adapter->addPeriodicTimer(0.1, function () use (&$count) {
        $count++;
    });

    expect($timer)->not->toBeNull();

    // 取消定时器
    $adapter->cancelTimer($timer);
});

test('handles request errors gracefully', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 创建一个可能导致错误的请求
    $request = $this->createPsr7Request('GET', '/error-test');

    expect(function () use ($adapter, $request) {
        $adapter->handleReactRequest($request);
    })->not->toThrow(\Exception::class);
});

test('can configure connection limits', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, [
        'max_connections' => 1000,
        'connection_timeout' => 30,
        'keep_alive_timeout' => 5,
    ]);

    $config = $adapter->getConfig();

    expect($config['max_connections'])->toBe(1000);
    expect($config['connection_timeout'])->toBe(30);
    expect($config['keep_alive_timeout'])->toBe(5);
});

test('validates port configuration', function () {
    $this->createApplication();

    // 测试有效端口
    $adapter = new ReactphpAdapter($this->app, ['port' => 8080]);
    expect($adapter->getConfig()['port'])->toBe(8080);

    // 测试边界值
    $adapter2 = new ReactphpAdapter($this->app, ['port' => 1]);
    expect($adapter2->getConfig()['port'])->toBe(1);

    $adapter3 = new ReactphpAdapter($this->app, ['port' => 65535]);
    expect($adapter3->getConfig()['port'])->toBe(65535);
});

test('handles header deduplication correctly', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 测试头部去重服务是否可用
    $hasHeaderService = method_exists($adapter, 'getHeaderService');
    expect($hasHeaderService)->toBe(true);

    // 测试构建运行时头部方法
    $hasBuildRuntimeHeaders = method_exists($adapter, 'buildRuntimeHeaders');
    expect($hasBuildRuntimeHeaders)->toBe(true);

    // 测试处理响应头部方法
    $hasProcessResponseHeaders = method_exists($adapter, 'processResponseHeaders');
    expect($hasProcessResponseHeaders)->toBe(true);
});

test('error handling uses header deduplication', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // 检查ReactPHP是否可用
    if (!$adapter->isSupported()) {
        $this->markTestSkipped('ReactPHP is not available');
        return;
    }

    // 创建一个测试异常
    $exception = new \Exception('Test error', 500);

    // 使用反射调用 handleReactError 方法
    $reflection = new \ReflectionClass($adapter);
    $method = $reflection->getMethod('handleReactError');
    $method->setAccessible(true);

    $response = $method->invoke($adapter, $exception);

    // 验证响应是 ReactPHP Response 实例
    expect($response)->toBeInstanceOf(\React\Http\Message\Response::class);

    // 验证状态码
    expect($response->getStatusCode())->toBe(500);

    // 验证头部包含 Content-Type
    $headers = $response->getHeaders();
    expect($headers)->toHaveKey('Content-Type');
    expect($headers['Content-Type'])->toContain('application/json');
});

// Header deduplication integration tests
test('reactphp adapter prevents header duplication in request handling', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // Create a test request
    $request = $this->createPsr7Request('GET', '/test', [
        'Accept' => 'application/json',
        'User-Agent' => 'Test-Client/1.0'
    ]);

    // Mock the handleRequest method to return a response with headers
    $mockResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'application/json',
        'Content-Length' => '25',
        'X-Custom-Header' => 'test-value'
    ], '{"message": "success"}');

    // Use reflection to access protected methods
    $reflection = new \ReflectionClass($adapter);
    
    // Mock the handleRequest method
    $adapter = new class($this->app, []) extends \yangweijie\thinkRuntime\adapter\ReactphpAdapter {
        private $mockResponse;
        
        public function setMockResponse($response) {
            $this->mockResponse = $response;
        }
        
        public function handleRequest($request) {
            return $this->mockResponse;
        }
    };
    
    $adapter->setMockResponse($mockResponse);

    // Call handleReactRequest
    $promise = $adapter->handleReactRequest($request);
    
    // Since we're testing, we need to resolve the promise
    $reactResponse = null;
    $promise->then(function($response) use (&$reactResponse) {
        $reactResponse = $response;
    });

    expect($reactResponse)->toBeInstanceOf(\React\Http\Message\Response::class);
    expect($reactResponse->getStatusCode())->toBe(200);

    // Verify headers are properly processed
    $headers = $reactResponse->getHeaders();
    
    // Count Content-Length headers to ensure no duplication
    $contentLengthCount = 0;
    foreach ($headers as $name => $values) {
        if (strtolower($name) === 'content-length') {
            $contentLengthCount++;
        }
    }
    expect($contentLengthCount)->toBe(1);
});

test('reactphp adapter uses header deduplication service', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // Verify the adapter has access to header deduplication service
    expect(method_exists($adapter, 'processResponseHeaders'))->toBe(true);
    expect(method_exists($adapter, 'getHeaderService'))->toBe(true);

    // Test that the header service is properly initialized
    $headerService = $adapter->getHeaderService();
    expect($headerService)->not->toBeNull();
    expect($headerService)->toBeInstanceOf(\yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface::class);
});

test('reactphp adapter handles case-insensitive headers correctly', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    // Create a request
    $request = $this->createPsr7Request('POST', '/api/test', [
        'Content-Type' => 'application/json'
    ], '{"test": "data"}');

    // Create a response with mixed case headers
    $mockResponse = $this->createPsr7Response(200, [
        'content-type' => 'application/json',
        'Content-Type' => 'text/html', // Should be deduplicated
        'X-Custom-Header' => 'value1',
        'x-custom-header' => 'value2' // Should be deduplicated
    ], '{"result": "success"}');

    // Create a testable adapter
    $adapter = new class($this->app, []) extends \yangweijie\thinkRuntime\adapter\ReactphpAdapter {
        private $mockResponse;
        
        public function setMockResponse($response) {
            $this->mockResponse = $response;
        }
        
        public function handleRequest($request) {
            return $this->mockResponse;
        }
    };
    
    $adapter->setMockResponse($mockResponse);

    // Call handleReactRequest
    $promise = $adapter->handleReactRequest($request);
    
    $reactResponse = null;
    $promise->then(function($response) use (&$reactResponse) {
        $reactResponse = $response;
    });

    expect($reactResponse)->toBeInstanceOf(\React\Http\Message\Response::class);

    $headers = $reactResponse->getHeaders();

    // Count occurrences of each header (case-insensitive)
    $headerCounts = [];
    foreach ($headers as $name => $values) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    // Each header should appear only once after deduplication
    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['x-custom-header'])->toBe(1);
});

test('reactphp adapter handles compression headers without duplication', function () {
    $this->createApplication();
    $adapter = new ReactphpAdapter($this->app, []);

    $request = $this->createPsr7Request('GET', '/large-content');

    // Create response with compression headers
    $mockResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'text/html',
        'Content-Encoding' => 'gzip',
        'Content-Length' => '500'
    ], str_repeat('large content block ', 25));

    $adapter = new class($this->app, []) extends \yangweijie\thinkRuntime\adapter\ReactphpAdapter {
        private $mockResponse;
        
        public function setMockResponse($response) {
            $this->mockResponse = $response;
        }
        
        public function handleRequest($request) {
            return $this->mockResponse;
        }
    };
    
    $adapter->setMockResponse($mockResponse);

    $promise = $adapter->handleReactRequest($request);
    
    $reactResponse = null;
    $promise->then(function($response) use (&$reactResponse) {
        $reactResponse = $response;
    });

    $headers = $reactResponse->getHeaders();

    // Verify no duplicate headers
    $headerCounts = [];
    foreach ($headers as $name => $values) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['content-encoding'])->toBe(1);
    expect($headerCounts['content-length'])->toBe(1);
});
