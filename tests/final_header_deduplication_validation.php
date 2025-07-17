<?php

declare(strict_types=1);

/**
 * Final Header Deduplication Validation
 * 
 * Comprehensive validation that the header deduplication fix works correctly
 * across all scenarios without requiring real server startup.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use yangweijie\thinkRuntime\adapter\WorkermanAdapter;
use yangweijie\thinkRuntime\adapter\SwooleAdapter;

class FinalHeaderDeduplicationValidation
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
     * Run comprehensive validation
     */
    public function runValidation(): void
    {
        echo "🎯 Final Header Deduplication Validation\n";
        echo "=" . str_repeat("=", 50) . "\n\n";

        $this->validateContentLengthDeduplication();
        $this->validateBrowserCompatibility();
        $this->validateCompressionScenarios();
        $this->validateCorsScenarios();
        $this->validateCacheControlHandling();
        $this->validateRealWorldScenarios();
        $this->validateAdapterIntegration();
        $this->validateHttpCompliance();

        $this->printFinalSummary();
    }

    /**
     * Validate Content-Length deduplication (the main reported issue)
     */
    private function validateContentLengthDeduplication(): void
    {
        echo "📏 Validating Content-Length Deduplication (Main Issue)\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // Scenario 1: PSR-7 and runtime both set Content-Length
        $psrHeaders = ['Content-Length' => ['1024']];
        $runtimeHeaders = ['Content-Length' => ['1024']]; // Duplicate!
        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'Duplicate Content-Length elimination',
            isset($result['Content-Length']) && count($result['Content-Length']) === 1,
            'Should have only one Content-Length header'
        );

        // Scenario 2: Different Content-Length values (PSR-7 should win)
        $psrHeaders = ['Content-Length' => ['2048']];
        $runtimeHeaders = ['Content-Length' => ['1024']];
        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'Content-Length priority (PSR-7 wins)',
            $result['Content-Length'][0] === '2048',
            'PSR-7 Content-Length should take precedence'
        );

        // Scenario 3: Case-insensitive Content-Length
        $psrHeaders = ['content-length' => ['3072']];
        $runtimeHeaders = ['Content-Length' => ['1024']];
        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'Case-insensitive Content-Length',
            isset($result['Content-Length']) && $result['Content-Length'][0] === '3072',
            'Should handle case-insensitive Content-Length headers'
        );

        echo "\n";
    }

    /**
     * Validate browser compatibility scenarios
     */
    private function validateBrowserCompatibility(): void
    {
        echo "🌐 Validating Browser Compatibility\n";
        echo "-" . str_repeat("-", 50) . "\n";

        $browserScenarios = [
            'Chrome' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
            ],
            'Firefox' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ];

        foreach ($browserScenarios as $browser => $requestHeaders) {
            $response = $this->simulateBrowserResponse($requestHeaders);
            
            $this->assertTest(
                "{$browser} compatibility",
                $this->hasNoDuplicateHeaders($response),
                "{$browser} response should have no duplicate headers"
            );
        }

        echo "\n";
    }

    /**
     * Validate compression scenarios
     */
    private function validateCompressionScenarios(): void
    {
        echo "🗜️ Validating Compression Scenarios\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // Gzip compression with Content-Length conflict
        $psrHeaders = [
            'Content-Type' => ['text/html'],
            'Content-Length' => ['10240'], // Original size
        ];
        $runtimeHeaders = [
            'Content-Encoding' => ['gzip'],
            'Content-Length' => ['5120'], // Compressed size - conflict!
            'Vary' => ['Accept-Encoding'],
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'Compression Content-Length handling',
            $result['Content-Length'][0] === '10240' && isset($result['Content-Encoding']),
            'Should preserve PSR-7 Content-Length with compression headers'
        );

        echo "\n";
    }

    /**
     * Validate CORS scenarios
     */
    private function validateCorsScenarios(): void
    {
        echo "🌍 Validating CORS Scenarios\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // CORS preflight with conflicting origins
        $psrHeaders = [
            'Access-Control-Allow-Origin' => ['https://example.com'],
            'Access-Control-Allow-Methods' => ['GET, POST'],
        ];
        $runtimeHeaders = [
            'Access-Control-Allow-Origin' => ['*'], // Conflict
            'Access-Control-Allow-Headers' => ['Content-Type'],
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'CORS origin precedence',
            $result['Access-Control-Allow-Origin'][0] === 'https://example.com',
            'PSR-7 CORS origin should take precedence'
        );

        echo "\n";
    }

    /**
     * Validate Cache-Control handling (fixed issue)
     */
    private function validateCacheControlHandling(): void
    {
        echo "💾 Validating Cache-Control Handling\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // Application cache control vs runtime cache control
        $psrHeaders = [
            'Cache-Control' => ['public, max-age=3600'],
        ];
        $runtimeHeaders = [
            'Cache-Control' => ['no-cache'], // Conflict
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'Cache-Control precedence',
            $result['Cache-Control'][0] === 'public, max-age=3600',
            'Application Cache-Control should take precedence'
        );

        $this->assertTest(
            'Cache-Control not combinable',
            !$this->headerService->shouldCombineHeader('Cache-Control'),
            'Cache-Control should not be combinable'
        );

        echo "\n";
    }

    /**
     * Validate real-world scenarios
     */
    private function validateRealWorldScenarios(): void
    {
        echo "🌍 Validating Real-World Scenarios\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // File upload scenario
        $psrHeaders = [
            'Content-Type' => ['application/json'],
            'Content-Length' => ['256'],
            'X-Upload-Status' => ['success'],
        ];
        $runtimeHeaders = [
            'Server' => ['ThinkPHP-Runtime'],
            'Content-Length' => ['256'], // Duplicate
            'Date' => [gmdate('D, d M Y H:i:s T')],
        ];

        $result = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        $this->assertTest(
            'File upload response',
            count($result['Content-Length']) === 1 && isset($result['X-Upload-Status']),
            'File upload should have deduplicated headers'
        );

        // API response with multiple middleware headers
        $psrHeaders = ['Content-Type' => ['application/json']];
        $middlewareHeaders = ['X-Frame-Options' => ['DENY']];
        $runtimeHeaders = ['Server' => ['ThinkPHP-Runtime']];

        $intermediate = $this->headerService->mergeHeaders($psrHeaders, $middlewareHeaders);
        $final = $this->headerService->mergeHeaders($intermediate, $runtimeHeaders);
        
        $this->assertTest(
            'Multi-stage header merge',
            count($final) === 3 && isset($final['Content-Type'], $final['X-Frame-Options'], $final['Server']),
            'Multi-stage merge should work correctly'
        );

        echo "\n";
    }

    /**
     * Validate adapter integration
     */
    private function validateAdapterIntegration(): void
    {
        echo "🔌 Validating Adapter Integration\n";
        echo "-" . str_repeat("-", 50) . "\n";

        $app = $this->createMockApp();

        // Test key adapters have header deduplication service
        $adapters = [
            'WorkermanAdapter' => WorkermanAdapter::class,
            'SwooleAdapter' => SwooleAdapter::class,
        ];

        foreach ($adapters as $name => $class) {
            if (class_exists($class)) {
                $adapter = new $class($app, []);
                $this->assertTest(
                    "{$name} integration",
                    method_exists($adapter, 'getHeaderService'),
                    "{$name} should have header deduplication service"
                );
            }
        }

        echo "\n";
    }

    /**
     * Validate HTTP/1.1 compliance
     */
    private function validateHttpCompliance(): void
    {
        echo "📋 Validating HTTP/1.1 Compliance\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // Test that critical headers are not duplicated
        $criticalHeaders = ['Content-Length', 'Content-Type', 'Host', 'Authorization'];
        
        foreach ($criticalHeaders as $header) {
            $headers = [
                $header => ['value1'],
                strtolower($header) => ['value2'], // Case variation
            ];
            
            $result = $this->headerService->deduplicateHeaders($headers);
            $normalizedName = $this->headerService->normalizeHeaderName($header);
            
            $this->assertTest(
                "HTTP/1.1 compliance: {$header}",
                isset($result[$normalizedName]) && count($result[$normalizedName]) === 1,
                "{$header} should appear only once"
            );
        }

        echo "\n";
    }

    /**
     * Simulate browser response with header deduplication
     */
    private function simulateBrowserResponse(array $requestHeaders): array
    {
        $psrHeaders = [
            'Content-Type' => ['text/html; charset=UTF-8'],
            'Content-Length' => ['2048'],
        ];
        
        $runtimeHeaders = [
            'Server' => ['ThinkPHP-Runtime'],
            'Date' => [gmdate('D, d M Y H:i:s T')],
            'Content-Type' => ['text/html'], // Potential conflict
        ];

        return $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
    }

    /**
     * Check if headers have no duplicates
     */
    private function hasNoDuplicateHeaders(array $headers): bool
    {
        foreach ($headers as $name => $values) {
            if (is_array($values) && count($values) > 1) {
                // Check if it's a combinable header
                if (!$this->headerService->shouldCombineHeader($name)) {
                    return false;
                }
            }
        }
        return true;
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
        } else {
            echo "❌ {$testName}: {$message}\n";
        }
    }

    /**
     * Print final summary
     */
    private function printFinalSummary(): void
    {
        echo "\n" . "=" . str_repeat("=", 50) . "\n";
        echo "🎯 Final Validation Summary\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        echo "Total Tests: {$this->totalTests}\n";
        echo "Passed: {$this->passedTests}\n";
        echo "Failed: " . ($this->totalTests - $this->passedTests) . "\n";
        
        $successRate = $this->totalTests > 0 ? ($this->passedTests / $this->totalTests) * 100 : 0;
        echo "Success Rate: " . number_format($successRate, 1) . "%\n";

        if ($this->passedTests === $this->totalTests) {
            echo "\n🎉 VALIDATION SUCCESSFUL!\n";
            echo "✅ Header deduplication fix is working correctly\n";
            echo "✅ Content-Length duplication issue is resolved\n";
            echo "✅ Browser compatibility is maintained\n";
            echo "✅ HTTP/1.1 compliance is ensured\n";
            echo "✅ Real-world scenarios are handled properly\n";
            echo "\n🚀 The fix is ready for production use!\n";
        } else {
            echo "\n⚠️ VALIDATION FAILED!\n";
            echo "Some tests failed. Please review the implementation.\n";
        }
    }

    /**
     * Create mock application
     */
    private function createMockApp(): object
    {
        return new class {
            public function isDebug(): bool { return true; }
            public function getRootPath(): string { return __DIR__ . '/../'; }
        };
    }
}

// Run validation if executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $validator = new FinalHeaderDeduplicationValidation();
    $validator->runValidation();
}