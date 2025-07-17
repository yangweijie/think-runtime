# Design Document

## Overview

The HTTP header duplication issue occurs when both the PSR-7 response object and the runtime-specific response handling code set the same headers, particularly Content-Length. This violates HTTP/1.1 specifications and causes browser warnings and potential compatibility issues.

The problem manifests in several ways:
1. **PSR-7 Response Headers**: The application sets headers through PSR-7 response objects
2. **Runtime Auto-Headers**: Runtime adapters automatically calculate and set headers like Content-Length
3. **Middleware Headers**: CORS, security, and compression middleware add headers
4. **Case Sensitivity**: Different header name cases (Content-Length vs content-length) are treated as separate headers

## Architecture

### Core Components

#### 1. Header Deduplication Service
A centralized service responsible for:
- Merging headers from multiple sources
- Handling case-insensitive header names
- Applying HTTP/1.1 header combination rules
- Providing consistent header resolution across all adapters

#### 2. Enhanced Abstract Runtime
Extend `AbstractRuntime` with header management capabilities:
- Common header deduplication logic
- Standardized header merging methods
- Debug logging for header conflicts
- Performance-optimized header processing

#### 3. Adapter Integration Points
Modify each adapter's response conversion methods:
- `WorkermanAdapter::convertPsrResponseToWorkerman()`
- `SwooleAdapter::sendSwooleResponse()`
- `ReactPHPAdapter::handleReactRequest()`
- All other adapter response handling methods

## Components and Interfaces

### HeaderDeduplicationService

```php
interface HeaderDeduplicationInterface
{
    public function deduplicateHeaders(array $headers): array;
    public function mergeHeaders(array $primary, array $secondary): array;
    public function normalizeHeaderName(string $name): string;
    public function shouldCombineHeader(string $name): bool;
    public function combineHeaderValues(string $name, array $values): string;
}

class HeaderDeduplicationService implements HeaderDeduplicationInterface
{
    // Core deduplication logic
    // HTTP/1.1 compliant header merging
    // Case-insensitive header handling
    // Debug logging capabilities
}
```

### Enhanced AbstractRuntime

```php
abstract class AbstractRuntime
{
    protected HeaderDeduplicationService $headerService;
    
    protected function processResponseHeaders(
        ResponseInterface $psrResponse,
        array $runtimeHeaders = []
    ): array {
        // Merge PSR-7 headers with runtime-specific headers
        // Apply deduplication rules
        // Log conflicts if debug enabled
    }
    
    protected function shouldSkipRuntimeHeader(string $name, array $psrHeaders): bool {
        // Determine if runtime should skip setting a header
        // that's already present in PSR-7 response
    }
}
```

### Adapter Response Methods

Each adapter will use the common header processing:

```php
// WorkermanAdapter
protected function convertPsrResponseToWorkerman(ResponseInterface $psrResponse): Response
{
    $statusCode = $psrResponse->getStatusCode();
    $runtimeHeaders = $this->buildRuntimeHeaders($request);
    $finalHeaders = $this->processResponseHeaders($psrResponse, $runtimeHeaders);
    
    return new Response($statusCode, $finalHeaders, $body);
}

// SwooleAdapter  
protected function sendSwooleResponse(SwooleResponse $swooleResponse, ResponseInterface $psr7Response): void
{
    $swooleResponse->status($psr7Response->getStatusCode());
    $finalHeaders = $this->processResponseHeaders($psr7Response);
    
    foreach ($finalHeaders as $name => $value) {
        $swooleResponse->header($name, $value);
    }
}
```

## Data Models

### Header Conflict Resolution Rules

1. **Content-Length Priority**: PSR-7 response takes precedence over runtime calculation
2. **Content-Type Priority**: Application-set content type overrides runtime defaults
3. **Security Headers**: Runtime security headers are additive, not overriding
4. **CORS Headers**: Middleware CORS headers take precedence over application headers
5. **Compression Headers**: Runtime compression headers override application headers

### Header Normalization Map

```php
private const HEADER_NORMALIZATION = [
    'content-length' => 'Content-Length',
    'content-type' => 'Content-Type',
    'content-encoding' => 'Content-Encoding',
    'cache-control' => 'Cache-Control',
    'set-cookie' => 'Set-Cookie',
    // ... other common headers
];
```

### Combinable Headers List

```php
private const COMBINABLE_HEADERS = [
    'Accept',
    'Accept-Charset',
    'Accept-Encoding',
    'Accept-Language',
    'Cache-Control',
    'Connection',
    'Cookie',
    'Pragma',
    'Upgrade',
    'Via',
    'Warning',
];
```

## Error Handling

### Header Conflict Detection
- Log when duplicate headers are found
- Warn about potential HTTP compliance issues
- Provide clear resolution information

### Exception Handling
- Graceful fallback when header processing fails
- Preserve original headers if deduplication fails
- Log errors without breaking request processing

### Debug Mode
- Detailed logging of header merging process
- Before/after header comparison
- Performance impact measurement

## Testing Strategy

### Unit Tests
1. **HeaderDeduplicationService Tests**
   - Test case-insensitive header merging
   - Verify HTTP/1.1 compliance rules
   - Test header value combination logic

2. **AbstractRuntime Tests**
   - Test header processing integration
   - Verify conflict resolution rules
   - Test debug logging functionality

### Integration Tests
1. **Adapter-Specific Tests**
   - Test each adapter's response conversion
   - Verify no duplicate headers in final output
   - Test with various header combinations

2. **End-to-End Tests**
   - Test complete request/response cycle
   - Verify browser compatibility
   - Test with real HTTP clients

### Performance Tests
1. **Header Processing Overhead**
   - Measure deduplication performance impact
   - Compare before/after response times
   - Memory usage analysis

2. **Stress Testing**
   - High-concurrency header processing
   - Large header sets handling
   - Memory leak detection

## Implementation Phases

### Phase 1: Core Service
- Implement HeaderDeduplicationService
- Add to AbstractRuntime base class
- Create comprehensive unit tests

### Phase 2: Adapter Integration
- Update WorkermanAdapter (highest priority - reported issue)
- Update SwooleAdapter
- Update ReactPHPAdapter
- Update remaining adapters

### Phase 3: Advanced Features
- Add debug logging capabilities
- Implement performance monitoring
- Add configuration options

### Phase 4: Validation
- Comprehensive integration testing
- Performance benchmarking
- Browser compatibility verification

## Configuration Options

```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => false,
    'strict_mode' => true, // Throw exceptions on conflicts
    'preserve_case' => false, // Preserve original header case
    'custom_rules' => [], // Custom header resolution rules
]
```

This design ensures systematic resolution of header duplication issues while maintaining backward compatibility and providing clear debugging capabilities.