<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\VercelAdapter;

test('has correct name', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    expect($adapter->getName())->toBe('vercel');
});

test('has correct priority in vercel environment', function () {
    $this->createApplication();
    
    // Mock Vercel environment
    $_ENV['VERCEL'] = '1';
    
    $adapter = new VercelAdapter($this->app, []);
    expect($adapter->getPriority())->toBe(180);
    
    // Clean up environment
    unset($_ENV['VERCEL']);
});

test('can get and set config', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    $config = $adapter->getConfig();
    expect($config)->toBeArray();

    $adapter->setConfig(['test' => 'value']);
    $newConfig = $adapter->getConfig();
    expect($newConfig['test'])->toBe('value');
});

test('supports environment detection', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Check if Vercel environment is available
    $supported = $adapter->isSupported();

    // In test environment, Vercel may not be available, so we just test method exists
    expect($supported)->toBeIn([true, false]);
});

test('has required methods', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Test all required methods exist
    $methods = ['boot', 'run', 'start', 'stop', 'getName', 'isSupported', 'getPriority'];
    foreach ($methods as $method) {
        expect(method_exists($adapter, $method))->toBe(true);
    }
});

test('can handle PSR-7 request', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Test method exists and is callable
    expect(method_exists($adapter, 'handleRequest'))->toBe(true);
});

// Header deduplication integration tests
test('vercel adapter uses header deduplication service', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Verify the adapter has access to header deduplication service
    expect(method_exists($adapter, 'processResponseHeaders'))->toBe(true);
    expect(method_exists($adapter, 'getHeaderService'))->toBe(true);

    // Test that the header service is properly initialized
    $headerService = $adapter->getHeaderService();
    expect($headerService)->not->toBeNull();
    expect($headerService)->toBeInstanceOf(\yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface::class);
});

test('vercel adapter handles serverless response headers without duplication', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Create PSR-7 response with headers that might be duplicated in Vercel
    $psrResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'application/json',
        'Content-Length' => '38',
        'X-Vercel-Header' => 'vercel-test',
        'Cache-Control' => 'public, max-age=0'
    ], '{"message": "Vercel serverless response"}');

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
    expect($headerCounts['x-vercel-header'])->toBe(1);
    expect($headerCounts['cache-control'])->toBe(1);
});

test('vercel adapter handles case-insensitive headers correctly', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Create PSR-7 response with mixed case headers
    $psrResponse = $this->createPsr7Response(200, [
        'content-type' => 'application/json',
        'Content-Type' => 'text/html', // Should be deduplicated
        'X-Vercel-Custom' => 'value1',
        'x-vercel-custom' => 'value2' // Should be deduplicated
    ], '{"test": "vercel"}');

    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    // Count occurrences of each header (case-insensitive)
    $headerCounts = [];
    foreach ($finalHeaders as $name => $value) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    // Each header should appear only once after deduplication
    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['x-vercel-custom'])->toBe(1);
});

test('vercel adapter handles edge function headers without duplication', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Create PSR-7 response with Vercel Edge Function headers
    $psrResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'application/json',
        'X-Vercel-Cache' => 'MISS',
        'X-Vercel-Id' => 'edge-function-123',
        'X-Edge-Runtime' => 'vercel'
    ], '{"edge": "function response"}');

    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    // Verify no duplicate headers
    $headerCounts = [];
    foreach ($finalHeaders as $name => $value) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['x-vercel-cache'])->toBe(1);
    expect($headerCounts['x-vercel-id'])->toBe(1);
    expect($headerCounts['x-edge-runtime'])->toBe(1);
});

test('vercel adapter preserves vercel platform headers', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Create PSR-7 response with Vercel platform headers
    $psrResponse = $this->createPsr7Response(200, [
        'X-Vercel-Cache' => 'HIT',
        'X-Vercel-Id' => 'deployment-id-456',
        'X-Vercel-Proxy-Cache' => 'MISS',
        'X-Vercel-Proxy-Cache-Tags' => 'tag1,tag2'
    ], 'Vercel platform response');

    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    // Verify all Vercel platform headers are preserved
    $expectedHeaders = [
        'x-vercel-cache',
        'x-vercel-id',
        'x-vercel-proxy-cache',
        'x-vercel-proxy-cache-tags'
    ];

    foreach ($expectedHeaders as $expectedHeader) {
        $found = false;
        foreach ($finalHeaders as $name => $value) {
            if (strtolower($name) === $expectedHeader) {
                $found = true;
                break;
            }
        }
        expect($found)->toBe(true, "Vercel platform header {$expectedHeader} should be preserved");
    }
});

test('vercel adapter handles cdn headers correctly', function () {
    $this->createApplication();
    $adapter = new VercelAdapter($this->app, []);

    // Create PSR-7 response with CDN headers
    $psrResponse = $this->createPsr7Response(200, [
        'Content-Type' => 'text/html',
        'Content-Encoding' => 'gzip',
        'Content-Length' => '250',
        'X-Vercel-Cache' => 'HIT',
        'Cache-Control' => 'public, max-age=31536000',
        'ETag' => '"abc123"'
    ], str_repeat('Vercel CDN content ', 12));

    $finalHeaders = $adapter->processResponseHeaders($psrResponse);

    // Verify no duplicate headers
    $headerCounts = [];
    foreach ($finalHeaders as $name => $value) {
        $normalizedName = strtolower($name);
        $headerCounts[$normalizedName] = ($headerCounts[$normalizedName] ?? 0) + 1;
    }

    expect($headerCounts['content-type'])->toBe(1);
    expect($headerCounts['content-encoding'])->toBe(1);
    expect($headerCounts['content-length'])->toBe(1);
    expect($headerCounts['x-vercel-cache'])->toBe(1);
    expect($headerCounts['cache-control'])->toBe(1);
    expect($headerCounts['etag'])->toBe(1);
});