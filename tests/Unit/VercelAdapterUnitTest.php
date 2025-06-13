<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\VercelAdapter;
use think\App;

/**
 * VercelAdapter 单元测试
 * 测试适配器的核心功能和边界情况
 */

beforeEach(function () {
    $this->app = new App();
    $this->adapter = new VercelAdapter($this->app, []);
});

test('implements required interface', function () {
    expect($this->adapter)->toBeInstanceOf(\yangweijie\thinkRuntime\contract\AdapterInterface::class);
});

test('extends abstract runtime', function () {
    expect($this->adapter)->toBeInstanceOf(\yangweijie\thinkRuntime\runtime\AbstractRuntime::class);
});

test('has correct adapter name', function () {
    expect($this->adapter->getName())->toBe('vercel');
});

test('has correct priority in vercel environment', function () {
    // 模拟 Vercel 环境
    $_ENV['VERCEL'] = '1';
    
    $adapter = new VercelAdapter($this->app, []);
    expect($adapter->getPriority())->toBe(180);
    
    // 清理环境变量
    unset($_ENV['VERCEL']);
});

test('has lower priority in non-vercel environment', function () {
    // 确保不在 Vercel 环境中
    unset($_ENV['VERCEL']);
    unset($_ENV['VERCEL_ENV']);
    unset($_ENV['VERCEL_URL']);
    unset($_SERVER['HTTP_X_VERCEL_ID']);
    
    $adapter = new VercelAdapter($this->app, []);
    expect($adapter->getPriority())->toBe(60);
});

test('detects vercel environment correctly', function () {
    // 测试 VERCEL 环境变量
    $_ENV['VERCEL'] = '1';
    
    $adapter = new VercelAdapter($this->app, []);
    expect($adapter->isSupported())->toBeTrue();
    
    // 清理环境变量
    unset($_ENV['VERCEL']);
});

test('detects vercel environment with VERCEL_ENV', function () {
    // 测试 VERCEL_ENV 环境变量
    $_ENV['VERCEL_ENV'] = 'production';
    
    $adapter = new VercelAdapter($this->app, []);
    expect($adapter->isSupported())->toBeTrue();
    
    // 清理环境变量
    unset($_ENV['VERCEL_ENV']);
});

test('detects vercel environment with VERCEL_URL', function () {
    // 测试 VERCEL_URL 环境变量
    $_ENV['VERCEL_URL'] = 'my-app.vercel.app';
    
    $adapter = new VercelAdapter($this->app, []);
    expect($adapter->isSupported())->toBeTrue();
    
    // 清理环境变量
    unset($_ENV['VERCEL_URL']);
});

test('detects vercel environment with X-Vercel-ID header', function () {
    // 测试 X-Vercel-ID 头信息
    $_SERVER['HTTP_X_VERCEL_ID'] = 'iad1::12345';
    
    $adapter = new VercelAdapter($this->app, []);
    expect($adapter->isSupported())->toBeTrue();
    
    // 清理服务器变量
    unset($_SERVER['HTTP_X_VERCEL_ID']);
});

test('supports environment with vercel function', function () {
    // 如果有 vercel_request 函数，也应该支持
    if (function_exists('vercel_request')) {
        expect($this->adapter->isSupported())->toBeTrue();
    } else {
        // 在没有 vercel 相关环境时，应该不支持
        expect($this->adapter->isSupported())->toBeFalse();
    }
});

test('isAvailable returns same as isSupported', function () {
    expect($this->adapter->isAvailable())->toBe($this->adapter->isSupported());
});

test('has default configuration', function () {
    $config = $this->adapter->getConfig();
    
    expect($config)->toHaveKey('vercel');
    expect($config)->toHaveKey('http');
    expect($config)->toHaveKey('error');
    expect($config)->toHaveKey('monitor');
    expect($config)->toHaveKey('static');
    
    expect($config['vercel']['timeout'])->toBe(10);
    expect($config['vercel']['memory'])->toBe(1024);
    expect($config['vercel']['region'])->toBe('auto');
    expect($config['http']['enable_cors'])->toBeTrue();
    expect($config['error']['display_errors'])->toBeFalse();
    expect($config['monitor']['enable'])->toBeTrue();
    expect($config['static']['enable'])->toBeFalse();
});

test('can merge custom configuration', function () {
    $customConfig = [
        'vercel' => [
            'timeout' => 30,
            'memory' => 512,
            'region' => 'iad1',
        ],
        'http' => [
            'enable_cors' => false,
            'max_body_size' => '10mb',
        ],
    ];
    
    $adapter = new VercelAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();
    
    expect($config['vercel']['timeout'])->toBe(30);
    expect($config['vercel']['memory'])->toBe(512);
    expect($config['vercel']['region'])->toBe('iad1');
    expect($config['http']['enable_cors'])->toBeFalse();
    expect($config['http']['max_body_size'])->toBe('10mb');
    
    // 默认值应该保持
    expect($config['error']['display_errors'])->toBeFalse();
    expect($config['monitor']['enable'])->toBeTrue();
});

