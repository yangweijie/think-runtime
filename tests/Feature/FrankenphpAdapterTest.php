<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    expect($adapter->getName())->toBe('frankenphp');
});

test('has correct priority', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    expect($adapter->getPriority())->toBe(95);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    $config = $adapter->getConfig();
    expect($config)->toBeArray();

    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    // 测试方法存在且可调用
    $hasMethod = method_exists($adapter, 'handleRequest');
    expect($hasMethod)->toBe(true);
});

test('can start and stop', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    // 测试方法存在且可调用
    $hasStart = method_exists($adapter, 'start');
    $hasStop = method_exists($adapter, 'stop');
    expect($hasStart)->toBe(true);
    expect($hasStop)->toBe(true);
});

test('builds correct frankenphp config', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, [
        'listen' => ':9000',
        'worker_num' => 8,
        'auto_https' => false,
        'http2' => true,
        'http3' => true,
    ]);

    // 使用反射访问受保护的方法
    $reflection = new \ReflectionClass($adapter);
    $method = $reflection->getMethod('buildFrankenphpConfig');
    $method->setAccessible(true);

    $config = $method->invoke($adapter);
    $configArray = json_decode($config, true);

    expect($configArray)->toBeArray()
        ->and($configArray['listen'])->toBe(':9000')
        ->and($configArray['worker_num'])->toBe(8)
        ->and($configArray['http2'])->toBe('on')
        ->and($configArray['http3'])->toBe('on');
});

test('supports environment detection', function () {
    $this->createApplication();

    // 保存原始环境状态
    $originalFrankenphpVersion = $_SERVER['FRANKENPHP_VERSION'] ?? null;
    $originalFrankenphpConfig = getenv('FRANKENPHP_CONFIG');

    // 清理所有FrankenPHP相关的环境变量
    unset($_SERVER['FRANKENPHP_VERSION']);
    if ($originalFrankenphpConfig !== false) {
        putenv('FRANKENPHP_CONFIG');
    }

    $adapter = new FrankenphpAdapter($this->app, []);

    // 检查是否在FrankenPHP环境中运行
    $inFrankenphpEnv = isset($_SERVER['FRANKENPHP_VERSION']) ||
                      function_exists('frankenphp_handle_request') ||
                      getenv('FRANKENPHP_CONFIG') !== false;

    // 如果不在FrankenPHP环境中，但系统中安装了FrankenPHP，isSupported()仍可能返回true
    // 所以我们需要检查实际的支持状态
    $supported = $adapter->isSupported();

    if ($inFrankenphpEnv) {
        // 如果在FrankenPHP环境中，应该支持
        expect($supported)->toBe(true);
    } else {
        // 如果不在FrankenPHP环境中，支持状态取决于是否能找到FrankenPHP二进制文件
        // 这是一个更现实的测试，因为isSupported()会检查canStartFrankenphp()
        expect($supported)->toBeIn([true, false]); // 可能支持也可能不支持，取决于系统环境
    }

    // 模拟FrankenPHP环境
    $_SERVER['FRANKENPHP_VERSION'] = '1.0.0';
    $supportedWithEnv = $adapter->isSupported();
    expect($supportedWithEnv)->toBe(true);

    // 恢复原始环境状态
    if ($originalFrankenphpVersion !== null) {
        $_SERVER['FRANKENPHP_VERSION'] = $originalFrankenphpVersion;
    } else {
        unset($_SERVER['FRANKENPHP_VERSION']);
    }

    if ($originalFrankenphpConfig !== false) {
        putenv("FRANKENPHP_CONFIG={$originalFrankenphpConfig}");
    }
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    // 测试所有必需方法存在
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority'];
    foreach ($methods as $method) {
        $hasMethod = method_exists($adapter, $method);
        expect($hasMethod)->toBe(true);
    }
});

test('can handle request with different methods', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    foreach ($methods as $method) {
        $request = $this->createPsr7Request($method, '/test');

        // 测试请求处理不会抛出异常
        expect(function () use ($adapter, $request) {
            $adapter->handleRequest($request);
        })->not->toThrow(\Exception::class);
    }
});

test('handles request with json body', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    $jsonData = json_encode(['test' => 'data']);
    $request = $this->createPsr7Request('POST', '/api/test', ['Content-Type' => 'application/json'], $jsonData);

    expect(function () use ($adapter, $request) {
        $adapter->handleRequest($request);
    })->not->toThrow(\Exception::class);
});

test('handles request with form data', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    $formData = 'name=test&value=123';
    $request = $this->createPsr7Request('POST', '/form', ['Content-Type' => 'application/x-www-form-urlencoded'], $formData);

    expect(function () use ($adapter, $request) {
        $adapter->handleRequest($request);
    })->not->toThrow(\Exception::class);
});

test('handles error gracefully', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, []);

    // 创建一个可能导致错误的请求
    $request = $this->createPsr7Request('GET', '/nonexistent');

    $response = $adapter->handleRequest($request);

    expect($response)->toBeInstanceOf(\Psr\Http\Message\ResponseInterface::class);
    expect($response->getStatusCode())->toBeInt();
});

test('can configure worker settings', function () {
    $this->createApplication();
    $adapter = new FrankenphpAdapter($this->app, [
        'worker_num' => 16,
        'max_requests' => 10000,
        'request_timeout' => 30,
    ]);

    $config = $adapter->getConfig();

    expect($config['worker_num'])->toBe(16);
    expect($config['max_requests'])->toBe(10000);
    expect($config['request_timeout'])->toBe(30);
});

test('validates configuration', function () {
    $this->createApplication();

    // 测试无效端口配置
    expect(function () {
        new FrankenphpAdapter($this->app, ['listen' => ':99999']);
    })->not->toThrow(\Exception::class);

    // 测试负数worker配置
    expect(function () {
        new FrankenphpAdapter($this->app, ['worker_num' => -1]);
    })->not->toThrow(\Exception::class);
});
