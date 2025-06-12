<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

/**
 * Workerman 集成测试
 * 测试 Workerman 适配器与运行时管理器的集成
 */

beforeEach(function () {
    $this->app = new \think\App();
    $this->app->initialize();
});

test('runtime manager can detect workerman adapter', function () {
    $manager = new RuntimeManager($this->app);
    $adapters = $manager->getAvailableAdapters();
    
    $workermanAdapter = null;
    foreach ($adapters as $adapter) {
        if ($adapter->getName() === 'workerman') {
            $workermanAdapter = $adapter;
            break;
        }
    }
    
    expect($workermanAdapter)->not->toBeNull();
    expect($workermanAdapter)->toBeInstanceOf(WorkermanAdapter::class);
});

test('runtime manager respects workerman priority', function () {
    $manager = new RuntimeManager($this->app);
    $adapters = $manager->getAvailableAdapters();
    
    $workermanAdapter = null;
    foreach ($adapters as $adapter) {
        if ($adapter->getName() === 'workerman') {
            $workermanAdapter = $adapter;
            break;
        }
    }
    
    expect($workermanAdapter)->not->toBeNull();
    expect($workermanAdapter->getPriority())->toBe(85);
});

test('can get workerman adapter by name', function () {
    $manager = new RuntimeManager($this->app);
    
    try {
        $adapter = $manager->getAdapter('workerman');
        expect($adapter)->toBeInstanceOf(WorkermanAdapter::class);
        expect($adapter->getName())->toBe('workerman');
    } catch (\Exception $e) {
        // 如果 Workerman 不可用，这是预期的
        expect($e->getMessage())->toContain('not available');
    }
});

test('workerman adapter configuration is properly loaded', function () {
    $config = [
        'workerman' => [
            'host' => '127.0.0.1',
            'port' => 8888,
            'count' => 2,
            'name' => 'Test-Workerman',
        ],
    ];

    $manager = new RuntimeManager($this->app, $config);
    
    try {
        $adapter = $manager->getAdapter('workerman');
        $adapterConfig = $adapter->getConfig();
        
        expect($adapterConfig['host'])->toBe('127.0.0.1');
        expect($adapterConfig['port'])->toBe(8888);
        expect($adapterConfig['count'])->toBe(2);
        expect($adapterConfig['name'])->toBe('Test-Workerman');
    } catch (\Exception $e) {
        // 如果 Workerman 不可用，跳过测试
        expect($e->getMessage())->toContain('not available');
    }
});

test('workerman is included in auto detection order', function () {
    $manager = new RuntimeManager($this->app);
    $autoDetectOrder = $manager->getAutoDetectOrder();
    
    expect($autoDetectOrder)->toContain('workerman');
});

test('workerman priority affects auto detection', function () {
    $manager = new RuntimeManager($this->app);
    $adapters = $manager->getAvailableAdapters();
    
    // 找到 Workerman 适配器的位置
    $workermanIndex = null;
    foreach ($adapters as $index => $adapter) {
        if ($adapter->getName() === 'workerman') {
            $workermanIndex = $index;
            break;
        }
    }
    
    if ($workermanIndex !== null) {
        // Workerman 的优先级是 85，应该在某些适配器之后
        $workermanAdapter = $adapters[$workermanIndex];
        expect($workermanAdapter->getPriority())->toBe(85);
        
        // 检查是否有更高优先级的适配器在前面
        for ($i = 0; $i < $workermanIndex; $i++) {
            expect($adapters[$i]->getPriority())->toBeGreaterThan(85);
        }
    }
});

test('validates required configuration keys', function () {
    $adapter = new WorkermanAdapter($this->app, []);
    $config = $adapter->getConfig();
    
    $requiredKeys = [
        'host', 'port', 'count', 'name', 'transport', 'protocol',
        'static_file', 'monitor', 'middleware', 'log', 'timer'
    ];
    
    foreach ($requiredKeys as $key) {
        expect($config)->toHaveKey($key);
    }
});

