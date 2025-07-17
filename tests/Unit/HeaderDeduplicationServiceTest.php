<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface;

/**
 * HeaderDeduplicationService 综合测试套件
 * 测试HTTP头部去重服务的所有功能，包括：
 * - 头部合并逻辑
 * - 大小写不敏感的头部名称处理和标准化
 * - HTTP/1.1兼容的头部值合并
 * - 冲突解决规则和优先级处理
 */

beforeEach(function () {
    $this->service = new HeaderDeduplicationService();
});

// Interface Implementation Tests
test('implements HeaderDeduplicationInterface', function () {
    expect($this->service)->toBeInstanceOf(HeaderDeduplicationInterface::class);
});

test('has all required interface methods', function () {
    $reflection = new ReflectionClass($this->service);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $methodNames = array_map(fn($method) => $method->getName(), $methods);

    expect($methodNames)->toContain('deduplicateHeaders');
    expect($methodNames)->toContain('mergeHeaders');
    expect($methodNames)->toContain('normalizeHeaderName');
    expect($methodNames)->toContain('shouldCombineHeader');
    expect($methodNames)->toContain('combineHeaderValues');
    expect($methodNames)->toContain('detectHeaderConflicts');
    expect($methodNames)->toContain('setDebugMode');
});

// Header Name Normalization Tests
test('normalizes common headers to proper case', function () {
    $testCases = [
        'content-length' => 'Content-Length',
        'content-type' => 'Content-Type',
        'accept-encoding' => 'Accept-Encoding',
        'x-powered-by' => 'X-Powered-By',
        'access-control-allow-origin' => 'Access-Control-Allow-Origin',
        'www-authenticate' => 'WWW-Authenticate',
        'cache-control' => 'Cache-Control',
        'set-cookie' => 'Set-Cookie',
    ];

    foreach ($testCases as $input => $expected) {
        expect($this->service->normalizeHeaderName($input))->toBe($expected);
    }
});

test('handles case-insensitive header names', function () {
    $variations = [
        'content-length',
        'Content-Length',
        'CONTENT-LENGTH',
        'Content-length',
        'cOnTeNt-LeNgTh',
    ];

    foreach ($variations as $variation) {
        expect($this->service->normalizeHeaderName($variation))->toBe('Content-Length');
    }
});

test('normalizes unknown headers to pascal case', function () {
    $testCases = [
        'x-custom-header' => 'X-Custom-Header',
        'my_custom_header' => 'My-Custom-Header',
        'another-test-header' => 'Another-Test-Header',
        'UPPERCASE_HEADER' => 'Uppercase-Header',
        'mixed_Case-Header' => 'Mixed-Case-Header',
    ];

    foreach ($testCases as $input => $expected) {
        expect($this->service->normalizeHeaderName($input))->toBe($expected);
    }
});

test('handles whitespace in header names', function () {
    expect($this->service->normalizeHeaderName('  content-type  '))->toBe('Content-Type');
    expect($this->service->normalizeHeaderName("\tcache-control\n"))->toBe('Cache-Control');
});

test('handles empty and invalid header names', function () {
    expect($this->service->normalizeHeaderName(''))->toBe('');
    expect($this->service->normalizeHeaderName('   '))->toBe('');
    expect($this->service->normalizeHeaderName('-'))->toBe('-');
    expect($this->service->normalizeHeaderName('--'))->toBe('--');
});

