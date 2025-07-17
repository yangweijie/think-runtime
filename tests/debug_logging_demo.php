<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use Psr\Log\AbstractLogger;

/**
 * Simple logger for demonstration
 */
class DemoLogger extends AbstractLogger
{
    public function log($level, $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        echo "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
    }
}

echo "=== Header Deduplication Debug Logging Demo ===\n\n";

// Create service with debug logging enabled
$logger = new DemoLogger();
$config = [
    'debug_logging' => true,
    'strict_mode' => false,
    'log_critical_conflicts' => true,
    'throw_on_merge_failure' => false,
    'enable_performance_logging' => true,
];

$service = new HeaderDeduplicationService($logger, $config);

echo "1. Testing header deduplication with conflicts:\n";
echo "----------------------------------------------\n";

$headers = [
    'Content-Type' => 'application/json',
    'content-type' => 'text/html', // Critical conflict
    'Accept' => 'text/html',
    'accept' => 'application/json', // Combinable conflict
    'Content-Length' => '100',
    'content-length' => '200', // Critical conflict
    'Cache-Control' => 'no-cache',
    'cache-control' => 'no-store', // Combinable
];

$result = $service->deduplicateHeaders($headers);

echo "\nResult:\n";
foreach ($result as $name => $value) {
    echo "  {$name}: {$value}\n";
}

echo "\n2. Testing header merging with conflicts:\n";
echo "----------------------------------------\n";

$primary = [
    'Content-Type' => 'application/json',
    'Accept' => 'text/html',
    'Authorization' => 'Bearer token123',
];

$secondary = [
    'content-type' => 'text/html', // Critical conflict
    'accept' => 'application/json', // Combinable
    'Server' => 'nginx/1.18',
    'authorization' => 'Bearer token456', // Critical conflict
];

$merged = $service->mergeHeaders($primary, $secondary);

echo "\nMerged Result:\n";
foreach ($merged as $name => $value) {
    echo "  {$name}: {$value}\n";
}

echo "\n3. Testing header statistics:\n";
echo "----------------------------\n";

$stats = $service->getHeaderStats($headers);
echo "Header Statistics:\n";
foreach ($stats as $key => $value) {
    if (is_array($value)) {
        echo "  {$key}: " . implode(', ', $value) . "\n";
    } else {
        echo "  {$key}: {$value}\n";
    }
}

echo "\n4. Testing error handling (validation errors):\n";
echo "----------------------------------------------\n";

try {
    // Test with invalid header name
    $invalidHeaders = [
        'invalid@header' => 'value',
        'Valid-Header' => 'valid-value',
    ];
    
    $service->deduplicateHeaders($invalidHeaders);
} catch (Exception $e) {
    echo "Caught expected exception: " . $e->getMessage() . "\n";
}

echo "\n5. Testing strict mode:\n";
echo "----------------------\n";

$strictService = new HeaderDeduplicationService($logger, [
    'debug_logging' => true,
    'strict_mode' => true,
    'log_critical_conflicts' => true,
]);

try {
    $criticalConflicts = [
        'Content-Length' => '100',
        'content-length' => '200',
    ];
    
    $strictService->deduplicateHeaders($criticalConflicts);
} catch (Exception $e) {
    echo "Caught expected strict mode exception: " . $e->getMessage() . "\n";
}

echo "\n=== Demo Complete ===\n";