test('validates nested configuration structure', function () {
    $adapter = new WorkermanAdapter($this->app, []);
    $config = $adapter->getConfig();
    
    // 验证静态文件配置结构
    expect($config['static_file'])->toHaveKeys(['enable', 'document_root', 'cache_time', 'allowed_extensions']);
    
    // 验证监控配置结构
    expect($config['monitor'])->toHaveKeys(['enable', 'slow_request_threshold', 'memory_limit']);
    
    // 验证中间件配置结构
    expect($config['middleware']['cors'])->toHaveKeys(['enable', 'allow_origin', 'allow_methods', 'allow_headers']);
    expect($config['middleware']['security'])->toHaveKey('enable');
    
    // 验证日志配置结构
    expect($config['log'])->toHaveKeys(['enable', 'file', 'level']);
    
    // 验证定时器配置结构
    expect($config['timer'])->toHaveKeys(['enable', 'interval']);
});

test('handles invalid configuration gracefully', function () {
    // 测试无效配置不会导致异常
    expect(function () {
        new WorkermanAdapter($this->app, [
            'port' => -1,
            'count' => 0,
            'static_file' => 'invalid',
            'monitor' => null,
        ]);
    })->not->toThrow(\Exception::class);
});

test('supports all required adapter interface methods', function () {
    $adapter = new WorkermanAdapter($this->app, []);
    
    // 测试接口方法
    expect($adapter->getName())->toBe('workerman');
    expect($adapter->getPriority())->toBe(85);
    expect($adapter->isSupported())->toBeBool();
    expect($adapter->isAvailable())->toBeBool();
    
    // 测试配置方法
    $config = $adapter->getConfig();
    expect($config)->toBeArray();
    
    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('supports middleware functionality', function () {
    $adapter = new WorkermanAdapter($this->app, []);
    
    $middlewareExecuted = false;
    $middleware = function($request) use (&$middlewareExecuted) {
        $middlewareExecuted = true;
        return null;
    };
    
    $adapter->addMiddleware($middleware);
    
    // 验证中间件已添加
    $reflection = new ReflectionClass($adapter);
    $middlewaresProperty = $reflection->getProperty('middlewares');
    $middlewaresProperty->setAccessible(true);
    $middlewares = $middlewaresProperty->getValue($adapter);
    
    expect(count($middlewares))->toBeGreaterThan(0);
});

test('supports static file serving configuration', function () {
    $adapter = new WorkermanAdapter($this->app, [
        'static_file' => [
            'enable' => true,
            'document_root' => '/custom/path',
            'cache_time' => 7200,
            'allowed_extensions' => ['html', 'css', 'js'],
        ],
    ]);
    
    $config = $adapter->getConfig();
    
    expect($config['static_file']['enable'])->toBe(true);
    expect($config['static_file']['document_root'])->toBe('/custom/path');
    expect($config['static_file']['cache_time'])->toBe(7200);
    expect($config['static_file']['allowed_extensions'])->toBe(['html', 'css', 'js']);
});

test('supports monitoring configuration', function () {
    $adapter = new WorkermanAdapter($this->app, [
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 2000,
            'memory_limit' => '512M',
        ],
    ]);
    
    $config = $adapter->getConfig();
    
    expect($config['monitor']['enable'])->toBe(true);
    expect($config['monitor']['slow_request_threshold'])->toBe(2000);
    expect($config['monitor']['memory_limit'])->toBe('512M');
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

test('handles workerman unavailability gracefully', function () {
    $adapter = new WorkermanAdapter($this->app, []);
    
    // 这些方法应该在 Workerman 不可用时也能正常工作
    expect($adapter->getName())->toBe('workerman');
    expect($adapter->getPriority())->toBe(85);
    expect($adapter->getConfig())->toBeArray();
    
    // isSupported 和 isAvailable 应该返回 false 如果 Workerman 不可用
    if (!class_exists('\Workerman\Worker')) {
        expect($adapter->isSupported())->toBe(false);
        expect($adapter->isAvailable())->toBe(false);
    }
});

test('handles empty configuration', function () {
    $adapter = new WorkermanAdapter($this->app, []);
    $config = $adapter->getConfig();
    
    // 即使没有提供配置，也应该有默认值
    expect($config)->not->toBeEmpty();
    expect($config)->toHaveKey('host');
    expect($config)->toHaveKey('port');
    expect($config)->toHaveKey('count');
});

test('handles partial configuration override', function () {
    $adapter = new WorkermanAdapter($this->app, [
        'port' => 9999,
    ]);
    
    $config = $adapter->getConfig();
    
    // 应该只覆盖指定的配置，保留其他默认值
    expect($config['port'])->toBe(9999);
    expect($config['host'])->toBe('0.0.0.0'); // 默认值
    expect($config['count'])->toBe(4); // 默认值
});
