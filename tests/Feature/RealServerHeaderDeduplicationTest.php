<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\Tests\TestCase;

/**
 * Real Server Header Deduplication Tests
 * 
 * Tests that start actual HTTP servers and make real HTTP requests
 * to validate header deduplication in production-like scenarios.
 */
class RealServerHeaderDeduplicationTest extends TestCase
{
    private array $runningServers = [];
    private int $basePort = 9700;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    protected function tearDown(): void
    {
        // Stop all running servers
        foreach ($this->runningServers as $server) {
            $this->stopServer($server);
        }
        $this->runningServers = [];
        parent::tearDown();
    }

    /**
     * Test real HTTP server with actual cURL requests
     */
    public function test_real_http_server_eliminates_duplicate_headers(): void
    {
        if (!$this->canRunRealServerTests()) {
            $this->markTestSkipped('Real server tests require network capabilities and available ports');
            return;
        }

        $port = $this->getAvailablePort();
        $server = $this->startTestServer($port);
        
        if (!$server) {
            $this->markTestSkipped('Could not start test server');
            return;
        }

        // Wait for server to start
        sleep(2);

        try {
            // Test various scenarios with real HTTP requests
            $this->runRealHttpTests($port);
        } finally {
            $this->stopServer($server);
        }
    }

    /**
     * Test browser-like requests with real HTTP client
     */
    public function test_browser_like_requests_with_real_http(): void
    {
        if (!$this->canRunRealServerTests()) {
            $this->markTestSkipped('Real server tests require network capabilities');
            return;
        }

        $port = $this->getAvailablePort();
        $server = $this->startTestServer($port);
        
        if (!$server) {
            $this->markTestSkipped('Could not start test server');
            return;
        }

        sleep(2);

        try {
            $this->runBrowserLikeTests($port);
        } finally {
            $this->stopServer($server);
        }
    }

    /**
     * Test compression scenarios with real HTTP
     */
    public function test_compression_scenarios_with_real_http(): void
    {
        if (!$this->canRunRealServerTests()) {
            $this->markTestSkipped('Real server tests require network capabilities');
            return;
        }

        $port = $this->getAvailablePort();
        $server = $this->startTestServer($port);
        
        if (!$server) {
            $this->markTestSkipped('Could not start test server');
            return;
        }

        sleep(2);

        try {
            $this->runCompressionTests($port);
        } finally {
            $this->stopServer($server);
        }
    }

    /**
     * Run real HTTP tests against the server
     */
    private function runRealHttpTests(int $port): void
    {
        $baseUrl = "http://127.0.0.1:{$port}";

        // Test 1: Simple GET request
        $response = $this->makeRealHttpRequest($baseUrl . '/', 'GET');
        $this->assertNoDuplicateHeadersInRealResponse($response, 'Simple GET request failed');
        $this->assertValidRealHttpResponse($response, 'Invalid response for simple GET');

        // Test 2: POST with JSON data
        $jsonData = json_encode(['test' => 'data', 'timestamp' => time()]);
        $response = $this->makeRealHttpRequest($baseUrl . '/api/test', 'POST', [
            'Content-Type: application/json',
            'Accept: application/json',
        ], $jsonData);
        
        $this->assertNoDuplicateHeadersInRealResponse($response, 'POST with JSON failed');
        $this->assertValidRealHttpResponse($response, 'Invalid response for POST with JSON');

        // Test 3: Request with custom headers
        $response = $this->makeRealHttpRequest($baseUrl . '/custom', 'GET', [
            'X-Custom-Header: test-value',
            'X-Request-ID: ' . uniqid(),
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ]);
        
        $this->assertNoDuplicateHeadersInRealResponse($response, 'Custom headers request failed');
        $this->assertValidRealHttpResponse($response, 'Invalid response for custom headers');
    }

    /**
     * Run browser-like tests
     */
    private function runBrowserLikeTests(int $port): void
    {
        $baseUrl = "http://127.0.0.1:{$port}";

        $browserHeaders = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Cache-Control: max-age=0',
        ];

        $response = $this->makeRealHttpRequest($baseUrl . '/', 'GET', $browserHeaders);
        $this->assertNoDuplicateHeadersInRealResponse($response, 'Browser-like request failed');
        $this->assertValidRealHttpResponse($response, 'Invalid response for browser-like request');

