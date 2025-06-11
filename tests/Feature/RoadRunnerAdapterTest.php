<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\RoadrunnerAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    expect($adapter->getName())->toBe('roadrunner');
});

test('has correct priority', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    expect($adapter->getPriority())->toBe(90);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    $config = $adapter->getConfig();
    expect($config)->toBeArray();

    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('supports environment detection', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    // 默认情况下应该不支持（因为不在RoadRunner环境中）
    $supported = $adapter->isSupported();
    expect($supported)->toBe(false);

    // 模拟RoadRunner环境
    $this->mockRoadRunnerEnvironment();
    $supportedWithEnv = $adapter->isSupported();
    expect($supportedWithEnv)->toBe(true);

    // 清理
    $this->cleanEnvironment();
});

test('has default config', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试默认配置包含必要的键
    $requiredKeys = ['workers', 'max_jobs', 'allocate_timeout', 'destroy_timeout'];
    foreach ($requiredKeys as $key) {
        expect($config)->toHaveKey($key);
    }

    // 测试默认值
    expect($config['workers'])->toBe(4);
    expect($config['max_jobs'])->toBe(1000);
    expect($config['allocate_timeout'])->toBe(60);
    expect($config['destroy_timeout'])->toBe(60);
});

test('can merge custom config', function () {
    $this->createApplication();
    $customConfig = [
        'workers' => 8,
        'max_jobs' => 2000,
        'debug' => true,
    ];

    $adapter = new RoadrunnerAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();

    expect($config['workers'])->toBe(8);
    expect($config['max_jobs'])->toBe(2000);
    expect($config['debug'])->toBe(true);
});

test('has roadrunner specific methods', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    // 测试RoadRunner特定方法存在
    $methods = ['handleRoadRunnerRequest', 'getWorkerPool', 'resetWorker'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    // 测试方法存在且可调用
    expect(method_exists($adapter, 'handleRequest'))->toBe(true);
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    // 测试所有必需方法存在
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('has worker pool configuration', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试Worker池配置
    expect($config)->toHaveKey('pool');
    expect($config['pool'])->toHaveKey('num_workers');
    expect($config['pool'])->toHaveKey('max_jobs');
    expect($config['pool']['num_workers'])->toBe(4);
    expect($config['pool']['max_jobs'])->toBe(1000);
});

test('can configure http settings', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, [
        'http' => [
            'address' => '0.0.0.0:8080',
            'max_request_size' => '10MB',
            'uploads' => [
                'forbid' => ['.php', '.exe'],
            ],
        ],
    ]);

    $config = $adapter->getConfig();

    expect($config['http']['address'])->toBe('0.0.0.0:8080');
    expect($config['http']['max_request_size'])->toBe('10MB');
    expect($config['http']['uploads']['forbid'])->toContain('.php', '.exe');
});

test('can configure static files', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, [
        'static' => [
            'dir' => 'public',
            'forbid' => ['.htaccess'],
            'calculate_etag' => true,
        ],
    ]);

    $config = $adapter->getConfig();

    expect($config['static']['dir'])->toBe('public');
    expect($config['static']['forbid'])->toContain('.htaccess');
    expect($config['static']['calculate_etag'])->toBe(true);
});

test('can configure logs', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, [
        'logs' => [
            'mode' => 'development',
            'level' => 'debug',
            'file_logger_options' => [
                'log_output' => 'runtime/logs/rr.log',
                'max_size' => 10,
                'max_age' => 24,
                'max_backups' => 3,
                'compress' => true,
            ],
        ],
    ]);

    $config = $adapter->getConfig();

    expect($config['logs']['mode'])->toBe('development');
    expect($config['logs']['level'])->toBe('debug');
    expect($config['logs']['file_logger_options']['log_output'])->toBe('runtime/logs/rr.log');
});

test('can get worker pool status', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    $status = $adapter->getWorkerPool();

    expect($status)->toBeArray();
    expect($status)->toHaveKey('workers');
    expect($status)->toHaveKey('active');
    expect($status['workers'])->toBeInt();
    expect($status['active'])->toBeInt();
});

test('can reset worker', function () {
    $this->createApplication();
    $adapter = new RoadrunnerAdapter($this->app, []);

    $result = $adapter->resetWorker();

    // 在测试环境中，这应该返回成功状态
    expect($result)->toBe(true);
});
