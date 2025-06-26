<?php

declare(strict_types=1);

use Think\Runtime\Runtime\ThinkPHPRuntime;
use Think\Runtime\Runtime\WorkermanRuntime;
use Think\Runtime\Runtime\ReactPHPRuntime;

beforeEach(function () {
    // Reset global state
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SESSION = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'HTTP_HOST' => 'localhost',
        'SCRIPT_FILENAME' => __FILE__,
    ];
    $_ENV = [];
});

it('can run complete ThinkPHP runtime workflow', function () {
    $runtime = new ThinkPHPRuntime();
    
    // Create a simple application callable
    $appCallable = function (array $context) {
        return createMockApp();
    };
    
    // Resolve the callable
    $resolver = $runtime->getResolver($appCallable);
    [$callable, $arguments] = $resolver->resolve();
    
    // Execute the callable to get the application
    $application = $callable(...$arguments);
    
    // Get the runner for the application
    $runner = $runtime->getRunner($application);
    
    // Run the application
    ob_start();
    $exitCode = $runner->run();
    $output = ob_get_clean();
    
    expect($exitCode)->toBe(0)
        ->and($output)->toBe('Hello World');
});

it('can handle different application types', function () {
    $runtime = new ThinkPHPRuntime();
    
    // Test with callable application
    $callableApp = function () {
        return 42;
    };
    
    $runner = $runtime->getRunner($callableApp);
    $exitCode = $runner->run();
    
    expect($exitCode)->toBe(42);
    
    // Test with null application
    $nullRunner = $runtime->getRunner(null);
    $nullExitCode = $nullRunner->run();
    
    expect($nullExitCode)->toBe(0);
});

it('can resolve complex parameter combinations', function () {
    $runtime = new ThinkPHPRuntime();
    
    $complexCallable = function (array $context, array $argv, array $request) {
        return [
            'context_keys' => array_keys($context),
            'argv_count' => count($argv),
            'request_keys' => array_keys($request),
        ];
    };
    
    $_SERVER['TEST_VAR'] = 'test_value';
    $_SERVER['argv'] = ['script.php', 'arg1'];
    $_GET = ['query' => 'value'];
    $_POST = ['body' => 'data'];
    
    $resolver = $runtime->getResolver($complexCallable);
    [$callable, $arguments] = $resolver->resolve();
    
    $result = $callable(...$arguments);
    
    expect($result)->toBeArray()
        ->and($result['context_keys'])->toContain('TEST_VAR')
        ->and($result['argv_count'])->toBe(2)
        ->and($result['request_keys'])->toContain('query', 'body', 'files', 'session');
});

it('can handle runtime options correctly', function () {
    $options = [
        'env' => 'testing',
        'debug' => true,
        'app_path' => '/test/app',
    ];
    
    $runtime = new ThinkPHPRuntime($options);
    
    $appCallable = function (array $context) use ($options) {
        return (object) [
            'options' => $options,
            'context' => $context,
        ];
    };
    
    $resolver = $runtime->getResolver($appCallable);
    [$callable, $arguments] = $resolver->resolve();
    
    $application = $callable(...$arguments);
    
    expect($application->options['env'])->toBe('testing')
        ->and($application->options['debug'])->toBeTrue()
        ->and($application->options['app_path'])->toBe('/test/app');
});

it('can extend runtime with custom options', function () {
    $baseRuntime = new ThinkPHPRuntime(['base' => 'value']);
    $extendedRuntime = $baseRuntime->withOptions(['extended' => 'value']);
    
    $baseOptions = $baseRuntime->getOptions();
    $extendedOptions = $extendedRuntime->getOptions();
    
    expect($baseOptions)->toHaveKey('base')
        ->and($baseOptions)->not->toHaveKey('extended')
        ->and($extendedOptions)->toHaveKey('base')
        ->and($extendedOptions)->toHaveKey('extended');
});

it('can handle Workerman runtime configuration', function () {
    $runtime = new WorkermanRuntime([
        'host' => '0.0.0.0',
        'port' => 9000,
        'worker_count' => 8,
    ]);
    
    $options = $runtime->getOptions();
    
    expect($options['host'])->toBe('0.0.0.0')
        ->and($options['port'])->toBe(9000)
        ->and($options['worker_count'])->toBe(8);
});

it('can handle ReactPHP runtime configuration', function () {
    $runtime = new ReactPHPRuntime([
        'host' => '127.0.0.1',
        'port' => 8080,
        'max_concurrent_requests' => 200,
    ]);
    
    $options = $runtime->getOptions();
    
    expect($options['host'])->toBe('127.0.0.1')
        ->and($options['port'])->toBe(8080)
        ->and($options['max_concurrent_requests'])->toBe(200);
});

it('can handle error scenarios gracefully', function () {
    $runtime = new ThinkPHPRuntime();
    
    // Test with application that throws exception
    $errorApp = new class {
        public function handle($request)
        {
            throw new Exception('Test error');
        }
    };
    
    $runner = $runtime->getRunner($errorApp);
    
    ob_start();
    $exitCode = $runner->run();
    ob_end_clean();
    
    expect($exitCode)->toBe(1);
});

it('can handle environment variable configuration', function () {
    $_SERVER['APP_ENV'] = 'production';
    $_SERVER['APP_DEBUG'] = '0';
    $_SERVER['APP_RUNTIME_OPTIONS'] = [
        'custom_option' => 'custom_value',
    ];
    
    $runtime = new ThinkPHPRuntime();
    $options = $runtime->getOptions();
    
    expect($options['env'])->toBe('production')
        ->and($options['debug'])->toBeFalse();
});
