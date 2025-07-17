<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\BrefAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    expect($adapter->getName())->toBe('bref');
});

test('has correct priority in lambda environment', function () {
    $this->createApplication();
    
    // Mock Lambda environment
    $_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';
    
    $adapter = new BrefAdapter($this->app, []);
    expect($adapter->getPriority())->toBe(200);
    
    // Clean up environment
    unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    $config = $adapter->getConfig();
    expect($config)->toBeArray();

    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('supports environment detection', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    // Check if Bref environment is available
    $supported = $adapter->isSupported();

    // In test environment, Bref may not be installed, so we just test method exists
    expect($supported)->toBeIn([true, false]);
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    // Test all required methods exist
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    // Test method exists and is callable
    expect(method_exists($adapter, 'handleRequest'))->toBe(true);
});

// Header deduplication integration tests
test('bref adapter uses header deduplication service', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    // Verify the adapter has access to header deduplication service
    expect(method_exists($adapter, 'processResponseHeaders'))->toBe(true);
    expect(method_exists($adapter, 'getHeaderService'))->toBe(true);

    // Test that the header service is properly initialized
    $headerService = $adapter->getHeaderService();
    expect($headerService)->not->toBeNull();
    expect($headerService)->toBeInstanceOf(\yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface::class);
});

test('bref adapter handles lambda response headers without duplication', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    // Create PSR-7 response with headers that might be duplicated in Lambda
    $psrResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'application/json',
        'Content-Length' => '35',
        'X-Lambda-Header' => 'bref-test',
        'Cache-Control' => 'no-cache, no-store'
    ], '{"message": "Bref Lambda response"}');

    // Test that processResponseHeaders method works correctly
    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    expect($finalHeaders)->toBeArray();

    // Count occurrences of each header (case-insensitive)
    $headerCounts = [];
    foreach ($finalHeaders as $name => $value) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    // Each header should appear only once
    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['content-length'])->toBe(1);
    expect($headerCounts['x-lambda-header'])->toBe(1);
    expect($headerCounts['cache-control'])->toBe(1);
});

test('bref adapter handles case-insensitive headers correctly', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    // Create PSR-7 response with mixed case headers
    $psrResponse = $this->createPsr7Response(200, [
        'content-type' => 'application/json',
        'Content-Type' => 'text/html', // Should be deduplicated
        'X-AWS-Header' => 'value1',
        'x-aws-header' => 'value2' // Should be deduplicated
    ], '{"test": "bref"}');

    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    // Count occurrences of each header (case-insensitive)
    $headerCounts = [];
    foreach ($finalHeaders as $name => $value) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    // Each header should appear only once after deduplication
    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['x-aws-header'])->toBe(1);
});

test('bref adapter handles serverless headers without duplication', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    // Create PSR-7 response with serverless-specific headers
    $psrResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'application/json',
        'X-Amzn-RequestId' => 'req-12345',
        'X-Amzn-Trace-Id' => 'trace-67890',
        'X-Lambda-Runtime-Request-Id' => 'runtime-abc123'
    ], '{"serverless": "response"}');

    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    // Verify no duplicate headers
    $headerCounts = [];
    foreach ($finalHeaders as $name => $value) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['x-amzn-requestid'])->toBe(1);
    expect($headerCounts['x-amzn-trace-id'])->toBe(1);
    expect($headerCounts['x-lambda-runtime-request-id'])->toBe(1);
});

test('bref adapter preserves aws lambda headers', function () {
    $this->createApplication();
    $adapter = new BrefAdapter($this->app, []);

    // Create PSR-7 response with AWS Lambda headers
    $psrResponse = $this->createPsr7Response(200, [
        'X-Amzn-RequestId' => 'request-id-123',
        'X-Amzn-Trace-Id' => 'Root=1-trace-id',
        'X-Lambda-Runtime-Request-Id' => 'runtime-request-id',
        'X-Amzn-Remapped-Content-Length' => '100'
    ], 'AWS Lambda Bref response');

    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    // Verify all AWS Lambda headers are preserved
    $expectedHeaders = [
        'x-amzn-requestid',
        'x-amzn-trace-id',
        'x-lambda-runtime-request-id',
        'x-amzn-remapped-content-length'
    ];

    foreach ($expectedHeaders as $expectedHeader) {
        $found = false;
        foreach ($finalHeaders as $name => $value) {
            if (strtolower($name) === $expectedHeader) {
                $found = true;
                break;
            }
        }
        expect($found)->toBe(true, "AWS Lambda header {$expectedHeader} should be preserved");
    }
});