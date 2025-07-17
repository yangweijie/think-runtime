<?php

/**
 * Header Deduplication Configuration Examples
 * 
 * This file contains various configuration examples for different environments
 * and use cases. Copy the relevant sections to your config/runtime.php file.
 */

return [
    // ========================================
    // DEVELOPMENT ENVIRONMENT CONFIGURATION
    // ========================================
    'development' => [
        'header_deduplication' => [
            'enabled' => true,
            'debug_logging' => true,                    // Enable detailed logging
            'strict_mode' => true,                      // Throw exceptions on conflicts
            'log_critical_conflicts' => true,          // Log critical conflicts
            'throw_on_merge_failure' => true,          // Fail fast on merge errors
            'preserve_original_case' => false,         // Normalize header names
            'max_header_value_length' => 8192,         // 8KB limit
            'enable_performance_logging' => true,      // Monitor performance
            'enable_header_name_cache' => true,        // Enable caching
            'max_cache_size' => 1000,                  // Cache up to 1000 names
            'enable_batch_processing' => true,         // Batch optimization
            'log_level' => 'debug',                    // Most verbose logging
            'log_file' => 'runtime/logs/header_debug.log', // Dedicated log file
            
            // Custom rules for development testing
            'custom_rules' => [
                'X-Debug-Token' => [
                    'priority' => 'psr7_first',
                    'combinable' => false,
                    'critical' => false,
                ],
                'X-Request-ID' => [
                    'priority' => 'runtime_first',
                    'combinable' => false,
                    'critical' => true,
                ],
            ],
        ],
    ],

    // ========================================
    // PRODUCTION ENVIRONMENT CONFIGURATION
    // ========================================
    'production' => [
        'header_deduplication' => [
            'enabled' => true,
            'debug_logging' => false,                   // Disable debug logs
            'strict_mode' => false,                     // Don't throw exceptions
            'log_critical_conflicts' => true,          // Still log critical issues
            'throw_on_merge_failure' => false,         // Use fallback behavior
            'preserve_original_case' => false,         // Normalize for consistency
            'max_header_value_length' => 8192,         // Standard limit
            'enable_performance_logging' => false,     // Disable for performance
            'enable_header_name_cache' => true,        // Keep caching enabled
            'max_cache_size' => 2000,                  // Larger cache for production
            'enable_batch_processing' => true,         // Optimize batch operations
            'log_level' => 'warning',                  // Only warnings and errors
            'log_file' => 'runtime/logs/header_deduplication.log',
            
            // Minimal custom rules for production
            'custom_rules' => [],
        ],
    ],

    // ========================================
    // HIGH-PERFORMANCE ENVIRONMENT
    // ========================================
    'high_performance' => [
        'header_deduplication' => [
            'enabled' => true,
            'debug_logging' => false,                   // No debug overhead
            'strict_mode' => false,                     // No exceptions
            'log_critical_conflicts' => false,         // Minimal logging
            'throw_on_merge_failure' => false,         // Always use fallback
            'preserve_original_case' => false,         // Normalize efficiently
            'max_header_value_length' => 4096,         // Smaller limit for speed
            'enable_performance_logging' => false,     // No performance overhead
            'enable_header_name_cache' => true,        // Cache for speed
            'max_cache_size' => 5000,                  // Large cache
            'enable_batch_processing' => true,         // Batch optimization
            'log_level' => 'error',                    // Only errors
            'log_file' => null,                        // Use default logger
            'custom_rules' => [],                      // No custom processing
        ],
    ],

    // ========================================
    // TESTING ENVIRONMENT CONFIGURATION
    // ========================================
    'testing' => [
        'header_deduplication' => [
            'enabled' => true,
            'debug_logging' => true,                    // Full debugging
            'strict_mode' => true,                      // Catch all issues
            'log_critical_conflicts' => true,          // Log everything
            'throw_on_merge_failure' => true,          // Fail tests on issues
            'preserve_original_case' => false,         // Consistent behavior
            'max_header_value_length' => 8192,         // Standard limit
            'enable_performance_logging' => true,      // Monitor test performance
            'enable_header_name_cache' => false,       // Disable for consistent tests
            'max_cache_size' => 100,                   // Small cache for testing
            'enable_batch_processing' => false,        // Test individual operations
            'log_level' => 'debug',                    // Full logging
            'log_file' => 'tests/logs/header_test.log',
            
            // Test-specific custom rules
            'custom_rules' => [
                'X-Test-Header' => [
                    'priority' => 'combine',
                    'combinable' => true,
                    'separator' => '; ',
                    'critical' => false,
                ],
            ],
        ],
    ],

    // ========================================
    // RUNTIME-SPECIFIC CONFIGURATIONS
    // ========================================
    'runtime_specific' => [
        'runtimes' => [
            // Swoole-specific configuration
            'swoole' => [
                'header_deduplication' => [
                    'enabled' => true,
                    'debug_logging' => false,
                    'strict_mode' => false,
                    'enable_performance_logging' => true,  // Monitor Swoole performance
                    'custom_rules' => [
                        'Server' => [
                            'priority' => 'runtime_first',    // Let Swoole set server header
                            'combinable' => false,
                            'critical' => false,
                        ],
                    ],
                ],
            ],

            // Workerman-specific configuration
            'workerman' => [
                'header_deduplication' => [
                    'enabled' => true,
                    'debug_logging' => false,
                    'log_critical_conflicts' => true,
                    'custom_rules' => [
                        'Connection' => [
                            'priority' => 'runtime_first',    // Workerman handles connections
                            'combinable' => false,
                            'critical' => false,
                        ],
                        'Keep-Alive' => [
                            'priority' => 'runtime_first',
                            'combinable' => false,
                            'critical' => false,
                        ],
                    ],
                ],
            ],

            // FrankenPHP-specific configuration
            'frankenphp' => [
                'header_deduplication' => [
                    'enabled' => true,
                    'debug_logging' => false,
                    'custom_rules' => [
                        'Server' => [
                            'priority' => 'runtime_first',    // FrankenPHP server identification
                            'combinable' => false,
                            'critical' => false,
                        ],
                        'X-Powered-By' => [
                            'priority' => 'runtime_first',
                            'combinable' => false,
                            'critical' => false,
                        ],
                    ],
                ],
            ],

            // Serverless configurations (Bref, Vercel)
            'bref' => [
                'header_deduplication' => [
                    'enabled' => true,
                    'debug_logging' => false,
                    'strict_mode' => false,
                    'max_header_value_length' => 4096,    // AWS Lambda limits
                    'enable_performance_logging' => false, // Minimize cold start impact
                    'custom_rules' => [
                        'X-Amzn-RequestId' => [
                            'priority' => 'runtime_first',
                            'combinable' => false,
                            'critical' => false,
                        ],
                    ],
                ],
            ],

            'vercel' => [
                'header_deduplication' => [
                    'enabled' => true,
                    'debug_logging' => false,
                    'strict_mode' => false,
                    'max_header_value_length' => 4096,    // Vercel limits
                    'enable_performance_logging' => false, // Minimize function overhead
                    'custom_rules' => [
                        'X-Vercel-Id' => [
                            'priority' => 'runtime_first',
                            'combinable' => false,
                            'critical' => false,
                        ],
                    ],
                ],
            ],
        ],
    ],

    // ========================================
    // CUSTOM RULES EXAMPLES
    // ========================================
    'custom_rules_examples' => [
        'header_deduplication' => [
            'custom_rules' => [
                // API versioning header
                'X-API-Version' => [
                    'priority' => 'psr7_first',           // Application controls API version
                    'combinable' => false,                // Only one version per request
                    'separator' => ', ',                  // Not used since not combinable
                    'critical' => true,                   // Log conflicts as critical
                ],

                // Rate limiting headers (combinable for multiple limits)
                'X-RateLimit-Limit' => [
                    'priority' => 'combine',              // Combine multiple limits
                    'combinable' => true,                 // Allow multiple rate limits
                    'separator' => '; ',                  // Semicolon separator
                    'critical' => false,                  // Not critical
                ],

                // Security headers (runtime should control these)
                'X-Frame-Options' => [
                    'priority' => 'runtime_first',       // Security middleware controls
                    'combinable' => false,                // Only one frame option
                    'critical' => true,                   // Security is critical
                ],

                // Custom application headers
                'X-App-Environment' => [
                    'priority' => 'psr7_first',          // Application sets environment
                    'combinable' => false,                // Single environment
                    'critical' => false,                  // Not critical
                ],

                // Tracing headers (can be combined from multiple sources)
                'X-Trace-Id' => [
                    'priority' => 'combine',              // Combine trace IDs
                    'combinable' => true,                 // Multiple trace systems
                    'separator' => ', ',                  // Comma separated
                    'critical' => false,                  // Not critical for response
                ],

                // Cache control extensions
                'X-Cache-Tags' => [
                    'priority' => 'combine',              // Combine all cache tags
                    'combinable' => true,                 // Multiple tag sources
                    'separator' => ' ',                   // Space separated tags
                    'critical' => false,                  // Not critical
                ],

                // Content security policy (should not be combined)
                'Content-Security-Policy' => [
                    'priority' => 'runtime_first',       // Security middleware controls
                    'combinable' => false,                // CSP should be single policy
                    'critical' => true,                   // Security critical
                ],

                // Custom metrics headers
                'X-Response-Time' => [
                    'priority' => 'runtime_first',       // Runtime measures response time
                    'combinable' => false,                // Single measurement
                    'critical' => false,                  // Not critical
                ],
            ],
        ],
    ],

    // ========================================
    // DEBUGGING CONFIGURATION
    // ========================================
    'debugging' => [
        'header_deduplication' => [
            'enabled' => true,
            'debug_logging' => true,
            'strict_mode' => true,                      // Fail fast for debugging
            'log_critical_conflicts' => true,
            'throw_on_merge_failure' => true,          // Don't hide merge issues
            'preserve_original_case' => true,          // See original header names
            'max_header_value_length' => 16384,        // Allow larger values for debugging
            'enable_performance_logging' => true,      // Monitor performance impact
            'enable_header_name_cache' => false,       // Disable cache to see all operations
            'max_cache_size' => 0,                     // No caching
            'enable_batch_processing' => false,        // Process individually for clarity
            'log_level' => 'debug',                    // Maximum verbosity
            'log_file' => 'runtime/logs/header_debug_detailed.log',
            'custom_rules' => [],                      // No custom rules to isolate issues
        ],
    ],

    // ========================================
    // MINIMAL CONFIGURATION
    // ========================================
    'minimal' => [
        'header_deduplication' => [
            'enabled' => true,                         // Only enable basic functionality
            // All other options use defaults
        ],
    ],

    // ========================================
    // DISABLED CONFIGURATION
    // ========================================
    'disabled' => [
        'header_deduplication' => [
            'enabled' => false,                        // Completely disable header deduplication
        ],
    ],
];