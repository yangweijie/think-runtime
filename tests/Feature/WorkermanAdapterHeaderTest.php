<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

test('workerman adapter uses header deduplication service', function () {
    $this->createApplication();
    $adapter = new WorkermanAdapter($this->app, []);

    // Verify the adapter has access to header deduplication service
    expect(method_exists($adapter, 'processResponseHeaders'))->toBe(true);
    expect(method_exists($adapter, 'getHeaderService'))->toBe(true);

    // Test that the header service is properly initialized
    $headerService = $adapter->getHeaderService();
    expect($headerService)->not->toBeNull();
    expect($headerService)->toBeInstanceOf(\yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface::class);
});