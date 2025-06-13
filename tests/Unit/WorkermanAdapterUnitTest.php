<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;
use think\App;

/**
 * WorkermanAdapter 单元测试
 * 测试适配器的核心功能和边界情况
 */

beforeEach(function () {
    $this->app = new App();
    $this->adapter = new WorkermanAdapter($this->app, []);
});

test('implements required interface', function () {
    expect($this->adapter)->toBeInstanceOf(\yangweijie\thinkRuntime\contract\AdapterInterface::class);
});

test('extends abstract runtime', function () {
    expect($this->adapter)->toBeInstanceOf(\yangweijie\thinkRuntime\runtime\AbstractRuntime::class);
});

test('has correct adapter name', function () {
    expect($this->adapter->getName())->toBe('workerman');
});

test('has correct priority', function () {
    expect($this->adapter->getPriority())->toBe(85);
});

test('availability depends on workerman class', function () {
    $hasWorkerman = class_exists('\Workerman\Worker');
    expect($this->adapter->isAvailable())->toBe($hasWorkerman);
    expect($this->adapter->isSupported())->toBe($hasWorkerman);
});

test('has comprehensive default config', function () {
    $config = $this->adapter->getConfig();

    // 基础配置
    expect($config)->toHaveKeys(['host', 'port', 'count', 'name', 'transport', 'protocol']);
    
    // 静态文件配置
    expect($config)->toHaveKey('static_file');
    expect($config['static_file'])->toHaveKeys(['enable', 'document_root', 'cache_time', 'allowed_extensions']);
    
    // 监控配置
    expect($config)->toHaveKey('monitor');
    expect($config['monitor'])->toHaveKeys(['enable', 'slow_request_threshold', 'memory_limit']);
    
    // 中间件配置
    expect($config)->toHaveKey('middleware');
    expect($config['middleware'])->toHaveKeys(['cors', 'security']);
    
    // 日志配置
    expect($config)->toHaveKey('log');
    expect($config['log'])->toHaveKeys(['enable', 'file', 'level']);
    
    // 定时器配置
    expect($config)->toHaveKey('timer');
    expect($config['timer'])->toHaveKeys(['enable', 'interval']);
});

test('can override default config', function () {
    $customConfig = [
        'host' => '192.168.1.100',
        'port' => 9999,
        'count' => 16,
        'name' => 'Custom-Server',
        'static_file' => [
            'enable' => false,
            'cache_time' => 7200,
        ],
        'monitor' => [
            'slow_request_threshold' => 2000,
        ],
    ];

    $adapter = new WorkermanAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();

    expect($config['host'])->toBe('192.168.1.100');
    expect($config['port'])->toBe(9999);
    expect($config['count'])->toBe(16);
    expect($config['name'])->toBe('Custom-Server');
    expect($config['static_file']['enable'])->toBe(false);
    expect($config['static_file']['cache_time'])->toBe(7200);
    expect($config['monitor']['slow_request_threshold'])->toBe(2000);
});

test('can set config after instantiation', function () {
    $this->adapter->setConfig(['test_key' => 'test_value']);
    $config = $this->adapter->getConfig();
    
    expect($config['test_key'])->toBe('test_value');
});

test('has all required public methods', function () {
    $publicMethods = [
        'boot', 'start', 'stop', 'run', 'terminate',
        'getName', 'isAvailable', 'isSupported', 'getPriority',
        'getConfig', 'setConfig', 'addMiddleware',
        'onWorkerStart', 'onMessage', 'onConnect', 'onClose', 'onError',
        'onWorkerStop', 'onWorkerReload'
    ];

    foreach ($publicMethods as $method) {
        expect(method_exists($this->adapter, $method))->toBe(true);
        
        $reflection = new ReflectionMethod($this->adapter, $method);
        expect($reflection->isPublic())->toBe(true);
    }
});

test('has comprehensive mime type mapping', function () {
    $reflection = new ReflectionClass($this->adapter);
    $mimeTypesProperty = $reflection->getProperty('mimeTypes');
    $mimeTypesProperty->setAccessible(true);
    $mimeTypes = $mimeTypesProperty->getValue($this->adapter);

    expect($mimeTypes)->toBeArray();
    expect(count($mimeTypes))->toBeGreaterThan(10);

    // 测试常见类型
    $expectedTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'json' => 'application/json',
        'html' => 'text/html',
        'pdf' => 'application/pdf',
    ];

    foreach ($expectedTypes as $ext => $expectedMime) {
        expect($mimeTypes)->toHaveKey($ext);
        expect($mimeTypes[$ext])->toBe($expectedMime);
    }
});

test('getMimeType returns correct types', function () {
    $reflection = new ReflectionClass($this->adapter);
    $getMimeTypeMethod = $reflection->getMethod('getMimeType');
    $getMimeTypeMethod->setAccessible(true);

    expect($getMimeTypeMethod->invoke($this->adapter, 'css'))->toBe('text/css');
    expect($getMimeTypeMethod->invoke($this->adapter, 'js'))->toBe('application/javascript');
    expect($getMimeTypeMethod->invoke($this->adapter, 'png'))->toBe('image/png');
    expect($getMimeTypeMethod->invoke($this->adapter, 'unknown'))->toBe('application/octet-stream');
});

