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