// Header Deduplication Logic Tests
test('removes duplicate headers with same case', function () {
    $headers = [
        'Content-Type' => 'application/json',
        'Content-Type' => 'text/html', // This will overwrite the first one in PHP arrays
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    expect($result)->toHaveKey('Content-Type');
    expect($result['Content-Type'])->toBe('text/html');
    expect(count($result))->toBe(1);
});

test('removes duplicate headers with different cases', function () {
    $headers = [
        'Content-Type' => 'application/json',
        'content-type' => 'text/html',
        'CONTENT-TYPE' => 'text/xml',
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    expect($result)->toHaveKey('Content-Type');
    expect($result['Content-Type'])->toBe('application/json'); // First value wins for unique headers
    expect(count($result))->toBe(1);
});

test('combines combinable headers correctly', function () {
    $headers = [
        'Accept' => 'text/html',
        'accept' => 'application/json',
        'ACCEPT' => 'text/xml',
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    expect($result)->toHaveKey('Accept');
    expect($result['Accept'])->toBe('text/html, application/json, text/xml');
});

test('handles mixed unique and combinable headers', function () {
    $headers = [
        'Content-Type' => 'application/json',
        'content-type' => 'text/html', // Should not combine (unique header)
        'Accept' => 'text/html',
        'accept' => 'application/json', // Should combine
        'Content-Length' => '100',
        'content-length' => '200', // Should not combine (unique header)
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    expect($result['Content-Type'])->toBe('application/json'); // First wins
    expect($result['Accept'])->toBe('text/html, application/json'); // Combined
    expect($result['Content-Length'])->toBe('100'); // First wins
    expect(count($result))->toBe(3);
});

test('preserves non-duplicate headers', function () {
    $headers = [
        'Content-Type' => 'application/json',
        'Accept' => 'text/html',
        'User-Agent' => 'Mozilla/5.0',
        'Authorization' => 'Bearer token123',
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    expect($result)->toBe($headers);
    expect(count($result))->toBe(4);
});

test('handles empty headers array', function () {
    $result = $this->service->deduplicateHeaders([]);
    expect($result)->toBe([]);
});

test('handles headers with array values', function () {
    $headers = [
        'Accept' => ['text/html', 'application/json'],
        'accept' => 'text/xml',
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    expect($result)->toHaveKey('Accept');
    expect($result['Accept'])->toBe('text/html, application/json, text/xml');
});

// Header Merging Logic Tests
test('merges headers with primary taking precedence', function () {
    $primary = [
        'Content-Type' => 'application/json',
        'Accept' => 'text/html',
    ];
    
    $secondary = [
        'Content-Length' => '100',
        'Accept' => 'application/json',
        'Server' => 'nginx',
    ];

    $result = $this->service->mergeHeaders($primary, $secondary);
    
    expect($result['Content-Type'])->toBe('application/json'); // Primary wins
    expect($result['Content-Length'])->toBe('100'); // From secondary
    expect($result['Accept'])->toBe('application/json, text/html'); // Combined
    expect($result['Server'])->toBe('nginx'); // From secondary
});

test('handles unique headers in merge', function () {
    $primary = [
        'Content-Type' => 'application/json',
        'Content-Length' => '100',
    ];
    
    $secondary = [
        'Content-Type' => 'text/html',
        'Content-Length' => '200',
    ];

    $result = $this->service->mergeHeaders($primary, $secondary);
    
    expect($result['Content-Type'])->toBe('application/json'); // Primary wins
    expect($result['Content-Length'])->toBe('100'); // Primary wins
});

test('handles combinable headers in merge', function () {
    $primary = [
        'Accept' => 'text/html',
        'Cache-Control' => 'no-cache',
    ];
    
    $secondary = [
        'Accept' => 'application/json',
        'Cache-Control' => 'no-store',
    ];

    $result = $this->service->mergeHeaders($primary, $secondary);
    
    expect($result['Accept'])->toBe('application/json, text/html'); // Combined
    expect($result['Cache-Control'])->toBe('no-store, no-cache'); // Combined
});

test('handles case-insensitive merging', function () {
    $primary = [
        'Content-Type' => 'application/json',
        'accept' => 'text/html',
    ];
    
    $secondary = [
        'content-type' => 'text/html',
        'Accept' => 'application/json',
    ];

    $result = $this->service->mergeHeaders($primary, $secondary);
    
    expect($result['Content-Type'])->toBe('application/json'); // Primary wins
    expect($result['Accept'])->toBe('application/json, text/html'); // Combined
});

test('handles empty arrays in merge', function () {
    $primary = ['Content-Type' => 'application/json'];
    $secondary = [];

    $result = $this->service->mergeHeaders($primary, $secondary);
    expect($result)->toBe(['Content-Type' => 'application/json']);

    $result = $this->service->mergeHeaders([], $primary);
    expect($result)->toBe(['Content-Type' => 'application/json']);

    $result = $this->service->mergeHeaders([], []);
    expect($result)->toBe([]);
});

// HTTP/1.1 Compliant Header Value Combination Tests
test('combines cache-control headers with comma separation', function () {
    $values = ['no-cache', 'no-store', 'must-revalidate'];
    $result = $this->service->combineHeaderValues('Cache-Control', $values);
    expect($result)->toBe('no-cache, no-store, must-revalidate');
});

test('combines cookie headers with semicolon separation', function () {
    $values = ['session=abc123', 'user=john', 'theme=dark'];
    $result = $this->service->combineHeaderValues('Cookie', $values);
    expect($result)->toBe('session=abc123; user=john; theme=dark');
});

test('handles set-cookie headers specially', function () {
    $values = ['session=abc123; Path=/', 'user=john; Path=/', 'theme=dark; Path=/'];
    $result = $this->service->combineHeaderValues('Set-Cookie', $values);
    expect($result)->toBe('session=abc123; Path=/'); // Only first value
});

test('combines accept headers with comma separation', function () {
    $values = ['text/html', 'application/json', 'text/xml'];
    $result = $this->service->combineHeaderValues('Accept', $values);
    expect($result)->toBe('text/html, application/json, text/xml');
});

test('combines pragma headers with comma separation', function () {
    $values = ['no-cache', 'no-store'];
    $result = $this->service->combineHeaderValues('Pragma', $values);
    expect($result)->toBe('no-cache, no-store');
});

test('removes duplicate values when combining', function () {
    $values = ['no-cache', 'no-store', 'no-cache', 'must-revalidate'];
    $result = $this->service->combineHeaderValues('Cache-Control', $values);
    expect($result)->toBe('no-cache, no-store, must-revalidate');
});

test('filters empty values when combining', function () {
    $values = ['no-cache', '', 'no-store', null, 'must-revalidate'];
    $result = $this->service->combineHeaderValues('Cache-Control', $values);
    expect($result)->toBe('no-cache, no-store, must-revalidate');
});

test('handles single value arrays', function () {
    $values = ['application/json'];
    $result = $this->service->combineHeaderValues('Accept', $values);
    expect($result)->toBe('application/json');
});

test('handles empty value arrays', function () {
    $values = [];
    $result = $this->service->combineHeaderValues('Accept', $values);
    expect($result)->toBe('');
});

// Header Combination Rules Tests
test('identifies combinable headers correctly', function () {
    $combinableHeaders = [
        'Accept', 'Accept-Charset', 'Accept-Encoding', 'Accept-Language',
        'Cache-Control', 'Connection', 'Cookie', 'Pragma', 'Upgrade',
        'Via', 'Warning', 'Vary', 'Access-Control-Allow-Methods',
        'Access-Control-Allow-Headers'
    ];

    foreach ($combinableHeaders as $header) {
        expect($this->service->shouldCombineHeader($header))->toBeTrue();
    }
});

test('identifies unique headers correctly', function () {
    $uniqueHeaders = [
        'Content-Length', 'Content-Type', 'Content-Encoding',
        'Host', 'Authorization', 'Date', 'Expires', 'Last-Modified',
        'ETag', 'Location', 'Server', 'User-Agent', 'Referer',
        'WWW-Authenticate', 'Access-Control-Allow-Origin',
        'Access-Control-Allow-Credentials', 'Access-Control-Max-Age'
    ];

    foreach ($uniqueHeaders as $header) {
        expect($this->service->shouldCombineHeader($header))->toBeFalse();
        expect($this->service->isUniqueHeader($header))->toBeTrue();
    }
});

test('handles unknown headers as non-combinable', function () {
    $unknownHeaders = ['X-Custom-Header', 'My-Special-Header', 'Unknown-Header'];

    foreach ($unknownHeaders as $header) {
        expect($this->service->shouldCombineHeader($header))->toBeFalse();
    }
});

// Conflict Detection and Resolution Tests
test('detects header conflicts correctly', function () {
    $headers1 = [
        'Content-Type' => 'application/json',
        'Accept' => 'text/html',
        'User-Agent' => 'Mozilla/5.0',
    ];
    
    $headers2 = [
        'Content-Type' => 'text/html',
        'Server' => 'nginx',
        'Accept' => 'application/json',
    ];

    $conflicts = $this->service->detectHeaderConflicts($headers1, $headers2);
    
    expect($conflicts)->toContain('Content-Type');
    expect($conflicts)->toContain('Accept');
    expect($conflicts)->not->toContain('User-Agent');
    expect($conflicts)->not->toContain('Server');
    expect(count($conflicts))->toBe(2);
});

test('detects case-insensitive conflicts', function () {
    $headers1 = [
        'Content-Type' => 'application/json',
        'accept' => 'text/html',
    ];
    
    $headers2 = [
        'content-type' => 'text/html',
        'Accept' => 'application/json',
    ];

    $conflicts = $this->service->detectHeaderConflicts($headers1, $headers2);
    
    expect($conflicts)->toContain('Content-Type');
    expect($conflicts)->toContain('Accept');
    expect(count($conflicts))->toBe(2);
});

test('handles no conflicts', function () {
    $headers1 = [
        'Content-Type' => 'application/json',
        'Accept' => 'text/html',
    ];
    
    $headers2 = [
        'Server' => 'nginx',
        'User-Agent' => 'Mozilla/5.0',
    ];

    $conflicts = $this->service->detectHeaderConflicts($headers1, $headers2);
    expect($conflicts)->toBe([]);
});

test('resolves header priority correctly', function () {
    // Content-Length: PSR-7 value should take precedence
    $result = $this->service->resolveHeaderPriority('Content-Length', '100', '200');
    expect($result)->toBe('100');

    $result = $this->service->resolveHeaderPriority('Content-Length', '', '200');
    expect($result)->toBe('200');

    $result = $this->service->resolveHeaderPriority('Content-Length', null, '200');
    expect($result)->toBe('200');

    // Content-Type: Application-set should take precedence
    $result = $this->service->resolveHeaderPriority('Content-Type', 'application/json', 'text/html');
    expect($result)->toBe('application/json');

    // Content-Encoding: Runtime should take precedence
    $result = $this->service->resolveHeaderPriority('Content-Encoding', 'deflate', 'gzip');
    expect($result)->toBe('gzip');

    $result = $this->service->resolveHeaderPriority('Content-Encoding', 'deflate', '');
    expect($result)->toBe('deflate');

    // Server: Runtime should take precedence
    $result = $this->service->resolveHeaderPriority('Server', 'Apache/2.4', 'nginx/1.18');
    expect($result)->toBe('nginx/1.18');

    // Default: PSR-7 should take precedence
    $result = $this->service->resolveHeaderPriority('X-Custom-Header', 'psr7-value', 'runtime-value');
    expect($result)->toBe('psr7-value');

    $result = $this->service->resolveHeaderPriority('X-Custom-Header', '', 'runtime-value');
    expect($result)->toBe('runtime-value');
});

// Debug Mode and Logging Tests
test('enables and disables debug mode', function () {
    // Test enabling debug mode
    $this->service->setDebugMode(true);
    expect(true)->toBeTrue(); // No exception thrown

    // Test disabling debug mode
    $this->service->setDebugMode(false);
    expect(true)->toBeTrue(); // No exception thrown
});

test('debug mode affects logging behavior', function () {
    // Enable debug mode
    $this->service->setDebugMode(true);
    
    // Test with headers that will cause conflicts
    $headers = [
        'Content-Type' => 'application/json',
        'content-type' => 'text/html',
    ];

    // This should trigger debug logging (we can't easily test the actual logging output)
    $result = $this->service->deduplicateHeaders($headers);
    expect($result)->toHaveKey('Content-Type');
    
    // Disable debug mode
    $this->service->setDebugMode(false);
    
    // Same operation should not log
    $result = $this->service->deduplicateHeaders($headers);
    expect($result)->toHaveKey('Content-Type');
});

// Edge Cases and Error Handling Tests
test('handles malformed header arrays', function () {
    $headers = [
        '' => 'empty-name',
        'Valid-Header' => '',
        'Another-Header' => null,
        'Normal-Header' => 'normal-value',
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    // Should handle gracefully without throwing exceptions
    expect($result)->toBeArray();
    expect($result)->toHaveKey('Normal-Header');
    expect($result['Normal-Header'])->toBe('normal-value');
});

test('handles very long header values', function () {
    $longValue = str_repeat('a', 8192); // 8KB header value
    $headers = [
        'X-Long-Header' => $longValue,
        'x-long-header' => 'short',
    ];

    $result = $this->service->deduplicateHeaders($headers);
    expect($result)->toHaveKey('X-Long-Header');
    expect($result['X-Long-Header'])->toBe($longValue); // First value wins
});

test('handles special characters in header names', function () {
    $headers = [
        'X-Special-Chars-Header' => 'value1',
        'x_special_chars_header' => 'value2', // Underscore variant
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    // Both should normalize to the same header name, so only one should remain
    expect($result)->toHaveKey('X-Special-Chars-Header');
    expect(count($result))->toBe(1); // Same normalized name, so deduplicated
    expect($result['X-Special-Chars-Header'])->toBe('value1'); // First value wins
});

test('handles numeric header values', function () {
    $headers = [
        'Content-Length' => 100,
        'content-length' => '200',
        'X-Numeric' => 42,
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    expect($result['Content-Length'])->toBe(100); // First value wins
    expect($result['X-Numeric'])->toBe(42);
});

test('handles boolean header values', function () {
    $headers = [
        'X-Boolean-True' => true,
        'X-Boolean-False' => false,
    ];

    $result = $this->service->deduplicateHeaders($headers);
    
    expect($result['X-Boolean-True'])->toBe(true);
    expect($result['X-Boolean-False'])->toBe(false);
});

// Additional comprehensive tests for complete coverage
test('handles complex header merging scenarios', function () {
    $primary = [
        'Accept' => 'text/html',
        'Content-Type' => 'application/json',
        'X-Custom' => 'primary-value',
    ];
    
    $secondary = [
        'accept' => 'application/json', // Should combine
        'content-type' => 'text/html', // Should be overridden by primary
        'Server' => 'nginx', // Should be added
        'x-custom' => 'secondary-value', // Should be overridden by primary
    ];

    $result = $this->service->mergeHeaders($primary, $secondary);
    
    expect($result['Accept'])->toBe('application/json, text/html'); // Combined
    expect($result['Content-Type'])->toBe('application/json'); // Primary wins
    expect($result['Server'])->toBe('nginx'); // From secondary
    expect($result['X-Custom'])->toBe('primary-value'); // Primary wins
});

test('handles multiple header value combinations', function () {
    // Test with multiple different header types
    $testCases = [
        ['Via', ['1.1 proxy1', '1.1 proxy2'], '1.1 proxy1, 1.1 proxy2'],
        ['Warning', ['199 Miscellaneous warning', '214 Transformation applied'], '199 Miscellaneous warning, 214 Transformation applied'],
        ['Connection', ['keep-alive', 'upgrade'], 'keep-alive, upgrade'],
        ['Upgrade', ['websocket', 'h2c'], 'websocket, h2c'],
    ];

    foreach ($testCases as [$header, $values, $expected]) {
        $result = $this->service->combineHeaderValues($header, $values);
        expect($result)->toBe($expected);
    }
});

test('validates header normalization edge cases', function () {
    $edgeCases = [
        'content--length' => 'Content--Length', // Double dash
        'x-' => 'X-', // Trailing dash
        '-x' => '-X', // Leading dash
        'a' => 'A', // Single character
        'x-a-b-c-d-e-f' => 'X-A-B-C-D-E-F', // Many segments
    ];

    foreach ($edgeCases as $input => $expected) {
        expect($this->service->normalizeHeaderName($input))->toBe($expected);
    }
});

test('comprehensive conflict detection with mixed cases', function () {
    $headers1 = [
        'Content-Type' => 'application/json',
        'accept-encoding' => 'gzip',
        'X-Custom-Header' => 'value1',
        'cache-control' => 'no-cache',
    ];
    
    $headers2 = [
        'content-type' => 'text/html',
        'Accept-Encoding' => 'deflate',
        'x-custom-header' => 'value2',
        'Authorization' => 'Bearer token',
    ];

    $conflicts = $this->service->detectHeaderConflicts($headers1, $headers2);
    
    expect($conflicts)->toContain('Content-Type');
    expect($conflicts)->toContain('Accept-Encoding');
    expect($conflicts)->toContain('X-Custom-Header');
    expect($conflicts)->not->toContain('Cache-Control');
    expect($conflicts)->not->toContain('Authorization');
    expect(count($conflicts))->toBe(3);
});

test('priority resolution with null and empty values', function () {
    // Test all priority resolution scenarios with edge cases
    $testCases = [
        ['Content-Length', null, null, null],
        ['Content-Length', '', '', ''],
        ['Content-Type', null, 'runtime', 'runtime'],
        ['Content-Encoding', 'psr7', null, 'psr7'],
        ['Server', null, 'runtime', 'runtime'],
        ['X-Custom', null, null, null],
    ];

    foreach ($testCases as [$header, $psrValue, $runtimeValue, $expected]) {
        $result = $this->service->resolveHeaderPriority($header, $psrValue, $runtimeValue);
        expect($result)->toBe($expected);
    }
});

// Debug Logging and Error Handling Tests
test('constructor accepts logger and config', function () {
    $logger = new \Psr\Log\NullLogger();
    $config = [
        'debug_logging' => true,
        'strict_mode' => true,
        'max_header_value_length' => 4096,
    ];
    
    $service = new HeaderDeduplicationService($logger, $config);
    
    expect($service->getConfig())->toMatchArray($config);
});

test('setConfig updates configuration correctly', function () {
    $newConfig = [
        'debug_logging' => true,
        'strict_mode' => false,
        'log_critical_conflicts' => false,
    ];
    
    $this->service->setConfig($newConfig);
    $config = $this->service->getConfig();
    
    expect($config['debug_logging'])->toBe(true);
    expect($config['strict_mode'])->toBe(false);
    expect($config['log_critical_conflicts'])->toBe(false);
});

test('setStrictMode updates strict mode setting', function () {
    $this->service->setStrictMode(true);
    $config = $this->service->getConfig();
    expect($config['strict_mode'])->toBe(true);
    
    $this->service->setStrictMode(false);
    $config = $this->service->getConfig();
    expect($config['strict_mode'])->toBe(false);
});

test('validates header names and throws exceptions for invalid headers', function () {
    $service = new HeaderDeduplicationService(null, ['throw_on_merge_failure' => true]);
    
    // Test empty header name
    expect(function () use ($service) {
        $service->deduplicateHeaders(['' => 'value']);
    })->toThrow(\yangweijie\thinkRuntime\exception\HeaderMergingException::class);
    
    // Test invalid characters in header name
    expect(function () use ($service) {
        $service->deduplicateHeaders(['invalid@header' => 'value']);
    })->toThrow(\yangweijie\thinkRuntime\exception\HeaderMergingException::class);
    
    // Test header value with newlines
    expect(function () use ($service) {
        $service->deduplicateHeaders(['Valid-Header' => "value\nwith\nnewlines"]);
    })->toThrow(\yangweijie\thinkRuntime\exception\HeaderMergingException::class);
});

test('validates header value length', function () {
    $service = new HeaderDeduplicationService(null, [
        'throw_on_merge_failure' => true,
        'max_header_value_length' => 100
    ]);
    
    $longValue = str_repeat('a', 101);
    
    expect(function () use ($service, $longValue) {
        $service->deduplicateHeaders(['X-Long-Header' => $longValue]);
    })->toThrow(\yangweijie\thinkRuntime\exception\HeaderMergingException::class);
});

test('handles validation errors gracefully when throw_on_merge_failure is false', function () {
    $service = new HeaderDeduplicationService(null, ['throw_on_merge_failure' => false]);
    
    // Should not throw exception, but return original headers
    $headers = ['' => 'empty-name', 'Valid-Header' => 'valid-value'];
    $result = $service->deduplicateHeaders($headers);
    
    expect($result)->toBeArray();
    // Should return original headers as fallback
    expect($result)->toBe($headers);
});

test('detects and handles critical header conflicts', function () {
    $service = new HeaderDeduplicationService(null, [
        'debug_logging' => true,
        'log_critical_conflicts' => true
    ]);
    
    $headers = [
        'Content-Length' => '100',
        'content-length' => '200', // Critical conflict
        'Authorization' => 'Bearer token1',
        'authorization' => 'Bearer token2', // Critical conflict
    ];
    
    $result = $service->deduplicateHeaders($headers);
    
    // Should resolve conflicts without throwing exceptions
    expect($result)->toHaveKey('Content-Length');
    expect($result)->toHaveKey('Authorization');
    expect($result['Content-Length'])->toBe('100'); // First value wins
    expect($result['Authorization'])->toBe('Bearer token1'); // First value wins
});

test('throws exceptions in strict mode for critical conflicts', function () {
    $service = new HeaderDeduplicationService(null, [
        'strict_mode' => true,
        'throw_on_merge_failure' => true
    ]);
    
    $headers = [
        'Content-Length' => '100',
        'content-length' => '200', // Critical conflict
    ];
    
    expect(function () use ($service, $headers) {
        $service->deduplicateHeaders($headers);
    })->toThrow(\yangweijie\thinkRuntime\exception\HeaderConflictException::class);
});

test('getHeaderStats provides comprehensive statistics', function () {
    $headers = [
        'Content-Type' => 'application/json',
        'content-type' => 'text/html', // Conflict
        'Accept' => 'text/html',
        'accept' => 'application/json', // Conflict (combinable)
        'Content-Length' => '100', // Critical header
        'Server' => 'nginx',
    ];
    
    $stats = $this->service->getHeaderStats($headers);
    
    expect($stats['total_headers'])->toBe(6);
    expect($stats['unique_headers'])->toBe(4); // Content-Type, Accept, Content-Length, Server
    expect($stats['potential_conflicts'])->toContain('Content-Type');
    expect($stats['potential_conflicts'])->toContain('Accept');
    expect($stats['critical_headers'])->toBe(3); // Content-Type (2 instances) and Content-Length (1 instance) = 3 total
    expect($stats['combinable_headers'])->toBe(2); // Accept appears twice
});

test('handles merge failures gracefully', function () {
    // Create a service that will fail on header combination
    $service = new HeaderDeduplicationService(null, ['throw_on_merge_failure' => false]);
    
    $primary = [
        'Accept' => 'text/html',
    ];
    
    $secondary = [
        'accept' => 'application/json',
    ];
    
    // This should not throw an exception even if internal merge fails
    $result = $service->mergeHeaders($primary, $secondary);
    
    expect($result)->toBeArray();
    expect($result)->toHaveKey('Accept');
});

test('logs performance metrics when enabled', function () {
    $service = new HeaderDeduplicationService(null, [
        'enable_performance_logging' => true,
        'debug_logging' => true
    ]);
    
    // Create headers with string keys to avoid validation issues
    $headers = [];
    for ($i = 1; $i <= 100; $i++) {
        $headers["X-Header-{$i}"] = "value{$i}";
    }
    
    // Should complete without errors and log performance
    $result = $service->deduplicateHeaders($headers);
    expect($result)->toBeArray();
    expect(count($result))->toBe(100);
});

test('exception classes work correctly', function () {
    // Test HeaderMergingException
    $exception = \yangweijie\thinkRuntime\exception\HeaderMergingException::duplicateHeader(
        'Content-Type', 
        'application/json', 
        'text/html'
    );
    
    expect($exception->getHeaderName())->toBe('Content-Type');
    expect($exception->getConflictingValues())->toBe(['application/json', 'text/html']);
    expect($exception->getMessage())->toContain('Duplicate header');
    
    // Test HeaderConflictException
    $conflicts = ['Content-Length' => ['100', '200']];
    $exception = \yangweijie\thinkRuntime\exception\HeaderConflictException::criticalConflict($conflicts);
    
    expect($exception->getConflicts())->toBe($conflicts);
    expect($exception->getResolutionStrategy())->toBe('abort');
    expect($exception->getMessage())->toContain('Critical header conflicts');
});

test('handles complex merge scenarios with logging', function () {
    $service = new HeaderDeduplicationService(null, [
        'debug_logging' => true,
        'log_critical_conflicts' => true,
        'enable_performance_logging' => true
    ]);
    
    $primary = [
        'Content-Type' => 'application/json', // Critical, should win
        'Accept' => 'text/html', // Combinable
        'Content-Length' => '100', // Critical, should win
        'Cache-Control' => 'no-cache', // Combinable
    ];
    
    $secondary = [
        'content-type' => 'text/html', // Critical conflict
        'accept' => 'application/json', // Should combine
        'content-length' => '200', // Critical conflict
        'cache-control' => 'no-store', // Should combine
        'Server' => 'nginx', // New header
    ];
    
    $result = $service->mergeHeaders($primary, $secondary);
    
    expect($result['Content-Type'])->toBe('application/json'); // Primary wins
    expect($result['Accept'])->toBe('application/json, text/html'); // Combined
    expect($result['Content-Length'])->toBe('100'); // Primary wins
    expect($result['Cache-Control'])->toBe('no-store, no-cache'); // Combined
    expect($result['Server'])->toBe('nginx'); // From secondary
});

test('handles array header values in validation', function () {
    $service = new HeaderDeduplicationService(null, ['throw_on_merge_failure' => true]);
    
    $headers = [
        'Accept' => ['text/html', 'application/json'],
        'X-Custom' => ['value1', 'value2'],
    ];
    
    // Should handle array values without throwing exceptions
    $result = $service->deduplicateHeaders($headers);
    expect($result)->toHaveKey('Accept');
    expect($result)->toHaveKey('X-Custom');
});

test('truncateValue works correctly for logging', function () {
    $service = new HeaderDeduplicationService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('truncateValue');
    $method->setAccessible(true);
    
    // Test short value
    $result = $method->invoke($service, 'short');
    expect($result)->toBe('short');
    
    // Test long value
    $longValue = str_repeat('a', 150);
    $result = $method->invoke($service, $longValue, 100);
    expect($result)->toBe(str_repeat('a', 100) . '...');
    
    // Test array value
    $result = $method->invoke($service, ['value1', 'value2']);
    expect($result)->toBe('value1, value2');
});

test('isCriticalHeader identifies critical headers correctly', function () {
    $service = new HeaderDeduplicationService();
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('isCriticalHeader');
    $method->setAccessible(true);
    
    $criticalHeaders = [
        'Content-Length', 'Content-Type', 'Authorization', 
        'Host', 'Location', 'Set-Cookie'
    ];
    
    foreach ($criticalHeaders as $header) {
        expect($method->invoke($service, $header))->toBe(true);
    }
    
    // Test non-critical header
    expect($method->invoke($service, 'Accept'))->toBe(false);
    expect($method->invoke($service, 'X-Custom-Header'))->toBe(false);
});