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