        // Check that common headers are not duplicated
        $this->assertSingleHeaderOccurrence($response, 'Content-Type', 'Content-Type should appear only once');
        $this->assertSingleHeaderOccurrence($response, 'Content-Length', 'Content-Length should appear only once');
        $this->assertSingleHeaderOccurrence($response, 'Server', 'Server should appear only once');
    }

    /**
     * Run compression tests
     */
    private function runCompressionTests(int $port): void
    {
        $baseUrl = "http://127.0.0.1:{$port}";

        // Request with gzip encoding
        $response = $this->makeRealHttpRequest($baseUrl . '/large-content', 'GET', [
            'Accept-Encoding: gzip, deflate',
            'Accept: text/html',
        ]);

        $this->assertNoDuplicateHeadersInRealResponse($response, 'Gzip compression request failed');
        $this->assertValidRealHttpResponse($response, 'Invalid response for gzip compression');

        // Specifically check Content-Length and Content-Encoding
        $this->assertSingleHeaderOccurrence($response, 'Content-Length', 'Content-Length should appear only once with compression');
        
        // If compression is enabled, Content-Encoding should appear once
        if ($this->responseHasHeader($response, 'Content-Encoding')) {
            $this->assertSingleHeaderOccurrence($response, 'Content-Encoding', 'Content-Encoding should appear only once');
        }
    }

    /**
     * Make a real HTTP request using cURL
     */
    private function makeRealHttpRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception("cURL request failed: {$error}");
        }

        return [
            'response' => $response,
            'http_code' => $httpCode,
            'error' => $error
        ];
    }

    /**
     * Start a test HTTP server
     */
    private function startTestServer(int $port): ?array
    {
        // Create a simple PHP server script
        $serverScript = $this->createTestServerScript();
        $scriptPath = sys_get_temp_dir() . '/test_server_' . $port . '.php';
        file_put_contents($scriptPath, $serverScript);

        // Start PHP built-in server
        $command = sprintf(
            'php -S 127.0.0.1:%d %s > /dev/null 2>&1 &',
            $port,
            escapeshellarg($scriptPath)
        );

        if (PHP_OS_FAMILY === 'Windows') {
            $command = sprintf(
                'start /B php -S 127.0.0.1:%d %s > NUL 2>&1',
                $port,
                escapeshellarg($scriptPath)
            );
        }

        exec($command, $output, $returnCode);

        $server = [
            'port' => $port,
            'script_path' => $scriptPath,
            'pid' => null,
        ];

        $this->runningServers[] = $server;
        return $server;
    }

    /**
     * Stop a test server
     */
    private function stopServer(array $server): void
    {
        if (isset($server['script_path']) && file_exists($server['script_path'])) {
            unlink($server['script_path']);
        }

        // Kill processes using the port
        if (PHP_OS_FAMILY === 'Windows') {
            exec("netstat -ano | findstr :{$server['port']}", $output);
            foreach ($output as $line) {
                if (preg_match('/\s+(\d+)$/', $line, $matches)) {
                    exec("taskkill /F /PID {$matches[1]} > NUL 2>&1");
                }
            }
        } else {
            exec("lsof -ti:{$server['port']} | xargs kill -9 > /dev/null 2>&1");
        }
    }

    /**
     * Create test server script that uses header deduplication
     */
    private function createTestServerScript(): string
    {
        return '<?php
// Test server script that simulates ThinkPHP Runtime behavior
require_once __DIR__ . "/../../vendor/autoload.php";

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;

$headerService = new HeaderDeduplicationService();

// Simulate PSR-7 response headers
$psrHeaders = [
    "Content-Type" => ["text/html; charset=UTF-8"],
    "X-Powered-By" => ["ThinkPHP"],
];

// Simulate runtime headers that might conflict
$runtimeHeaders = [
    "Server" => ["PHP/" . PHP_VERSION],
    "Date" => [gmdate("D, d M Y H:i:s T")],
    "Content-Type" => ["text/html"], // Potential duplicate
];

// Handle different routes
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$method = $_SERVER["REQUEST_METHOD"];

$body = "";
$statusCode = 200;

switch ($path) {
    case "/":
        $body = "<html><body><h1>Test Server</h1><p>Header deduplication test</p></body></html>";
        break;
        
    case "/api/test":
        $psrHeaders["Content-Type"] = ["application/json"];
        $body = json_encode(["status" => "success", "method" => $method]);
        break;
        
    case "/custom":
        $psrHeaders["X-Custom-Response"] = ["test-response"];
        $body = "Custom response with headers";
        break;
        
    case "/large-content":
        $body = str_repeat("This is large content for compression testing. ", 1000);
        // Simulate compression
        if (isset($_SERVER["HTTP_ACCEPT_ENCODING"]) && strpos($_SERVER["HTTP_ACCEPT_ENCODING"], "gzip") !== false) {
            $runtimeHeaders["Content-Encoding"] = ["gzip"];
            $body = gzcompress($body);
        }
        break;
        
    default:
        $statusCode = 404;
        $body = "Not Found";
        break;
}

// Add Content-Length (potential duplicate)
$psrHeaders["Content-Length"] = [strlen($body)];
$runtimeHeaders["Content-Length"] = [strlen($body)]; // Duplicate!

// Use header deduplication service
$finalHeaders = $headerService->mergeHeaders($psrHeaders, $runtimeHeaders);

// Send response
http_response_code($statusCode);
foreach ($finalHeaders as $name => $values) {
    foreach ((array)$values as $value) {
        header("{$name}: {$value}");
    }
}

echo $body;
';
    }

    /**
     * Check if we can run real server tests
     */
    private function canRunRealServerTests(): bool
    {
        // Check if we have network capabilities and required functions
        return function_exists('curl_init') && 
               function_exists('exec') && 
               !empty($this->getAvailablePort());
    }

    /**
     * Get an available port for testing
     */
    private function getAvailablePort(): int
    {
        for ($port = $this->basePort; $port < $this->basePort + 100; $port++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if (!$socket) {
                return $port; // Port is available
            }
            fclose($socket);
        }
        return 0; // No available port found
    }

    /**
     * Assert no duplicate headers in real HTTP response
     */
    private function assertNoDuplicateHeadersInRealResponse(array $response, string $message = ''): void
    {
        $headers = $this->parseRealHttpHeaders($response['response']);
        $headerCounts = [];
        
        foreach ($headers as $headerLine) {
            if (empty($headerLine) || strpos($headerLine, ':') === false) {
                continue;
            }
            
            [$name] = explode(':', $headerLine, 2);
            $normalizedName = strtolower(trim($name));
            
            if (!isset($headerCounts[$normalizedName])) {
                $headerCounts[$normalizedName] = 0;
            }
            $headerCounts[$normalizedName]++;
        }
        
        $duplicates = array_filter($headerCounts, fn($count) => $count > 1);
        
        $this->assertEmpty($duplicates, 
            ($message ?: 'Duplicate headers found in real response') . ': ' . implode(', ', array_keys($duplicates)));
    }

    /**
     * Assert valid real HTTP response
     */
    private function assertValidRealHttpResponse(array $response, string $message = ''): void
    {
        $this->assertIsArray($response, $message ?: 'Response should be an array');
        $this->assertArrayHasKey('response', $response, $message ?: 'Response should have response key');
        $this->assertArrayHasKey('http_code', $response, $message ?: 'Response should have http_code key');
        
        $this->assertGreaterThanOrEqual(200, $response['http_code'], $message ?: 'HTTP code should be valid');
        $this->assertLessThan(600, $response['http_code'], $message ?: 'HTTP code should be valid');
        
        $this->assertStringStartsWith('HTTP/', $response['response'], $message ?: 'Response should start with HTTP/');
    }

    /**
     * Assert single header occurrence
     */
    private function assertSingleHeaderOccurrence(array $response, string $headerName, string $message = ''): void
    {
        $headers = $this->parseRealHttpHeaders($response['response']);
        $count = 0;
        $normalizedName = strtolower($headerName);
        
        foreach ($headers as $headerLine) {
            if (empty($headerLine) || strpos($headerLine, ':') === false) {
                continue;
            }
            
            [$name] = explode(':', $headerLine, 2);
            if (strtolower(trim($name)) === $normalizedName) {
                $count++;
            }
        }
        
        $this->assertLessThanOrEqual(1, $count, $message ?: "Header '{$headerName}' should appear at most once");
    }

    /**
     * Check if response has a specific header
     */
    private function responseHasHeader(array $response, string $headerName): bool
    {
        $headers = $this->parseRealHttpHeaders($response['response']);
        $normalizedName = strtolower($headerName);
        
        foreach ($headers as $headerLine) {
            if (empty($headerLine) || strpos($headerLine, ':') === false) {
                continue;
            }
            
            [$name] = explode(':', $headerLine, 2);
            if (strtolower(trim($name)) === $normalizedName) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Parse headers from real HTTP response
     */
    private function parseRealHttpHeaders(string $response): array
    {
        $lines = explode("\r\n", $response);
        $headers = [];
        
        foreach ($lines as $line) {
            if (empty($line)) {
                break; // End of headers
            }
            if (strpos($line, 'HTTP/') === 0) {
                continue; // Skip status line
            }
            $headers[] = $line;
        }
        
        return $headers;
    }
}