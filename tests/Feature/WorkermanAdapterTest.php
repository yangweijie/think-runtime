<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    expect($adapter->getName())->toBe('workerman');
});

test('has correct priority', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    expect($adapter->getPriority())->toBe(85);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $config = $adapter->getConfig();
    expect($config)->toBeArray();

    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('supports environment detection', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    // 检查Workerman类是否存在
    $supported = $adapter->isSupported();
    $hasWorkerman = class_exists('\Workerman\Worker');

    expect($supported)->toBe($hasWorkerman);
});

test('has default config', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试默认配置包含必要的键
    $requiredKeys = ['host', 'port', 'count', 'name', 'reloadable', 'transport', 'protocol'];
    foreach ($requiredKeys as $key) {
        expect($config)->toHaveKey($key);
    }

    // 测试默认值
    expect($config['host'])->toBe('0.0.0.0');
    expect($config['port'])->toBe(8080);
    expect($config['count'])->toBe(4);
    expect($config['name'])->toBe('ThinkPHP-Workerman');
    expect($config['reloadable'])->toBe(true);
    expect($config['transport'])->toBe('tcp');
    expect($config['protocol'])->toBe('http');
});

test('can merge custom config', function () {
    $this->createApplication();
    $customConfig = [
        'host' => '127.0.0.1',
        'port' => 8081,
        'count' => 8,
        'name' => 'Custom-Workerman',
        'user' => 'www-data',
        'group' => 'www-data',
    ];

    $adapter = new WorkermanAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();

    expect($config['host'])->toBe('127.0.0.1');
    expect($config['port'])->toBe(8081);
    expect($config['count'])->toBe(8);
    expect($config['name'])->toBe('Custom-Workerman');
    expect($config['user'])->toBe('www-data');
    expect($config['group'])->toBe('www-data');
});

test('has workerman specific methods', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    // 测试Workerman特定方法存在
    $methods = [
        'onWorkerStart', 'onMessage', 'onConnect', 'onClose', 'onError',
        'onWorkerStop', 'onWorkerReload', 'addMiddleware'
    ];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('has static file configuration', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试静态文件配置
    expect($config)->toHaveKey('static_file');
    expect($config['static_file'])->toHaveKey('enable');
    expect($config['static_file'])->toHaveKey('document_root');
    expect($config['static_file'])->toHaveKey('cache_time');
    expect($config['static_file'])->toHaveKey('allowed_extensions');

    expect($config['static_file']['enable'])->toBe(true);
    expect($config['static_file']['document_root'])->toBe('public');
    expect($config['static_file']['cache_time'])->toBe(3600);
    expect($config['static_file']['allowed_extensions'])->toBeArray();
});

test('has monitor configuration', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试监控配置
    expect($config)->toHaveKey('monitor');
    expect($config['monitor'])->toHaveKey('enable');
    expect($config['monitor'])->toHaveKey('slow_request_threshold');
    expect($config['monitor'])->toHaveKey('memory_limit');

    expect($config['monitor']['enable'])->toBe(true);
    expect($config['monitor']['slow_request_threshold'])->toBe(1000);
    expect($config['monitor']['memory_limit'])->toBe('256M');
});

test('has middleware configuration', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试中间件配置
    expect($config)->toHaveKey('middleware');
    expect($config['middleware'])->toHaveKey('cors');
    expect($config['middleware'])->toHaveKey('security');

    expect($config['middleware']['cors']['enable'])->toBe(true);
    expect($config['middleware']['cors']['allow_origin'])->toBe('*');
    expect($config['middleware']['security']['enable'])->toBe(true);
});

test('has log configuration', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试日志配置
    expect($config)->toHaveKey('log');
    expect($config['log'])->toHaveKey('enable');
    expect($config['log'])->toHaveKey('file');
    expect($config['log'])->toHaveKey('level');

    expect($config['log']['enable'])->toBe(true);
    expect($config['log']['file'])->toBe('runtime/logs/workerman.log');
    expect($config['log']['level'])->toBe('info');
});

test('has timer configuration', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $config = $adapter->getConfig();

    // 测试定时器配置
    expect($config)->toHaveKey('timer');
    expect($config['timer'])->toHaveKey('enable');
    expect($config['timer'])->toHaveKey('interval');

    expect($config['timer']['enable'])->toBe(false);
    expect($config['timer']['interval'])->toBe(60);
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    // 测试方法存在且可调用
    expect(method_exists($adapter, 'handleRequest'))->toBe(true);
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    // 测试所有必需方法存在
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority', 'terminate'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('can configure process settings', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, [
        'count' => 8,
        'user' => 'www-data',
        'group' => 'www-data',
        'reloadable' => false,
        'reusePort' => true,
    ]);

    $config = $adapter->getConfig();

    expect($config['count'])->toBe(8);
    expect($config['user'])->toBe('www-data');
    expect($config['group'])->toBe('www-data');
    expect($config['reloadable'])->toBe(false);
    expect($config['reusePort'])->toBe(true);
});

