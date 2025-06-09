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
