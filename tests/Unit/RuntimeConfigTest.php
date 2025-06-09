<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\config\RuntimeConfig;

test('can get default configuration', function () {
    $config = new RuntimeConfig();
    $result = $config->get();

    expect($result)->toBeArray()
        ->and($result['default'])->toBe('auto')
        ->and($result['auto_detect_order'])->toBeArray()
        ->and($result['runtimes'])->toBeArray();
});

test('can get specific configuration value', function () {
    $config = new RuntimeConfig();

    $default = $config->get('default');
    expect($default)->toBe('auto');

    $autoDetectOrder = $config->get('auto_detect_order');
    expect($autoDetectOrder)->toBeArray()
        ->and($autoDetectOrder)->toContain('swoole', 'frankenphp', 'reactphp', 'ripple', 'roadrunner', 'fpm');
});

test('can get nested configuration value', function () {
    $config = new RuntimeConfig();

    $swooleHost = $config->get('runtimes.swoole.host');
    expect($swooleHost)->toBe('0.0.0.0');

    $swoolePort = $config->get('runtimes.swoole.port');
    expect($swoolePort)->toBe(9501);
});

test('returns default value for non-existent key', function () {
    $config = new RuntimeConfig();

    $value = $config->get('non.existent.key', 'default_value');
    expect($value)->toBe('default_value');
});

test('can set configuration value', function () {
    $config = new RuntimeConfig();

    $config->set('test.key', 'test_value');
    $value = $config->get('test.key');
    expect($value)->toBe('test_value');
});

test('can get runtime specific configuration', function () {
    $config = new RuntimeConfig();

    $swooleConfig = $config->getRuntimeConfig('swoole');

    expect($swooleConfig)->toBeArray()
        ->and($swooleConfig['host'])->toBe('0.0.0.0')
        ->and($swooleConfig['port'])->toBe(9501);
});

test('can get default runtime', function () {
    $config = new RuntimeConfig();

    $defaultRuntime = $config->getDefaultRuntime();
    expect($defaultRuntime)->toBe('auto');
});

test('can get auto detect order', function () {
    $config = new RuntimeConfig();

    $order = $config->getAutoDetectOrder();
    expect($order)->toBeArray()
        ->and($order)->toContain('swoole', 'frankenphp', 'reactphp', 'ripple', 'roadrunner', 'fpm');
});

test('can get global configuration', function () {
    $config = new RuntimeConfig();

    $globalConfig = $config->getGlobalConfig();

    expect($globalConfig)->toBeArray()
        ->and($globalConfig['error_reporting'])->toBe(E_ALL)
        ->and($globalConfig['memory_limit'])->toBe('256M');
});

test('can merge user configuration with defaults', function () {
    $userConfig = [
        'default' => 'swoole',
        'runtimes' => [
            'swoole' => [
                'port' => 8080,
            ],
        ],
    ];

    $config = new RuntimeConfig($userConfig);

    expect($config->getDefaultRuntime())->toBe('swoole');

    $swooleConfig = $config->getRuntimeConfig('swoole');
    expect($swooleConfig['port'])->toBe(8080)
        ->and($swooleConfig['host'])->toBe('0.0.0.0'); // 默认值应该保留
});
