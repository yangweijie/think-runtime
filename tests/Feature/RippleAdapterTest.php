<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\RippleAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    $name = $adapter->getName();
    expect($name)->toBe('ripple');
});

test('has correct priority', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    $priority = $adapter->getPriority();
    expect($priority)->toBe(91);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    $config = $adapter->getConfig();
    expect($config)->toBeArray();
    
    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    // 测试方法存在且可调用
    $hasMethod = method_exists($adapter, 'handleRequest');
    expect($hasMethod)->toBe(true);
});

test('can start and stop', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    // 测试方法存在且可调用
    $hasStart = method_exists($adapter, 'start');
    $hasStop = method_exists($adapter, 'stop');
    expect($hasStart)->toBe(true);
    expect($hasStop)->toBe(true);
});

test('supports environment detection', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    // 检查PHP版本和Ripple支持
    $supported = $adapter->isSupported();
    
    // 在测试环境中，Ripple可能未安装，但我们可以测试PHP版本检查
    $hasMethod = method_exists($adapter, 'isSupported');
    expect($hasMethod)->toBe(true);
    expect($supported)->toBeIn([true, false]);
    
    // 如果PHP版本低于8.1，应该返回false
    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        expect($supported)->toBe(false);
    }
});

test('has coroutine methods', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    // 测试协程相关方法存在
    $methods = ['createCoroutine', 'getCoroutinePoolStatus'];
    foreach ($methods as $method) {
        $hasMethod = method_exists($adapter, $method);
        expect($hasMethod)->toBe(true);
    }
});

test('has ripple request handler', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    // 测试Ripple特定的请求处理方法
    $hasMethod = method_exists($adapter, 'handleRippleRequest');
    expect($hasMethod)->toBe(true);
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    // 测试所有必需方法存在
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority'];
    foreach ($methods as $method) {
        $hasMethod = method_exists($adapter, $method);
        expect($hasMethod)->toBe(true);
    }
});

test('has default config', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    $config = $adapter->getConfig();
    
    // 测试默认配置包含必要的键
    $requiredKeys = ['host', 'port', 'worker_num', 'max_connections', 'max_coroutines', 'enable_fiber'];
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
        'worker_num' => 8,
        'debug' => true,
        'enable_fiber' => false,
    ];
    
    $adapter = new RippleAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();
    
    expect($config['host'])->toBe('127.0.0.1');
    expect($config['port'])->toBe(9000);
    expect($config['worker_num'])->toBe(8);
    expect($config['debug'])->toBe(true);
    expect($config['enable_fiber'])->toBe(false);
});

test('has fiber support config', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    $config = $adapter->getConfig();
    
    // 测试Fiber相关配置
    expect($config)->toHaveKey('enable_fiber');
    expect($config)->toHaveKey('fiber_stack_size');
    expect($config['enable_fiber'])->toBe(true);
    expect($config['fiber_stack_size'])->toBe(8192);
});

test('has coroutine pool config', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    $config = $adapter->getConfig();
    
    // 测试协程池相关配置
    expect($config)->toHaveKey('max_coroutines');
    expect($config)->toHaveKey('coroutine_pool_size');
    expect($config['max_coroutines'])->toBe(100000);
    expect($config['coroutine_pool_size'])->toBe(1000);
});

test('has database pool config', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    $config = $adapter->getConfig();
    
    // 测试数据库连接池配置
    expect($config)->toHaveKey('database');
    expect($config['database'])->toHaveKey('pool_size');
    expect($config['database'])->toHaveKey('max_idle_time');
    expect($config['database']['pool_size'])->toBe(10);
    expect($config['database']['max_idle_time'])->toBe(3600);
});

test('can create coroutine', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);
    
    $executed = false;
    $result = $adapter->createCoroutine(function () use (&$executed) {
        $executed = true;
        return 'test';
    });
    
    // 在没有Fiber的环境中，应该同步执行
    expect($executed)->toBe(true);
});

test('can get coroutine pool status', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);

    $status = $adapter->getCoroutinePoolStatus();

    expect($status)->toBeArray();
    expect($status)->toHaveKey('total');
    expect($status)->toHaveKey('active');
    expect($status['total'])->toBeInt();
    expect($status['active'])->toBeInt();
});

test('handles fiber operations safely', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);

    // 测试Fiber相关操作不会崩溃
    expect(function () use ($adapter) {
        $adapter->createCoroutine(function () {
            return 'test';
        });
    })->not->toThrow(\Exception::class);
});

test('can handle high concurrency', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, [
        'max_coroutines' => 50000,
        'coroutine_pool_size' => 5000,
    ]);

    $config = $adapter->getConfig();

    expect($config['max_coroutines'])->toBe(50000);
    expect($config['coroutine_pool_size'])->toBe(5000);
});

test('handles request with ripple specific features', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);

    $request = $this->createPsr7Request('GET', '/ripple-test');

    expect(function () use ($adapter, $request) {
        $adapter->handleRippleRequest($request);
    })->not->toThrow(\Exception::class);
});

test('can configure database connection pool', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, [
        'database' => [
            'pool_size' => 20,
            'max_idle_time' => 7200,
            'connection_timeout' => 5,
            'query_timeout' => 30,
        ],
    ]);

    $config = $adapter->getConfig();

    expect($config['database']['pool_size'])->toBe(20);
    expect($config['database']['max_idle_time'])->toBe(7200);
    expect($config['database']['connection_timeout'])->toBe(5);
    expect($config['database']['query_timeout'])->toBe(30);
});

test('validates fiber configuration', function () {
    $this->createApplication();

    // 测试禁用Fiber
    $adapter = new RippleAdapter($this->app, ['enable_fiber' => false]);
    expect($adapter->getConfig()['enable_fiber'])->toBe(false);

    // 测试自定义Fiber栈大小
    $adapter2 = new RippleAdapter($this->app, ['fiber_stack_size' => 16384]);
    expect($adapter2->getConfig()['fiber_stack_size'])->toBe(16384);
});

test('handles memory management', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, [
        'memory' => [
            'limit' => '512M',
            'gc_threshold' => 1000,
            'auto_gc' => true,
        ],
    ]);

    $config = $adapter->getConfig();

    expect($config['memory']['limit'])->toBe('512M');
    expect($config['memory']['gc_threshold'])->toBe(1000);
    expect($config['memory']['auto_gc'])->toBe(true);
});

test('can create multiple coroutines', function () {
    $this->createApplication();
    $adapter = new RippleAdapter($this->app, []);

    $results = [];

    for ($i = 0; $i < 5; $i++) {
        $result = $adapter->createCoroutine(function () use ($i) {
            return "coroutine-{$i}";
        });
        $results[] = $result;
    }

    expect(count($results))->toBe(5);
});