test('can configure socket context', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, [
        'context' => [
            'socket' => [
                'backlog' => 1024,
                'so_reuseport' => 1,
            ],
        ],
    ]);

    $config = $adapter->getConfig();

    expect($config['context'])->toBeArray();
    expect($config['context']['socket']['backlog'])->toBe(1024);
    expect($config['context']['socket']['so_reuseport'])->toBe(1);
});

test('has mime type mapping', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    // 使用反射访问私有属性
    $reflection = new ReflectionClass($adapter);
    $mimeTypesProperty = $reflection->getProperty('mimeTypes');
    $mimeTypesProperty->setAccessible(true);
    $mimeTypes = $mimeTypesProperty->getValue($adapter);

    expect($mimeTypes)->toBeArray();
    expect($mimeTypes)->toHaveKey('css');
    expect($mimeTypes)->toHaveKey('js');
    expect($mimeTypes)->toHaveKey('png');
    expect($mimeTypes)->toHaveKey('json');

    expect($mimeTypes['css'])->toBe('text/css');
    expect($mimeTypes['js'])->toBe('application/javascript');
    expect($mimeTypes['png'])->toBe('image/png');
    expect($mimeTypes['json'])->toBe('application/json');
});

test('can add middleware', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    // 测试添加中间件
    $middleware = function($request) {
        return null;
    };

    $adapter->addMiddleware($middleware);

    // 使用反射检查中间件是否添加
    $reflection = new ReflectionClass($adapter);
    $middlewaresProperty = $reflection->getProperty('middlewares');
    $middlewaresProperty->setAccessible(true);
    $middlewares = $middlewaresProperty->getValue($adapter);

    expect($middlewares)->toBeArray();
    expect(count($middlewares))->toBeGreaterThan(0);
});

test('has utility methods', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    // 测试工具方法存在
    $methods = [
        'getMimeType', 'getPublicPath', 'handleStaticFile',
        'isValidStaticFile', 'logRequestMetrics', 'setupTimer',
        'checkMemoryUsage', 'parseMemoryLimit'
    ];

    $reflection = new ReflectionClass($adapter);
    foreach ($methods as $method) {
        expect($reflection->hasMethod($method))->toBe(true);
    }
});

test('can parse memory limit', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $reflection = new ReflectionClass($adapter);
    $parseMemoryLimitMethod = $reflection->getMethod('parseMemoryLimit');
    $parseMemoryLimitMethod->setAccessible(true);

    // 测试内存限制解析
    expect($parseMemoryLimitMethod->invoke($adapter, '128M'))->toBe(128 * 1024 * 1024);
    expect($parseMemoryLimitMethod->invoke($adapter, '1G'))->toBe(1024 * 1024 * 1024);
    expect($parseMemoryLimitMethod->invoke($adapter, '512K'))->toBe(512 * 1024);
    expect($parseMemoryLimitMethod->invoke($adapter, '1024'))->toBe(1024);
});

test('can get mime type', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $reflection = new ReflectionClass($adapter);
    $getMimeTypeMethod = $reflection->getMethod('getMimeType');
    $getMimeTypeMethod->setAccessible(true);

    // 测试MIME类型获取
    expect($getMimeTypeMethod->invoke($adapter, 'css'))->toBe('text/css');
    expect($getMimeTypeMethod->invoke($adapter, 'js'))->toBe('application/javascript');
    expect($getMimeTypeMethod->invoke($adapter, 'png'))->toBe('image/png');
    expect($getMimeTypeMethod->invoke($adapter, 'unknown'))->toBe('application/octet-stream');
});

test('can validate static file', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $reflection = new ReflectionClass($adapter);
    $isValidStaticFileMethod = $reflection->getMethod('isValidStaticFile');
    $isValidStaticFileMethod->setAccessible(true);

    // 创建临时测试文件
    $tempDir = sys_get_temp_dir() . '/workerman_test';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    $testFile = $tempDir . '/test.txt';
    file_put_contents($testFile, 'test content');

    // 测试有效文件
    expect($isValidStaticFileMethod->invoke($adapter, $testFile, $tempDir))->toBe(true);

    // 测试无效文件（不存在）
    expect($isValidStaticFileMethod->invoke($adapter, $tempDir . '/nonexistent.txt', $tempDir))->toBe(false);

    // 清理测试文件
    unlink($testFile);
    rmdir($tempDir);
});

test('can get public path', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    $reflection = new ReflectionClass($adapter);
    $getPublicPathMethod = $reflection->getMethod('getPublicPath');
    $getPublicPathMethod->setAccessible(true);

    $publicPath = $getPublicPathMethod->invoke($adapter);
    expect($publicPath)->toBeString();
    expect($publicPath)->toContain('public');
});
