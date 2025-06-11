<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\runtime\RuntimeManager;
use yangweijie\thinkRuntime\config\RuntimeConfig;

test('can create runtime manager', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    
    $manager = new RuntimeManager($this->app, $this->runtimeConfig);
    
    expect($manager)->toBeInstanceOf(RuntimeManager::class);
});

test('can get available runtimes', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    $runtimes = $this->runtimeManager->getAvailableRuntimes();
    
    expect($runtimes)->toBeArray();
    expect(count($runtimes))->toBeGreaterThan(0);
});

test('can detect best runtime', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    $bestRuntime = $this->runtimeManager->detectBestRuntime();
    
    expect($bestRuntime)->toBeString();
    expect($bestRuntime)->toBeIn(['swoole', 'frankenphp', 'reactphp', 'ripple', 'roadrunner', 'fpm']);
});

test('can get runtime by name', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    $runtime = $this->runtimeManager->getRuntime('fpm');
    
    expect($runtime)->not->toBeNull();
    expect($runtime->getName())->toBe('fpm');
});

test('throws exception for invalid runtime', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    expect(function () {
        $this->runtimeManager->getRuntime('invalid');
    })->toThrow(\InvalidArgumentException::class);
});

test('can start runtime with default config', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    // 使用FPM作为测试，因为它总是可用
    expect(function () {
        $this->runtimeManager->start('fpm');
    })->not->toThrow(\Exception::class);
});

test('can start runtime with custom config', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    $customConfig = [
        'debug' => true,
        'auto_start' => false,
    ];
    
    expect(function () use ($customConfig) {
        $this->runtimeManager->start('fpm', $customConfig);
    })->not->toThrow(\Exception::class);
});

test('can get runtime info', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    $info = $this->runtimeManager->getRuntimeInfo();
    
    expect($info)->toBeArray();
    expect($info)->toHaveKey('name');
    expect($info)->toHaveKey('available');
    expect($info)->toHaveKey('config');
    expect($info)->toHaveKey('all_available');
});

test('can stop runtime', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    // 先启动
    $this->runtimeManager->start('fpm');
    
    // 然后停止
    expect(function () {
        $this->runtimeManager->stop();
    })->not->toThrow(\Exception::class);
});

test('can restart runtime', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    // 启动
    $this->runtimeManager->start('fpm');
    
    // 重启
    expect(function () {
        $this->runtimeManager->restart();
    })->not->toThrow(\Exception::class);
});

test('can check if runtime is running', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    $isRunning = $this->runtimeManager->isRunning();
    
    expect($isRunning)->toBeIn([true, false]);
});

test('can get current runtime name', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    // 启动一个运行时
    $this->runtimeManager->start('fpm');
    
    $currentRuntime = $this->runtimeManager->getCurrentRuntime();
    
    expect($currentRuntime)->toBe('fpm');
});

test('auto detection follows priority order', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    $availableRuntimes = $this->runtimeManager->getAvailableRuntimes();
    $bestRuntime = $this->runtimeManager->detectBestRuntime();
    
    // 最佳运行时应该在可用运行时列表中
    expect($availableRuntimes)->toContain($bestRuntime);
    
    // 如果有多个可用运行时，应该选择优先级最高的
    if (count($availableRuntimes) > 1) {
        $priorities = [];
        foreach ($availableRuntimes as $runtimeName) {
            $runtime = $this->runtimeManager->getRuntime($runtimeName);
            $priorities[$runtimeName] = $runtime->getPriority();
        }
        
        $highestPriority = max($priorities);
        $highestPriorityRuntime = array_search($highestPriority, $priorities);
        
        expect($bestRuntime)->toBe($highestPriorityRuntime);
    }
});

test('can handle runtime switching', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    // 启动第一个运行时
    $this->runtimeManager->start('fpm');
    expect($this->runtimeManager->getCurrentRuntime())->toBe('fpm');
    
    // 切换到另一个运行时（如果可用）
    $availableRuntimes = $this->runtimeManager->getAvailableRuntimes();
    if (count($availableRuntimes) > 1) {
        $otherRuntime = array_values(array_diff($availableRuntimes, ['fpm']))[0];
        
        expect(function () use ($otherRuntime) {
            $this->runtimeManager->start($otherRuntime);
        })->not->toThrow(\Exception::class);
        
        expect($this->runtimeManager->getCurrentRuntime())->toBe($otherRuntime);
    }
});

test('validates runtime configuration', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    // 测试无效配置
    $invalidConfig = [
        'port' => 'invalid',
        'worker_num' => -1,
    ];
    
    // 应该能够处理无效配置而不崩溃
    expect(function () use ($invalidConfig) {
        $this->runtimeManager->start('fpm', $invalidConfig);
    })->not->toThrow(\TypeError::class);
});

test('can get runtime statistics', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    $this->runtimeManager->start('fpm');
    
    $stats = $this->runtimeManager->getStatistics();
    
    expect($stats)->toBeArray();
    expect($stats)->toHaveKey('runtime');
    expect($stats)->toHaveKey('uptime');
    expect($stats)->toHaveKey('memory_usage');
});

test('handles runtime errors gracefully', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();
    
    // 尝试启动不存在的运行时
    expect(function () {
        $this->runtimeManager->start('nonexistent');
    })->toThrow(\InvalidArgumentException::class);
    
    // 管理器应该仍然可用
    expect($this->runtimeManager->getAvailableRuntimes())->toBeArray();
});
