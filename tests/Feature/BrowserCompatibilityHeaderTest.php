<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\Tests\TestCase;
use yangweijie\thinkRuntime\service\HeaderDeduplicationService;

/**
 * Browser Compatibility Header Tests
 * 
 * Tests that simulate real browser requests and validate
 * that responses have properly deduplicated headers
 */
class BrowserCompatibilityHeaderTest extends TestCase
{
    private HeaderDeduplicationService $headerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
        $this->headerService = new HeaderDeduplicationService();
    }

    /**
     * Test Chrome browser request scenarios
     */
    public function test_chrome_browser_request_scenarios(): void
    {
        $chromeHeaders = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Cache-Control' => 'max-age=0',
        ];

        $response = $this->simulateBrowserRequest($chromeHeaders);
        
        $this->assertNoDuplicateHeaders($response, 'Chrome browser request should not have duplicate headers');
        $this->assertValidHttpHeaders($response, 'Chrome browser response should have valid HTTP headers');
        $this->assertBrowserCompatibleHeaders($response, 'Chrome browser response should be browser compatible');
    }

    /**
     * Test Firefox browser request scenarios
     */
    public function test_firefox_browser_request_scenarios(): void
    {
        $firefoxHeaders = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'DNT' => '1',
        ];

        $response = $this->simulateBrowserRequest($firefoxHeaders);
        
        $this->assertNoDuplicateHeaders($response, 'Firefox browser request should not have duplicate headers');
        $this->assertValidHttpHeaders($response, 'Firefox browser response should have valid HTTP headers');
        $this->assertBrowserCompatibleHeaders($response, 'Firefox browser response should be browser compatible');
    }

    /**
     * Test Safari browser request scenarios
     */
    public function test_safari_browser_request_scenarios(): void
    {
        $safariHeaders = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-us',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
        ];

        $response = $this->simulateBrowserRequest($safariHeaders);
        
        $this->assertNoDuplicateHeaders($response, 'Safari browser request should not have duplicate headers');
        $this->assertValidHttpHeaders($response, 'Safari browser response should have valid HTTP headers');
        $this->assertBrowserCompatibleHeaders($response, 'Safari browser response should be browser compatible');
    }

    /**
     * Test Edge browser request scenarios
     */
    public function test_edge_browser_request_scenarios(): void
    {
        $edgeHeaders = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
        ];

        $response = $this->simulateBrowserRequest($edgeHeaders);
        
        $this->assertNoDuplicateHeaders($response, 'Edge browser request should not have duplicate headers');
        $this->assertValidHttpHeaders($response, 'Edge browser response should have valid HTTP headers');
        $this->assertBrowserCompatibleHeaders($response, 'Edge browser response should be browser compatible');
    }

    /**
     * Test mobile browser scenarios
     */
    public function test_mobile_browser_scenarios(): void
    {
        // Mobile Chrome
        $mobileChromeHeaders = [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; SM-G973F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.120 Mobile Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];

        $response = $this->simulateBrowserRequest($mobileChromeHeaders);
        $this->assertNoDuplicateHeaders($response, 'Mobile Chrome should not have duplicate headers');

        // Mobile Safari
        $mobileSafariHeaders = [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-us',
            'Accept-Encoding' => 'gzip, deflate',
        ];

        $response = $this->simulateBrowserRequest($mobileSafariHeaders);
        $this->assertNoDuplicateHeaders($response, 'Mobile Safari should not have duplicate headers');
    }

    /**
     * Test browser requests with compression
     */
    public function test_browser_compression_scenarios(): void
    {
        $compressionScenarios = [
            'gzip_only' => ['Accept-Encoding' => 'gzip'],
            'deflate_only' => ['Accept-Encoding' => 'deflate'],
            'brotli_only' => ['Accept-Encoding' => 'br'],
            'multiple_encodings' => ['Accept-Encoding' => 'gzip, deflate, br'],
            'quality_values' => ['Accept-Encoding' => 'gzip;q=1.0, deflate;q=0.8, br;q=0.6'],
        ];

        foreach ($compressionScenarios as $scenario => $headers) {
            $response = $this->simulateBrowserRequest($headers, 'large_content');
            
            $this->assertNoDuplicateHeaders($response, "Compression scenario '{$scenario}' should not have duplicate headers");
            
            // Check that Content-Length appears only once
            $contentLengthCount = $this->countHeaderOccurrences($response, 'Content-Length');
            $this->assertLessThanOrEqual(1, $contentLengthCount, "Content-Length should appear at most once in '{$scenario}'");
            
            // If compression is applied, Content-Encoding should appear only once
            if ($this->hasHeader($response, 'Content-Encoding')) {
                $contentEncodingCount = $this->countHeaderOccurrences($response, 'Content-Encoding');
                $this->assertEquals(1, $contentEncodingCount, "Content-Encoding should appear exactly once in '{$scenario}'");
            }
        }
    }

    /**
     * Test browser CORS preflight requests
     */
    public function test_browser_cors_preflight_scenarios(): void
    {
        $corsScenarios = [
            'simple_cors' => [
                'Origin' => 'https://example.com',
                'Access-Control-Request-Method' => 'POST',
            ],
            'complex_cors' => [
                'Origin' => 'https://app.example.com',
                'Access-Control-Request-Method' => 'PUT',
                'Access-Control-Request-Headers' => 'Content-Type, Authorization, X-Custom-Header',
            ],
            'credentials_cors' => [
                'Origin' => 'https://secure.example.com',
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'Authorization',
            ],
        ];

        foreach ($corsScenarios as $scenario => $headers) {
            $response = $this->simulateBrowserRequest($headers, 'cors_preflight');
            
            $this->assertNoDuplicateHeaders($response, "CORS scenario '{$scenario}' should not have duplicate headers");
            
            // Check that CORS headers appear only once
            $corsHeaders = ['Access-Control-Allow-Origin', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Headers'];
            foreach ($corsHeaders as $corsHeader) {
                if ($this->hasHeader($response, $corsHeader)) {
                    $count = $this->countHeaderOccurrences($response, $corsHeader);
                    $this->assertEquals(1, $count, "{$corsHeader} should appear exactly once in '{$scenario}'");
                }
            }
        }
    }

    /**
     * Test browser caching scenarios
     */
    public function test_browser_caching_scenarios(): void
    {
        $cachingScenarios = [
            'no_cache' => [
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
            ],
            'max_age' => [
                'Cache-Control' => 'max-age=0',
            ],
            'conditional_request' => [
                'If-Modified-Since' => 'Wed, 21 Oct 2015 07:28:00 GMT',
                'If-None-Match' => '"abc123"',
            ],
        ];

        foreach ($cachingScenarios as $scenario => $headers) {
            $response = $this->simulateBrowserRequest($headers, 'cached_content');
            
            $this->assertNoDuplicateHeaders($response, "Caching scenario '{$scenario}' should not have duplicate headers");
            
            // Check that caching headers appear only once
            $cachingHeaders = ['Cache-Control', 'ETag', 'Last-Modified', 'Expires'];
            foreach ($cachingHeaders as $cachingHeader) {
                if ($this->hasHeader($response, $cachingHeader)) {
                    $count = $this->countHeaderOccurrences($response, $cachingHeader);
                    $this->assertEquals(1, $count, "{$cachingHeader} should appear exactly once in '{$scenario}'");
                }
            }
        }
    }

    /**
     * Simulate a browser request and return response headers
     */
    private function simulateBrowserRequest(array $requestHeaders, string $contentType = 'html'): array
    {
        // Simulate PSR-7 response headers based on content type
        $psrHeaders = $this->generatePsrHeaders($contentType);
        
        // Simulate runtime headers that might be added by the server
        $runtimeHeaders = $this->generateRuntimeHeaders($requestHeaders, $contentType);
        
        // Use header deduplication service to merge headers
        $finalHeaders = $this->headerService->mergeHeaders($psrHeaders, $runtimeHeaders);
        
        return $finalHeaders;
    }

    /**
     * Generate PSR-7 response headers based on content type
     */
    private function generatePsrHeaders(string $contentType): array
    {
        $headers = [];
        
        switch ($contentType) {
            case 'html':
                $headers['Content-Type'] = ['text/html; charset=UTF-8'];
                $headers['Content-Length'] = ['1024'];
                break;
                
            case 'large_content':
                $headers['Content-Type'] = ['text/html; charset=UTF-8'];
                $headers['Content-Length'] = ['10240'];
                break;
                
            case 'cors_preflight':
                $headers['Access-Control-Allow-Origin'] = ['https://example.com'];
                $headers['Access-Control-Allow-Methods'] = ['GET, POST, PUT, DELETE, OPTIONS'];
                $headers['Access-Control-Allow-Headers'] = ['Content-Type, Authorization'];
                $headers['Content-Length'] = ['0'];
                break;
                
            case 'cached_content':
                $headers['Content-Type'] = ['text/html; charset=UTF-8'];
                $headers['Content-Length'] = ['2048'];
                $headers['Cache-Control'] = ['public, max-age=3600'];
                $headers['ETag'] = ['"abc123"'];
                $headers['Last-Modified'] = ['Wed, 21 Oct 2015 07:28:00 GMT'];
                break;
        }
        
        return $headers;
    }

    /**
     * Generate runtime headers that might be added by the server
     */
    private function generateRuntimeHeaders(array $requestHeaders, string $contentType): array
    {
        $headers = [
            'Server' => ['ThinkPHP-Runtime/1.0'],
            'Date' => [gmdate('D, d M Y H:i:s T')],
            'X-Powered-By' => ['ThinkPHP'],
        ];
        
        // Add compression headers if client accepts encoding
        if (isset($requestHeaders['Accept-Encoding'])) {
            $acceptEncoding = $requestHeaders['Accept-Encoding'];
            if (strpos($acceptEncoding, 'gzip') !== false && $contentType === 'large_content') {
                $headers['Content-Encoding'] = ['gzip'];
                $headers['Content-Length'] = ['5120']; // Compressed size - potential duplicate!
                $headers['Vary'] = ['Accept-Encoding'];
            }
        }
        
        // Add CORS headers if Origin is present
        if (isset($requestHeaders['Origin'])) {
            $headers['Access-Control-Allow-Origin'] = ['*']; // Potential conflict with PSR-7
            $headers['Access-Control-Allow-Credentials'] = ['true'];
        }
        
        // Add security headers
        $headers['X-Frame-Options'] = ['DENY'];
        $headers['X-Content-Type-Options'] = ['nosniff'];
        $headers['X-XSS-Protection'] = ['1; mode=block'];
        
        // Add caching headers that might conflict
        if ($contentType === 'cached_content') {
            $headers['Cache-Control'] = ['no-cache']; // Potential conflict with PSR-7
            $headers['Pragma'] = ['no-cache'];
        }
        
        return $headers;
    }

    /**
     * Assert that there are no duplicate headers
     */
    private function assertNoDuplicateHeaders(array $headers, string $message = ''): void
    {
        $duplicates = [];
        
        foreach ($headers as $name => $values) {
            if (is_array($values) && count($values) > 1) {
                // Check if this is a combinable header
                if (!$this->headerService->shouldCombineHeader($name)) {
                    $duplicates[] = $name;
                }
            }
        }
        
        $this->assertEmpty($duplicates, ($message ?: 'No duplicate headers expected') . '. Found duplicates: ' . implode(', ', $duplicates));
    }

    /**
     * Assert that headers are valid HTTP headers
     */
    private function assertValidHttpHeaders(array $headers, string $message = ''): void
    {
        foreach ($headers as $name => $values) {
            // Check header name format
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9\-_]+$/', $name, 
                ($message ?: 'Invalid header name') . ": {$name}");
            
            // Check header values
            foreach ((array)$values as $value) {
                $this->assertIsString($value, ($message ?: 'Header value should be string') . ": {$name}");
                $this->assertNotEmpty($value, ($message ?: 'Header value should not be empty') . ": {$name}");
            }
        }
    }

    /**
     * Assert that headers are browser compatible
     */
    private function assertBrowserCompatibleHeaders(array $headers, string $message = ''): void
    {
        // Check for required headers
        $this->assertArrayHasKey('Content-Type', $headers, $message ?: 'Content-Type header is required for browser compatibility');
        
        // Check Content-Length if present
        if (isset($headers['Content-Length'])) {
            $contentLength = $headers['Content-Length'][0];
            $this->assertMatchesRegularExpression('/^\d+$/', $contentLength, 
                ($message ?: 'Content-Length should be numeric') . ": {$contentLength}");
        }
        
        // Check Date header format if present
        if (isset($headers['Date'])) {
            $date = $headers['Date'][0];
            $this->assertNotFalse(strtotime($date), 
                ($message ?: 'Date header should be valid') . ": {$date}");
        }
    }

    /**
     * Count occurrences of a header
     */
    private function countHeaderOccurrences(array $headers, string $headerName): int
    {
        $normalizedName = $this->headerService->normalizeHeaderName($headerName);
        return isset($headers[$normalizedName]) ? count((array)$headers[$normalizedName]) : 0;
    }

    /**
     * Check if a header exists
     */
    private function hasHeader(array $headers, string $headerName): bool
    {
        $normalizedName = $this->headerService->normalizeHeaderName($headerName);
        return isset($headers[$normalizedName]);
    }
}