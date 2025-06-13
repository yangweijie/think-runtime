<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\BrefAdapter;
use think\App;

/**
 * BrefAdapter 单元测试
 * 测试适配器的核心功能和边界情况
 */

beforeEach(function () {
    $this->app = new App();
    $this->adapter = new BrefAdapter($this->app, []);
});

test('implements required interface', function () {
    expect($this->adapter)->toBeInstanceOf(\yangweijie\thinkRuntime\contract\AdapterInterface::class);
});

test('extends abstract runtime', function () {
    expect($this->adapter)->toBeInstanceOf(\yangweijie\thinkRuntime\runtime\AbstractRuntime::class);
});

test('has correct adapter name', function () {
    expect($this->adapter->getName())->toBe('bref');
});

test('has correct priority in lambda environment', function () {
    // 模拟 Lambda 环境
    $_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';
    
    $adapter = new BrefAdapter($this->app, []);
    expect($adapter->getPriority())->toBe(200);
    
    // 清理环境变量
    unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
});

test('has lower priority in non-lambda environment', function () {
    // 确保不在 Lambda 环境中
    unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
    unset($_ENV['LAMBDA_TASK_ROOT']);
    unset($_ENV['AWS_EXECUTION_ENV']);
    
    $adapter = new BrefAdapter($this->app, []);
    expect($adapter->getPriority())->toBe(50);
});

test('detects lambda environment correctly', function () {
    // 测试 Lambda 环境检测
    $_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';
    
    $adapter = new BrefAdapter($this->app, []);
    expect($adapter->isSupported())->toBeTrue();
    
    // 清理环境变量
    unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
});

test('supports environment with bref classes', function () {
    // 如果有 bref 相关类，也应该支持
    if (class_exists('\Bref\Context\Context') || class_exists('\Runtime\Bref\Runtime')) {
        expect($this->adapter->isSupported())->toBeTrue();
    } else {
        // 在没有 bref 类且不在 Lambda 环境中时，应该不支持
        expect($this->adapter->isSupported())->toBeFalse();
    }
});

test('isAvailable returns same as isSupported', function () {
    expect($this->adapter->isAvailable())->toBe($this->adapter->isSupported());
});

test('has default configuration', function () {
    $config = $this->adapter->getConfig();
    
    expect($config)->toHaveKey('lambda');
    expect($config)->toHaveKey('http');
    expect($config)->toHaveKey('error');
    expect($config)->toHaveKey('monitor');
    
    expect($config['lambda']['timeout'])->toBe(30);
    expect($config['lambda']['memory'])->toBe(512);
    expect($config['http']['enable_cors'])->toBeTrue();
    expect($config['error']['display_errors'])->toBeFalse();
    expect($config['monitor']['enable'])->toBeTrue();
});

test('can merge custom configuration', function () {
    $customConfig = [
        'lambda' => [
            'timeout' => 60,
            'memory' => 1024,
        ],
        'http' => [
            'enable_cors' => false,
        ],
    ];
    
    $adapter = new BrefAdapter($this->app, $customConfig);
    $config = $adapter->getConfig();
    
    expect($config['lambda']['timeout'])->toBe(60);
    expect($config['lambda']['memory'])->toBe(1024);
    expect($config['http']['enable_cors'])->toBeFalse();
    
    // 默认值应该保持
    expect($config['error']['display_errors'])->toBeFalse();
    expect($config['monitor']['enable'])->toBeTrue();
});

test('can update configuration', function () {
    $this->adapter->setConfig([
        'lambda' => [
            'timeout' => 45,
        ],
    ]);
    
    $config = $this->adapter->getConfig();
    expect($config['lambda']['timeout'])->toBe(45);
});

test('detects api gateway v1 event format', function () {
    $reflection = new ReflectionClass($this->adapter);
    $method = $reflection->getMethod('isHttpLambdaEvent');
    $method->setAccessible(true);
    
    $apiGatewayV1Event = [
        'httpMethod' => 'GET',
        'path' => '/test',
        'headers' => [],
        'queryStringParameters' => [],
    ];
    
    expect($method->invoke($this->adapter, $apiGatewayV1Event))->toBeTrue();
});

test('detects api gateway v2 event format', function () {
    $reflection = new ReflectionClass($this->adapter);
    $method = $reflection->getMethod('isHttpLambdaEvent');
    $method->setAccessible(true);
    
    $apiGatewayV2Event = [
        'version' => '2.0',
        'requestContext' => [
            'http' => [
                'method' => 'GET',
                'path' => '/test',
            ],
        ],
    ];
    
    expect($method->invoke($this->adapter, $apiGatewayV2Event))->toBeTrue();
});

test('detects alb event format', function () {
    $reflection = new ReflectionClass($this->adapter);
    $method = $reflection->getMethod('isHttpLambdaEvent');
    $method->setAccessible(true);
    
    $albEvent = [
        'requestContext' => [
            'elb' => [
                'targetGroupArn' => 'arn:aws:elasticloadbalancing:...',
            ],
        ],
        'httpMethod' => 'GET',
        'path' => '/test',
    ];
    
    expect($method->invoke($this->adapter, $albEvent))->toBeTrue();
});

test('rejects non-http events', function () {
    $reflection = new ReflectionClass($this->adapter);
    $method = $reflection->getMethod('isHttpLambdaEvent');
    $method->setAccessible(true);
    
    $sqsEvent = [
        'Records' => [
            [
                'eventSource' => 'aws:sqs',
                'body' => 'test message',
            ],
        ],
    ];
    
    expect($method->invoke($this->adapter, $sqsEvent))->toBeFalse();
});

test('handles null events', function () {
    $reflection = new ReflectionClass($this->adapter);
    $method = $reflection->getMethod('isHttpLambdaEvent');
    $method->setAccessible(true);
    
    expect($method->invoke($this->adapter, null))->toBeFalse();
});

test('boot method behavior depends on environment support', function () {
    // 模拟支持的环境
    $_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';

    $adapter = new BrefAdapter($this->app, [
        'lambda' => ['memory' => 256],
        'error' => ['display_errors' => true],
    ]);

    // 检查是否支持，如果支持则测试 boot 成功，否则测试抛出异常
    if ($adapter->isSupported()) {
        // 如果支持，boot 应该成功
        try {
            $adapter->boot();
            expect(true)->toBeTrue(); // 如果没有异常，测试通过
        } catch (\Exception $e) {
            expect(false)->toBeTrue("Boot should not throw exception in supported environment: " . $e->getMessage());
        }
    } else {
        // 如果不支持，boot 应该抛出异常
        expect(function () use ($adapter) {
            $adapter->boot();
        })->toThrow(\RuntimeException::class, 'Bref runtime is not available');
    }

    // 清理环境变量
    unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
});

test('boot throws exception in unsupported environment', function () {
    // 确保不在支持的环境中
    unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
    unset($_ENV['LAMBDA_TASK_ROOT']);
    unset($_ENV['AWS_EXECUTION_ENV']);
    
    expect(function () {
        $this->adapter->boot();
    })->toThrow(\RuntimeException::class, 'Bref runtime is not available');
});

test('stop and terminate methods work', function () {
    // 测试 stop 和 terminate 方法不会抛出异常
    try {
        $this->adapter->stop();
        $this->adapter->terminate();
        expect(true)->toBeTrue(); // 如果没有异常，测试通过
    } catch (\Exception $e) {
        expect(false)->toBeTrue("Methods threw exception: " . $e->getMessage());
    }
});
