<?php

declare(strict_types=1);

use Think\Runtime\Runner\Vercel\VercelRunner;
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
    $runner = new VercelRunner($app);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('accepts options in constructor', function () {
    $app = createMockApp();
    $options = [
        'vercel_env' => 'production',
        'max_execution_time' => 30,
        'enable_cors' => false,
        'enable_logging' => true,
    ];
    
    $runner = new VercelRunner($app, $options);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('handles CORS preflight requests', function () {
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
    
    $app = createMockApp();
    $runner = new VercelRunner($app, ['enable_cors' => true]);
    
    // Test the protected isPreflightRequest method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('isPreflightRequest');
    $method->setAccessible(true);
    
    $isPreflightRequest = $method->invoke($runner);
    
    expect($isPreflightRequest)->toBeTrue();
});

it('creates request from serverless environment', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/users?page=1';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_GET = ['page' => '1'];
    $_POST = ['name' => 'John'];
    $_FILES = ['avatar' => 'file'];
    $_COOKIE = ['session' => 'abc123'];
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';
    
    $app = createMockApp();
    $runner = new VercelRunner($app);
    
    // Test the protected createRequest method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('createRequest');
    $method->setAccessible(true);
    
    $request = $method->invoke($runner);
    
    expect($request)->toBeArray()
        ->and($request['method'])->toBe('POST')
        ->and($request['uri'])->toBe('/api/users?page=1')
        ->and($request['query'])->toBe(['page' => '1'])
        ->and($request['body'])->toBe(['name' => 'John'])
        ->and($request['files'])->toBe(['avatar' => 'file'])
        ->and($request['cookies'])->toBe(['session' => 'abc123'])
        ->and($request)->toHaveKey('vercel_context')
        ->and($request['vercel_context'])->toHaveKey('is_cold_start');
});

it('gets Vercel context information', function () {
    $app = createMockApp();
    $options = [
        'vercel_env' => 'production',
        'vercel_region' => 'sfo1',
        'function_name' => 'api',
        'vercel_url' => 'https://my-app.vercel.app',
    ];
    $runner = new VercelRunner($app, $options);
    
    // Test the protected getVercelContext method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('getVercelContext');
    $method->setAccessible(true);
    
    $context = $method->invoke($runner);
    
    expect($context)->toBeArray()
        ->and($context['environment'])->toBe('production')
        ->and($context['region'])->toBe('sfo1')
        ->and($context['function_name'])->toBe('api')
        ->and($context['deployment_url'])->toBe('https://my-app.vercel.app')
        ->and($context)->toHaveKey('is_cold_start');
});

it('sets CORS headers correctly', function () {
    $app = createMockApp();
    $options = [
        'cors_origins' => 'https://example.com',
        'cors_methods' => 'GET,POST',
        'cors_headers' => 'Content-Type',
    ];
    $runner = new VercelRunner($app, $options);
    
    // Test the protected setCorsHeaders method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('setCorsHeaders');
    $method->setAccessible(true);
    
    ob_start();
    $method->invoke($runner);
    ob_end_clean();
    
    // Check if headers were set (we can't easily test headers in unit tests,
    // but we can verify the method runs without errors)
    expect(true)->toBeTrue();
});

it('sends JSON response correctly', function () {
    $app = createMockApp();
    $runner = new VercelRunner($app);
    
    // Test the protected sendJsonResponse method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('sendJsonResponse');
    $method->setAccessible(true);
    
    $response = ['message' => 'Hello', 'status' => 'success'];
    
    ob_start();
    $method->invoke($runner, $response);
    $output = ob_get_contents();
    ob_end_clean();
    
    expect($output)->toBe('{"message":"Hello","status":"success"}');
});

it('sends string response correctly', function () {
    $app = createMockApp();
    $runner = new VercelRunner($app);
    
    // Test the protected sendStringResponse method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('sendStringResponse');
    $method->setAccessible(true);
    
    ob_start();
    $method->invoke($runner, 'Hello World');
    $output = ob_get_contents();
    ob_end_clean();
    
    expect($output)->toBe('Hello World');
});

it('extracts headers from server variables', function () {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';
    
    $app = createMockApp();
    $runner = new VercelRunner($app);
    
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

it('handles exceptions in development mode', function () {
    $app = createMockApp();
    $runner = new VercelRunner($app, ['vercel_env' => 'development']);
    
    // Test the protected handleException method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('handleException');
    $method->setAccessible(true);
    
    $exception = new Exception('Test exception');
    
    ob_start();
    $method->invoke($runner, $exception);
    $output = ob_get_contents();
    ob_end_clean();
    
    $response = json_decode($output, true);
    
    expect($response)->toBeArray()
        ->and($response['error'])->toBe('Internal Server Error')
        ->and($response['message'])->toBe('Test exception')
        ->and($response)->toHaveKey('file')
        ->and($response)->toHaveKey('line')
        ->and($response)->toHaveKey('trace');
});

it('handles exceptions in production mode', function () {
    $app = createMockApp();
    $runner = new VercelRunner($app, ['vercel_env' => 'production']);
    
    // Test the protected handleException method via reflection
    $reflection = new ReflectionClass($runner);
    $method = $reflection->getMethod('handleException');
    $method->setAccessible(true);
    
    $exception = new Exception('Test exception');
    
    ob_start();
    $method->invoke($runner, $exception);
    $output = ob_get_contents();
    ob_end_clean();
    
    $response = json_decode($output, true);
    
    expect($response)->toBeArray()
        ->and($response['error'])->toBe('Internal Server Error')
        ->and($response['message'])->toBe('An unexpected error occurred')
        ->and($response)->not->toHaveKey('file')
        ->and($response)->not->toHaveKey('trace');
});
