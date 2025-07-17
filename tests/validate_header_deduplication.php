<?php

declare(strict_types=1);

/**
 * Header Deduplication Validation Script
 * 
 * Comprehensive validation of the header deduplication fix
 * Tests various scenarios to ensure duplicate headers are eliminated
 */

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use yangweijie\thinkRuntime\adapter\WorkermanAdapter;
use yangweijie\thinkRuntime\adapter\SwooleAdapter;
use yangweijie\thinkRuntime\adapter\ReactPHPAdapter;

class HeaderDeduplicationValidator
{
    private HeaderDeduplicationService $headerService;
    private array $testResults = [];
    private int $totalTests = 0;
    private int $passedTests = 0;

    public function __construct()
    {
        $this->headerService = new HeaderDeduplicationService();
    }

    /**
     * Run all validation tests
     */
    public function runAllTests(): void
    {
        echo "🧪 Starting Header Deduplication Validation Tests\n";
        echo "=" . str_repeat("=", 50) . "\n\n";

        $this->testBasicHeaderDeduplication();
        $this->testContentLengthDeduplication();
        $this->testCaseInsensitiveDeduplication();
        $this->testComplexHeaderScenarios();
        $this->testBrowserCompatibilityScenarios();
        $this->testCompressionScenarios();
        $this->testCorsScenarios();
        $this->testRealWorldScenarios();
        $this->testAdapterIntegration();

        $this->printSummary();
    }

    /**
     * Test basic header deduplication
     */
    private function testBasicHeaderDeduplication(): void
    {
        echo "📋 Testing Basic Header Deduplication\n";
        echo "-" . str_repeat("-", 40) . "\n";

        // Test 1: Simple duplicate headers
        $psrHeaders = [
            'Content-Type' => ['application/json'],
            'Content-Length' => ['100'],
        ];
        $runtimeHeaders = [
            'Content-Type' => ['text/html'],
            'Server' => ['ThinkPHP-Runtime'],
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        $this->assertTest(
            'Simple header merge',
            count($result) === 3 && isset($result['Content-Type']) && count($result['Content-Type']) === 1,
            'Headers should be merged without duplicates'
        );

        // Test 2: No conflicts
        $psrHeaders = ['X-Custom' => ['value1']];
        $runtimeHeaders = ['Server' => ['ThinkPHP']];
        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        $this->assertTest(
            'No conflicts merge',
            count($result) === 2,
            'Non-conflicting headers should be preserved'
        );

        echo "\n";
    }

    /**
     * Test Content-Length specific deduplication
     */
    private function testContentLengthDeduplication(): void
    {
        echo "📏 Testing Content-Length Deduplication\n";
        echo "-" . str_repeat("-", 40) . "\n";

        // Test various Content-Length scenarios
        $scenarios = [
            'PSR-7 wins' => [
                'psr' => ['Content-Length' => ['150']],
                'runtime' => ['Content-Length' => ['100']],
                'expected' => '150'
            ],
            'Runtime only' => [
                'psr' => [],
                'runtime' => ['Content-Length' => ['200']],
                'expected' => '200'
            ],
            'Case insensitive' => [
                'psr' => ['content-length' => ['300']],
                'runtime' => ['Content-Length' => ['250']],
                'expected' => '300'
            ],
        ];

        foreach ($scenarios as $name => $scenario) {
            $result = $this->headerService->mergeHeaders($scenario['psr'], $scenario['runtime']);
            $contentLength = $this->getHeaderValue($result, 'Content-Length');
            
            $this->assertTest(
                "Content-Length: {$name}",
                $contentLength === $scenario['expected'],
                "Expected Content-Length: {$scenario['expected']}, got: {$contentLength}"
            );
        }

        echo "\n";
    }

    /**
     * Test case-insensitive header deduplication
     */
    private function testCaseInsensitiveDeduplication(): void
    {
        echo "🔤 Testing Case-Insensitive Deduplication\n";
        echo "-" . str_repeat("-", 40) . "\n";

        $testCases = [
            ['Content-Type', 'content-type'],
            ['Content-Length', 'CONTENT-LENGTH'],
            ['Server', 'server'],
            ['X-Custom-Header', 'x-custom-header'],
        ];

        foreach ($testCases as [$header1, $header2]) {
            $psrHeaders = [$header1 => ['value1']];
            $runtimeHeaders = [$header2 => ['value2']];
            
            $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
            
            // Should have only one header with the normalized name
            $normalizedName = $this->headerService->normalizeHeaderName($header1);
            $hasOnlyOne = isset($result[$normalizedName]) && count($result) === 1;
            
            $this->assertTest(
                "Case insensitive: {$header1} vs {$header2}",
                $hasOnlyOne,
                "Should merge case-insensitive headers into one"
            );
        }

        echo "\n";
    }

    /**
     * Test complex header scenarios
     */
    private function testComplexHeaderScenarios(): void
    {
        echo "🔧 Testing Complex Header Scenarios\n";
        echo "-" . str_repeat("-", 40) . "\n";

        // Test 1: Multiple header sources
        $psrHeaders = [
            'Content-Type' => ['application/json'],
            'X-App-Version' => ['1.0.0'],
        ];
        $runtimeHeaders = [
            'Server' => ['ThinkPHP-Runtime'],
            'Date' => [gmdate('D, d M Y H:i:s T')],
        ];
        $middlewareHeaders = [
            'X-Frame-Options' => ['DENY'],
            'Content-Type' => ['application/json; charset=utf-8'], // Conflict
        ];

        // Merge in stages like real application would
        $intermediate = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        $final = $this->headerService->mergeHeaders($intermediate, $middlewareHeaders);

        $this->assertTest(
            'Multi-stage header merge',
            count($final) === 5 && isset($final['Content-Type']),
            'Should handle multi-stage header merging'
        );

        // Test 2: Empty header arrays
        $result = $this->headerService->mergeHeaders([], []);
        $this->assertTest(
            'Empty headers merge',
            empty($result),
            'Empty headers should result in empty array'
        );

        echo "\n";
    }

    /**
     * Test browser compatibility scenarios
     */
    private function testBrowserCompatibilityScenarios(): void
    {
        echo "🌐 Testing Browser Compatibility Scenarios\n";
        echo "-" . str_repeat("-", 40) . "\n";

        $browserScenarios = [
            'Chrome request' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
            ],
            'Firefox request' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ];

        foreach ($browserScenarios as $browser => $requestHeaders) {
            // Simulate response headers that might conflict
            $psrHeaders = [
                'Content-Type' => ['text/html; charset=UTF-8'],
                'Content-Length' => ['1024'],
            ];
            $runtimeHeaders = [
                'Server' => ['ThinkPHP-Runtime'],
                'Content-Type' => ['text/html'], // Potential conflict
                'Date' => [gmdate('D, d M Y H:i:s T')],
            ];

            $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
            
            $this->assertTest(
                "Browser scenario: {$browser}",
                $this->hasNoDuplicateHeaders($result),
                "Should handle {$browser} without duplicate headers"
            );
        }

        echo "\n";
    }