test('can update configuration', function () {
    $this->adapter->setConfig([
        'vercel' => [
            'timeout' => 15,
        ],
    ]);
    
    $config = $this->adapter->getConfig();
    expect($config['vercel']['timeout'])->toBe(15);
});

test('parses memory limit correctly', function () {
    $reflection = new ReflectionClass($this->adapter);
    $method = $reflection->getMethod('parseMemoryLimit');
    $method->setAccessible(true);
    
    expect($method->invoke($this->adapter, '128M'))->toBe(128 * 1024 * 1024);
    expect($method->invoke($this->adapter, '1G'))->toBe(1024 * 1024 * 1024);
    expect($method->invoke($this->adapter, '512K'))->toBe(512 * 1024);
    expect($method->invoke($this->adapter, '1048576'))->toBe(1048576);
});

test('gets request headers correctly', function () {
    // 设置模拟的服务器变量
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
    $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['CONTENT_LENGTH'] = '100';
    
    $reflection = new ReflectionClass($this->adapter);
    $method = $reflection->getMethod('getRequestHeaders');
    $method->setAccessible(true);
    
    $headers = $method->invoke($this->adapter);
    
    expect($headers)->toHaveKey('content-type');
    expect($headers)->toHaveKey('authorization');
    expect($headers)->toHaveKey('x-custom-header');
    expect($headers)->toHaveKey('content-length');
    
    expect($headers['content-type'])->toBe('application/json');
    expect($headers['authorization'])->toBe('Bearer token123');
    expect($headers['x-custom-header'])->toBe('custom-value');
    expect($headers['content-length'])->toBe('100');
    
    // 清理服务器变量
    unset($_SERVER['HTTP_CONTENT_TYPE']);
    unset($_SERVER['HTTP_AUTHORIZATION']);
    unset($_SERVER['HTTP_X_CUSTOM_HEADER']);
    unset($_SERVER['CONTENT_TYPE']);
    unset($_SERVER['CONTENT_LENGTH']);
});

test('boot method behavior depends on environment support', function () {
    // 模拟支持的环境
    $_ENV['VERCEL'] = '1';

    $adapter = new VercelAdapter($this->app, [
        'vercel' => ['memory' => 256],
        'error' => ['display_errors' => true],
    ]);

    // 检查是否支持，如果支持则测试 boot 成功，否则测试抛出异常
    if ($adapter->isSupported()) {
        // 如果支持，boot 应该成功
        try {
            $adapter->boot();
            expect(true)->toBeTrue(); // 如果没有异常，测试通过
        } catch (\Exception $e) {
            expect(false)->toBeTrue("Boot should not throw exception in supported environment: " . $e->getMessage());
        }
    } else {
        // 如果不支持，boot 应该抛出异常
        expect(function () use ($adapter) {
            $adapter->boot();
        })->toThrow(\RuntimeException::class, 'Vercel runtime is not available');
    }

    // 清理环境变量
    unset($_ENV['VERCEL']);
});

test('boot throws exception in unsupported environment', function () {
    // 确保不在支持的环境中
    unset($_ENV['VERCEL']);
    unset($_ENV['VERCEL_ENV']);
    unset($_ENV['VERCEL_URL']);
    unset($_SERVER['HTTP_X_VERCEL_ID']);
    
    expect(function () {
        $this->adapter->boot();
    })->toThrow(\RuntimeException::class, 'Vercel runtime is not available');
});

test('start method calls run', function () {
    // 模拟支持的环境
    $_ENV['VERCEL'] = '1';

    $adapter = new VercelAdapter($this->app, []);

    // 由于 run 方法会输出内容，我们只测试它不抛出异常
    try {
        ob_start();
        $adapter->start();
        ob_end_clean();
        expect(true)->toBeTrue(); // 如果没有异常，测试通过
    } catch (\Exception $e) {
        expect(false)->toBeTrue("Start method threw exception: " . $e->getMessage());
    }

    // 清理环境变量
    unset($_ENV['VERCEL']);
});

test('stop and terminate methods work', function () {
    // 测试 stop 和 terminate 方法不会抛出异常
    try {
        $this->adapter->stop();
        $this->adapter->terminate();
        expect(true)->toBeTrue(); // 如果没有异常，测试通过
    } catch (\Exception $e) {
        expect(false)->toBeTrue("Methods threw exception: " . $e->getMessage());
    }
});