test('parseMemoryLimit handles different units', function () {
    $reflection = new ReflectionClass($this->adapter);
    $parseMemoryLimitMethod = $reflection->getMethod('parseMemoryLimit');
    $parseMemoryLimitMethod->setAccessible(true);

    // 测试不同单位
    expect($parseMemoryLimitMethod->invoke($this->adapter, '128M'))->toBe(128 * 1024 * 1024);
    expect($parseMemoryLimitMethod->invoke($this->adapter, '1G'))->toBe(1024 * 1024 * 1024);
    expect($parseMemoryLimitMethod->invoke($this->adapter, '512K'))->toBe(512 * 1024);
    expect($parseMemoryLimitMethod->invoke($this->adapter, '1024'))->toBe(1024);

    // 测试小写单位
    expect($parseMemoryLimitMethod->invoke($this->adapter, '256m'))->toBe(256 * 1024 * 1024);
    expect($parseMemoryLimitMethod->invoke($this->adapter, '2g'))->toBe(2 * 1024 * 1024 * 1024);
});

test('can add multiple middlewares', function () {
    $middleware1 = function($request) { return null; };
    $middleware2 = function($request) { return null; };

    $this->adapter->addMiddleware($middleware1);
    $this->adapter->addMiddleware($middleware2);

    $reflection = new ReflectionClass($this->adapter);
    $middlewaresProperty = $reflection->getProperty('middlewares');
    $middlewaresProperty->setAccessible(true);
    $middlewares = $middlewaresProperty->getValue($this->adapter);

    expect($middlewares)->toBeArray();
    expect(count($middlewares))->toBeGreaterThanOrEqual(2);
});

test('isValidStaticFile validates file existence and security', function () {
    $reflection = new ReflectionClass($this->adapter);
    $isValidStaticFileMethod = $reflection->getMethod('isValidStaticFile');
    $isValidStaticFileMethod->setAccessible(true);

    // 创建临时测试环境
    $tempDir = sys_get_temp_dir() . '/workerman_test_' . uniqid();
    mkdir($tempDir, 0755, true);
    
    $testFile = $tempDir . '/test.txt';
    file_put_contents($testFile, 'test content');

    // 测试有效文件
    expect($isValidStaticFileMethod->invoke($this->adapter, $testFile, $tempDir))->toBe(true);

    // 测试不存在的文件
    expect($isValidStaticFileMethod->invoke($this->adapter, $tempDir . '/nonexistent.txt', $tempDir))->toBe(false);

    // 测试目录遍历攻击
    expect($isValidStaticFileMethod->invoke($this->adapter, $tempDir . '/../../../etc/passwd', $tempDir))->toBe(false);

    // 清理
    unlink($testFile);
    rmdir($tempDir);
});

test('getPublicPath returns correct path', function () {
    $reflection = new ReflectionClass($this->adapter);
    $getPublicPathMethod = $reflection->getMethod('getPublicPath');
    $getPublicPathMethod->setAccessible(true);

    $publicPath = $getPublicPathMethod->invoke($this->adapter);
    
    expect($publicPath)->toBeString();
    expect($publicPath)->toContain('public');
    expect($publicPath)->not->toBeEmpty();
});

test('handles configuration errors gracefully', function () {
    // 测试无效配置不会导致致命错误
    expect(function () {
        new WorkermanAdapter($this->app, [
            'port' => 'invalid_port',
            'count' => 'invalid_count',
        ]);
    })->not->toThrow(\Exception::class);
});

test('handles missing dependencies gracefully', function () {
    // 测试在没有Workerman的情况下不会崩溃
    expect($this->adapter->isSupported())->toBeBool();
    expect($this->adapter->isAvailable())->toBeBool();
});

test('supports multi-process configuration', function () {
    $adapter = new WorkermanAdapter($this->app, [
        'count' => 8,
        'reusePort' => true,
    ]);
    
    $config = $adapter->getConfig();
    
    expect($config['count'])->toBe(8);
    expect($config['reusePort'])->toBe(true);
});

test('supports user and group configuration', function () {
    $adapter = new WorkermanAdapter($this->app, [
        'user' => 'www-data',
        'group' => 'www-data',
    ]);
    
    $config = $adapter->getConfig();
    
    expect($config['user'])->toBe('www-data');
    expect($config['group'])->toBe('www-data');
});

test('supports socket context configuration', function () {
    $adapter = new WorkermanAdapter($this->app, [
        'context' => [
            'socket' => [
                'backlog' => 2048,
                'so_reuseport' => 1,
            ],
        ],
    ]);
    
    $config = $adapter->getConfig();
    
    expect($config['context']['socket']['backlog'])->toBe(2048);
    expect($config['context']['socket']['so_reuseport'])->toBe(1);
});