    /**
     * Test compression scenarios
     */
    private function testCompressionScenarios(): void
    {
        echo "🗜️ Testing Compression Scenarios\n";
        echo "-" . str_repeat("-", 40) . "\n";

        // Test gzip compression scenario
        $originalContent = str_repeat('Test content ', 100);
        $compressedContent = gzcompress($originalContent);

        $psrHeaders = [
            'Content-Type' => ['text/html'],
            'Content-Length' => [strlen($originalContent)], // Original length
        ];
        $runtimeHeaders = [
            'Content-Encoding' => ['gzip'],
            'Content-Length' => [strlen($compressedContent)], // Compressed length - conflict!
            'Vary' => ['Accept-Encoding'],
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'Gzip compression headers',
            isset($result['Content-Encoding']) && 
            isset($result['Content-Length']) && 
            count($result['Content-Length']) === 1,
            'Should handle compression headers without duplicates'
        );

        // Test brotli compression
        $runtimeHeaders['Content-Encoding'] = ['br'];
        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'Brotli compression headers',
            $result['Content-Encoding'][0] === 'br',
            'Should handle brotli compression headers'
        );

        echo "\n";
    }

    /**
     * Test CORS scenarios
     */
    private function testCorsScenarios(): void
    {
        echo "🌍 Testing CORS Scenarios\n";
        echo "-" . str_repeat("-", 40) . "\n";

        // Test CORS preflight response
        $psrHeaders = [
            'Access-Control-Allow-Origin' => ['https://example.com'],
            'Access-Control-Allow-Methods' => ['GET, POST'],
        ];
        $runtimeHeaders = [
            'Access-Control-Allow-Origin' => ['*'], // Conflict - should use PSR-7
            'Access-Control-Allow-Headers' => ['Content-Type, Authorization'],
            'Access-Control-Max-Age' => ['86400'],
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'CORS preflight headers',
            $result['Access-Control-Allow-Origin'][0] === 'https://example.com' &&
            isset($result['Access-Control-Allow-Headers']),
            'Should handle CORS headers with PSR-7 taking precedence'
        );

        // Test CORS with credentials
        $psrHeaders = [
            'Access-Control-Allow-Credentials' => ['true'],
            'Access-Control-Allow-Origin' => ['https://app.example.com'],
        ];
        $runtimeHeaders = [
            'Access-Control-Allow-Origin' => ['*'], // Invalid with credentials
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'CORS with credentials',
            $result['Access-Control-Allow-Origin'][0] === 'https://app.example.com',
            'Should preserve specific origin when credentials are allowed'
        );

        echo "\n";
    }

    /**
     * Test real-world scenarios
     */
    private function testRealWorldScenarios(): void
    {
        echo "🌍 Testing Real-World Scenarios\n";
        echo "-" . str_repeat("-", 40) . "\n";

        // Test 1: File upload response
        $psrHeaders = [
            'Content-Type' => ['application/json'],
            'Content-Length' => ['156'],
            'X-Upload-Status' => ['success'],
        ];
        $runtimeHeaders = [
            'Server' => ['ThinkPHP-Runtime'],
            'Content-Length' => ['156'], // Duplicate
            'Date' => [gmdate('D, d M Y H:i:s T')],
            'X-Request-ID' => [uniqid()],
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'File upload response',
            count($result['Content-Length']) === 1 && isset($result['X-Upload-Status']),
            'Should handle file upload response headers correctly'
        );

        // Test 2: API response with caching
        $psrHeaders = [
            'Content-Type' => ['application/json'],
            'Cache-Control' => ['public, max-age=3600'],
            'ETag' => ['"abc123"'],
        ];
        $runtimeHeaders = [
            'Cache-Control' => ['no-cache'], // Conflict
            'Last-Modified' => [gmdate('D, d M Y H:i:s T')],
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'API response with caching',
            $result['Cache-Control'][0] === 'public, max-age=3600',
            'Should preserve application cache control settings'
        );

        echo "\n";
    }

    /**
     * Test adapter integration
     */
    private function testAdapterIntegration(): void
    {
        echo "🔌 Testing Adapter Integration\n";
        echo "-" . str_repeat("-", 40) . "\n";

        // Create mock application
        $app = $this->createMockApp();

        // Test WorkermanAdapter
        if (class_exists(WorkermanAdapter::class)) {
            $adapter = new WorkermanAdapter($app, []);
            $this->assertTest(
                'WorkermanAdapter has header service',
                method_exists($adapter, 'getHeaderService'),
                'WorkermanAdapter should have header deduplication service'
            );
        }

        // Test SwooleAdapter
        if (class_exists(SwooleAdapter::class)) {
            $adapter = new SwooleAdapter($app, []);
            $this->assertTest(
                'SwooleAdapter has header service',
                method_exists($adapter, 'getHeaderService'),
                'SwooleAdapter should have header deduplication service'
            );
        }

        // Test ReactPHPAdapter
        if (class_exists(ReactPHPAdapter::class)) {
            $adapter = new ReactPHPAdapter($app, []);
            $this->assertTest(
                'ReactPHPAdapter has header service',
                method_exists($adapter, 'getHeaderService'),
                'ReactPHPAdapter should have header deduplication service'
            );
        }

        echo "\n";
    }

    /**
     * Assert a test result
     */
    private function assertTest(string $testName, bool $condition, string $message): void
    {
        $this->totalTests++;
        
        if ($condition) {
            $this->passedTests++;
            echo "✅ {$testName}\n";
            $this->testResults[] = ['name' => $testName, 'status' => 'PASS', 'message' => ''];
        } else {
            echo "❌ {$testName}: {$message}\n";
            $this->testResults[] = ['name' => $testName, 'status' => 'FAIL', 'message' => $message];
        }
    }

    /**
     * Print test summary
     */
    private function printSummary(): void
    {
        echo "\n" . "=" . str_repeat("=", 50) . "\n";
        echo "📊 Test Summary\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        echo "Total Tests: {$this->totalTests}\n";
        echo "Passed: {$this->passedTests}\n";
        echo "Failed: " . ($this->totalTests - $this->passedTests) . "\n";
        
        $successRate = $this->totalTests > 0 ? ($this->passedTests / $this->totalTests) * 100 : 0;
        echo "Success Rate: " . number_format($successRate, 1) . "%\n";

        if ($this->passedTests === $this->totalTests) {
            echo "\n🎉 All tests passed! Header deduplication is working correctly.\n";
        } else {
            echo "\n⚠️  Some tests failed. Please review the implementation.\n";
            
            echo "\nFailed Tests:\n";
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "- {$result['name']}: {$result['message']}\n";
                }
            }
        }
    }

    /**
     * Get header value from result array
     */
    private function getHeaderValue(array $headers, string $headerName): ?string
    {
        $normalizedName = $this->headerService->normalizeHeaderName($headerName);
        return isset($headers[$normalizedName]) ? $headers[$normalizedName][0] : null;
    }

    /**
     * Check if headers array has no duplicates
     */
    private function hasNoDuplicateHeaders(array $headers): bool
    {
        foreach ($headers as $values) {
            if (is_array($values) && count($values) > 1) {
                // Check if it's a combinable header
                return false; // For simplicity, assume no duplicates allowed
            }
        }
        return true;
    }

    /**
     * Create mock application for testing
     */
    private function createMockApp(): object
    {
        return new class {
            public function isDebug(): bool { return true; }
            public function getRootPath(): string { return __DIR__ . '/../'; }
        };
    }
}

// Run the validation if this script is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $validator = new HeaderDeduplicationValidator();
    $validator->runAllTests();
}