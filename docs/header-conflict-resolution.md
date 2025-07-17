# Header Conflict Resolution Rules and Priority System

## Overview

The ThinkPHP Runtime header deduplication system uses a sophisticated priority system to resolve conflicts when multiple sources attempt to set the same HTTP header. This document explains the resolution rules and priority system in detail.

## Header Sources and Priority Order

Headers can come from multiple sources, listed here in priority order (highest to lowest):

1. **PSR-7 Response Headers** (Application-set headers)
2. **Runtime-Specific Headers** (Server-generated headers)
3. **Middleware Headers** (CORS, Security, etc.)
4. **Default Headers** (Framework defaults)

## Header Categories

### Critical Headers
These headers are essential for HTTP compliance and are handled with special care:

- `Content-Length`
- `Content-Type`
- `Authorization`
- `Host`
- `Location`
- `Set-Cookie`

**Resolution Strategy**: Conflicts with critical headers trigger warnings and follow strict priority rules.

### Unique Headers
These headers should appear only once in HTTP responses:

- `Content-Length`
- `Content-Type`
- `Content-Encoding`
- `Host`
- `Authorization`
- `Date`
- `Expires`
- `Last-Modified`
- `ETag`
- `Location`
- `Server`
- `User-Agent`
- `Referer`
- `WWW-Authenticate`
- `Access-Control-Allow-Origin`
- `Access-Control-Allow-Credentials`
- `Access-Control-Max-Age`

**Resolution Strategy**: First value wins, subsequent values are ignored with logging.

### Combinable Headers
These headers can have multiple values combined using appropriate separators:

- `Accept`
- `Accept-Charset`
- `Accept-Encoding`
- `Accept-Language`
- `Cache-Control`
- `Connection`
- `Cookie`
- `Pragma`
- `Upgrade`
- `Via`
- `Warning`
- `Vary`
- `Access-Control-Allow-Methods`
- `Access-Control-Allow-Headers`

**Resolution Strategy**: Values are combined using header-specific rules.

## Specific Header Resolution Rules

### Content-Length
```
Priority: PSR-7 Response > Runtime Calculation > Middleware
Rule: PSR-7 response value takes absolute precedence
Rationale: Application knows the exact content length
```

**Example**:
```php
// PSR-7 Response sets: Content-Length: 1024
// Runtime calculates: Content-Length: 1025
// Result: Content-Length: 1024 (PSR-7 wins)
```

### Content-Type
```
Priority: PSR-7 Response > Middleware > Runtime Default
Rule: Application-set content type overrides all others
Rationale: Application determines the actual content type
```

**Example**:
```php
// PSR-7 Response sets: Content-Type: application/json
// Runtime default: Content-Type: text/html
// Result: Content-Type: application/json
```

### Content-Encoding
```
Priority: Runtime Compression > PSR-7 Response > Middleware
Rule: Runtime compression settings take precedence
Rationale: Runtime handles actual compression
```

**Example**:
```php
// Runtime sets: Content-Encoding: gzip
// PSR-7 Response sets: Content-Encoding: deflate
// Result: Content-Encoding: gzip (Runtime compression wins)
```

### Server
```
Priority: Runtime > PSR-7 Response > Default
Rule: Runtime server identification takes precedence
Rationale: Runtime determines the actual server
```

### CORS Headers
```
Priority: Middleware > PSR-7 Response > Runtime
Rule: CORS middleware configuration overrides application headers
Rationale: CORS is a security concern handled by middleware
```

**Example**:
```php
// Middleware sets: Access-Control-Allow-Origin: https://example.com
// PSR-7 sets: Access-Control-Allow-Origin: *
// Result: Access-Control-Allow-Origin: https://example.com
```

### Security Headers
```
Priority: Middleware > Runtime > PSR-7 Response
Rule: Security middleware takes precedence
Rationale: Security headers should be controlled by infrastructure
```

### Cache-Control (Combinable)
```
Priority: Combine all sources with comma separation
Rule: All cache directives are preserved
Rationale: Multiple cache directives can coexist
```

**Example**:
```php
// PSR-7 Response: Cache-Control: max-age=3600
// Middleware: Cache-Control: no-cache
// Result: Cache-Control: max-age=3600, no-cache
```

