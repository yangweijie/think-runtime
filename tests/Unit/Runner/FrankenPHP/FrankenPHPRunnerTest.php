<?php

declare(strict_types=1);

use Think\Runtime\Runner\FrankenPHP\FrankenPHPRunner;
use Think\Runtime\Contract\RunnerInterface;

beforeEach(function () {
    // Reset global state
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'HTTP_HOST' => 'localhost',
    ];
});

it('implements RunnerInterface', function () {
    $app = createMockApp();
    $runner = new FrankenPHPRunner($app);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('throws exception when FrankenPHP is not available', function () {
    $app = createMockApp();
    $runner = new FrankenPHPRunner($app);
    
    // This test will only pass when FrankenPHP is not available
    if (!function_exists('frankenphp_handle_request')) {
        expect(fn() => $runner->run())
            ->toThrow(RuntimeException::class, 'FrankenPHP is not available');
    } else {
        // Skip test if FrankenPHP is available
        expect(true)->toBeTrue();
    }
});

it('accepts options in constructor', function () {
    $app = createMockApp();
    $options = [
        'frankenphp_loop_max' => 1000,
        'ignore_user_abort' => false,
        'gc_collect_cycles' => false,
    ];
    
    $runner = new FrankenPHPRunner($app, $options);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('creates request from globals', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/test?param=value';
    $_GET = ['param' => 'value'];
    $_POST = ['data' => 'test'];
    $_FILES = ['upload' => 'file'];
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';
    
    $app = new class {
        public $capturedRequest = null;
        
        public function handle($request)
        {
            $this->capturedRequest = $request;
            return 'OK';
        }
    };
    
    $runner = new FrankenPHPRunner($app);
    
    // Test the protected createRequest method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('createRequest');
    $method->setAccessible(true);
    
    $request = $method->invoke($runner);
    
    expect($request)->not->toBeNull();
});

it('handles different response types', function () {
    // Test string response
    $app = new class {
        public function handle($request)
        {
            return 'String Response';
        }
    };
    
    $runner = new FrankenPHPRunner($app);
    
    // Test the protected sendResponse method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('sendResponse');
    $method->setAccessible(true);
    
    ob_start();
    $method->invoke($runner, 'Test Response');
    $output = ob_get_contents();
    ob_end_flush();
    
    expect($output)->toBe('Test Response');
});

it('handles array response as JSON', function () {
    $app = new class {
        public function handle($request)
        {
            return ['message' => 'Hello', 'status' => 'success'];
        }
    };
    
    $runner = new FrankenPHPRunner($app);
    
    // Test the protected sendResponse method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('sendResponse');
    $method->setAccessible(true);
    
    ob_start();
    $method->invoke($runner, ['message' => 'Hello', 'status' => 'success']);
    $output = ob_get_contents();
    ob_end_flush();
    
    expect($output)->toBe('{"message":"Hello","status":"success"}');
});

it('extracts headers from server variables', function () {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';
    
    $app = createMockApp();
    $runner = new FrankenPHPRunner($app);
    
    // Test the protected getHeaders method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('getHeaders');
    $method->setAccessible(true);
    
    $headers = $method->invoke($runner);
    
    expect($headers)->toHaveKey('AUTHORIZATION')
        ->and($headers['AUTHORIZATION'])->toBe('Bearer token')
        ->and($headers)->toHaveKey('CONTENT-TYPE')
        ->and($headers['CONTENT-TYPE'])->toBe('application/json')
        ->and($headers)->toHaveKey('X-CUSTOM-HEADER')
        ->and($headers['X-CUSTOM-HEADER'])->toBe('custom-value');
});
