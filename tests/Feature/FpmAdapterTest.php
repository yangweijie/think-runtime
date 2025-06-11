<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\FpmAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    expect($adapter->getName())->toBe('fpm');
});

test('has correct priority', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    expect($adapter->getPriority())->toBe(10);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    $config = $adapter->getConfig();
    expect($config)->toBeArray();
    
    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('supports environment detection', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    // FPM适配器应该总是支持（作为后备选项）
    $supported = $adapter->isSupported();
    expect($supported)->toBe(true);
});

test('has default config', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    $config = $adapter->getConfig();
    
    // 测试默认配置包含必要的键
    $requiredKeys = ['auto_start', 'enable_static_handler', 'document_root'];
    foreach ($requiredKeys as $key) {
        expect($config)->toHaveKey($key);
    }
    
    // 测试默认值
    expect($config['auto_start'])->toBe(false);
    expect($config['enable_static_handler'])->toBe(true);
});

test('can merge custom config', function () {
    $this->createApplication();
    $customConfig = [
        'auto_start' => true,
        'document_root' => '/var/www/html',
        'debug' => true,
    ];
    
    $adapter = new FpmAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();
    
    expect($config['auto_start'])->toBe(true);
    expect($config['document_root'])->toBe('/var/www/html');
    expect($config['debug'])->toBe(true);
});

test('has fpm specific methods', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    // 测试FPM特定方法存在
    $methods = ['handleFpmRequest', 'getPhpSapi', 'isCliMode'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    // 测试方法存在且可调用
    expect(method_exists($adapter, 'handleRequest'))->toBe(true);
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    // 测试所有必需方法存在
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('can detect php sapi', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    $sapi = $adapter->getPhpSapi();
    
    expect($sapi)->toBe(php_sapi_name());
    expect($sapi)->toBeString();
});

test('can detect cli mode', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    $isCliMode = $adapter->isCliMode();
    $expectedCliMode = php_sapi_name() === 'cli';
    
    expect($isCliMode)->toBe($expectedCliMode);
});

test('has static file handling config', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    $config = $adapter->getConfig();
    
    // 测试静态文件处理配置
    expect($config)->toHaveKey('static');
    expect($config['static'])->toHaveKey('enable');
    expect($config['static'])->toHaveKey('extensions');
    expect($config['static']['enable'])->toBe(true);
    expect($config['static']['extensions'])->toBeArray();
    expect($config['static']['extensions'])->toContain('css', 'js', 'png', 'jpg', 'gif');
});

test('can configure error handling', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, [
        'error_handling' => [
            'display_errors' => true,
            'error_reporting' => E_ALL,
            'log_errors' => true,
            'error_log' => '/var/log/php_errors.log',
        ],
    ]);
    
    $config = $adapter->getConfig();
    
    expect($config['error_handling']['display_errors'])->toBe(true);
    expect($config['error_handling']['error_reporting'])->toBe(E_ALL);
    expect($config['error_handling']['log_errors'])->toBe(true);
    expect($config['error_handling']['error_log'])->toBe('/var/log/php_errors.log');
});

test('can configure session handling', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, [
        'session' => [
            'auto_start' => false,
            'save_handler' => 'files',
            'save_path' => '/tmp',
            'gc_maxlifetime' => 1440,
        ],
    ]);
    
    $config = $adapter->getConfig();
    
    expect($config['session']['auto_start'])->toBe(false);
    expect($config['session']['save_handler'])->toBe('files');
    expect($config['session']['save_path'])->toBe('/tmp');
    expect($config['session']['gc_maxlifetime'])->toBe(1440);
});

test('can configure memory and execution limits', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, [
        'limits' => [
            'memory_limit' => '256M',
            'max_execution_time' => 30,
            'max_input_time' => 60,
            'post_max_size' => '8M',
            'upload_max_filesize' => '2M',
        ],
    ]);
    
    $config = $adapter->getConfig();
    
    expect($config['limits']['memory_limit'])->toBe('256M');
    expect($config['limits']['max_execution_time'])->toBe(30);
    expect($config['limits']['max_input_time'])->toBe(60);
    expect($config['limits']['post_max_size'])->toBe('8M');
    expect($config['limits']['upload_max_filesize'])->toBe('2M');
});

test('handles request in fpm mode', function () {
    $this->createApplication();
    $adapter = new FpmAdapter($this->app, []);
    
    // 创建一个简单的PSR-7请求
    $request = $this->createPsr7Request('GET', '/test');
    
    // 测试请求处理不会抛出异常
    expect(function () use ($adapter, $request) {
        $adapter->handleFpmRequest($request);
    })->not->toThrow(\Exception::class);
});