### Set-Cookie (Special Case)
```
Priority: All values preserved as separate headers
Rule: Each Set-Cookie creates a separate header
Rationale: Multiple cookies require separate Set-Cookie headers
```

**Note**: Set-Cookie headers are not combined but sent as multiple headers.

## Resolution Process

### Step 1: Header Collection
1. Collect headers from PSR-7 response
2. Collect headers from runtime
3. Collect headers from middleware
4. Normalize all header names (case-insensitive)

### Step 2: Conflict Detection
1. Identify duplicate header names (after normalization)
2. Categorize headers (critical, unique, combinable)
3. Log conflicts if debug mode is enabled

### Step 3: Resolution Application
1. Apply priority rules based on header category
2. Combine values for combinable headers
3. Select highest priority value for unique headers
4. Log resolution decisions

### Step 4: Validation
1. Validate final header values
2. Check for HTTP compliance
3. Apply length limits
4. Generate warnings for critical conflicts

## Conflict Resolution Examples

### Example 1: Content-Length Conflict
```php
// Input:
PSR-7 Response: ['Content-Length' => '1024']
Runtime: ['content-length' => '1025']

// Process:
1. Normalize names: both become 'Content-Length'
2. Detect conflict: Content-Length appears twice
3. Apply rule: PSR-7 takes precedence
4. Log: "Content-Length conflict resolved: PSR-7 value (1024) used"

// Output:
['Content-Length' => '1024']
```

### Example 2: Cache-Control Combination
```php
// Input:
PSR-7 Response: ['Cache-Control' => 'max-age=3600']
Middleware: ['cache-control' => 'no-cache, must-revalidate']

// Process:
1. Normalize names: both become 'Cache-Control'
2. Detect conflict: Cache-Control appears twice
3. Apply rule: Combine values (Cache-Control is combinable)
4. Combine: 'max-age=3600, no-cache, must-revalidate'

// Output:
['Cache-Control' => 'max-age=3600, no-cache, must-revalidate']
```

### Example 3: CORS Headers Priority
```php
// Input:
PSR-7 Response: ['Access-Control-Allow-Origin' => '*']
CORS Middleware: ['Access-Control-Allow-Origin' => 'https://example.com']

// Process:
1. Normalize names: both become 'Access-Control-Allow-Origin'
2. Detect conflict: CORS header appears twice
3. Apply rule: Middleware takes precedence for CORS
4. Log: "CORS header conflict: middleware value used for security"

// Output:
['Access-Control-Allow-Origin' => 'https://example.com']
```

## Custom Resolution Rules

You can define custom resolution rules in the configuration:

```php
'header_deduplication' => [
    'custom_rules' => [
        'X-Custom-Header' => [
            'priority' => 'psr7_first',     // psr7_first, runtime_first, combine
            'combinable' => false,          // Whether values can be combined
            'separator' => ', ',            // Separator for combinable headers
            'critical' => false,            // Whether conflicts should be logged as critical
        ],
    ],
],
```

## Debugging Resolution

Enable debug logging to see resolution decisions:

```php
'header_deduplication' => [
    'debug_logging' => true,
    'log_critical_conflicts' => true,
],
```

**Debug output example**:
```
[HeaderDeduplication] Header conflict detected for 'Content-Length': existing='1024', new='1025', resolution='keep_existing'
[HeaderDeduplication] Successfully combined header 'Cache-Control' values
[HeaderDeduplication] CRITICAL header conflict detected for 'Content-Length': existing='1024', new='1025'
```

## Best Practices

1. **Understand your middleware stack** - Know what headers your middleware sets
2. **Use PSR-7 for application headers** - Set content-specific headers in your application
3. **Let runtime handle server headers** - Don't override Server, Date, etc. in application code
4. **Configure CORS properly** - Use middleware configuration instead of manual headers
5. **Monitor conflict logs** - Regular review helps identify problematic patterns
6. **Test with debug mode** - Enable debug logging during development
7. **Use strict mode for testing** - Catch conflicts early in development

## Performance Considerations

- Header resolution adds minimal overhead (< 1ms for typical requests)
- Caching is used for header name normalization
- Batch processing optimizes multiple header sets
- Performance logging can be enabled for monitoring

## Troubleshooting

See the [Header Deduplication Troubleshooting Guide](header-deduplication-troubleshooting.md) for common issues and solutions.