


# Implementation Plan

- [x] 1. Create header deduplication service and interfaces
  - Create HeaderDeduplicationInterface with methods for header merging and normalization
  - Implement HeaderDeduplicationService class with HTTP/1.1 compliant header processing
  - Add case-insensitive header name normalization and value combination logic
  - _Requirements: 1.1, 1.3, 3.1_

- [x] 2. Enhance AbstractRuntime with header management capabilities
  - Add HeaderDeduplicationService property and initialization in AbstractRuntime
  - Implement processResponseHeaders method for common header processing across adapters
  - Add shouldSkipRuntimeHeader method to prevent duplicate header setting
  - Create buildRuntimeHeaders method for adapter-specific header generation
  - _Requirements: 2.6, 3.1, 3.2_

- [x] 3. Fix WorkermanAdapter header duplication
  - Modify convertPsrResponseToWorkerman method to use header deduplication service
  - Update compression header handling to prevent Content-Length duplication
  - Remove manual Content-Length setting when PSR-7 response already has it
  - Add debug logging for header conflicts in WorkermanAdapter
  - _Requirements: 1.1, 1.2, 2.1, 3.1_

- [x] 4. Fix SwooleAdapter header duplication
  - Update sendSwooleResponse method to use centralized header processing
  - Modify middleware header setting to check for existing headers
  - Fix CORS and security middleware to avoid header conflicts
  - Ensure static file handler doesn't duplicate headers
  - _Requirements: 1.1, 1.2, 2.2, 3.1_

- [x] 5. Fix ReactPHPAdapter header duplication
  - Update handleReactRequest method to process headers before creating Response
  - Ensure uploaded file handling doesn't interfere with header processing
  - Add header deduplication to error response handling
  - _Requirements: 1.1, 1.2, 2.3, 3.1_

- [x] 6. Fix remaining adapter header duplications
  - Update FrankenPHPAdapter header processing in response handling
  - Fix BrefAdapter header setting in Lambda response conversion
  - Update VercelAdapter to use header deduplication service
  - Fix RoadrunnerAdapter header handling in PSR-7 conversion
  - Fix RippleAdapter setResponseHeaders method to prevent duplicates
  - _Requirements: 1.1, 1.2, 2.4, 2.5, 3.1_

- [x] 7. Create comprehensive unit tests for header deduplication
  - Write tests for HeaderDeduplicationService header merging logic
  - Test case-insensitive header name handling and normalization
  - Create tests for HTTP/1.1 compliant header value combination
  - Test conflict resolution rules and priority handling
  - _Requirements: 4.1, 4.4_

- [x] 8. Create basic integration tests for adapter header handling
  - Write basic test for WorkermanAdapter header deduplication integration
  - Verify adapters have access to header deduplication service
  - _Requirements: 4.2, 4.4_

- [x] 9. Add debug logging and error handling for header conflicts
  - Implement debug logging in HeaderDeduplicationService for conflict detection
  - Add warning logs for critical header conflicts with resolution details
  - Create descriptive exceptions for header merging failures
  - Add configuration option to enable/disable debug logging
  - _Requirements: 3.1, 3.2, 3.3_

- [-] 10. Expand integration tests for all adapters

  - Create comprehensive integration tests for each adapter's header handling
  - Test SwooleAdapter response sending with proper header deduplication
  - Create ReactPHPAdapter tests for header processing in request handling
  - Test FrankenPHPAdapter, BrefAdapter, VercelAdapter, RoadrunnerAdapter, and RippleAdapter
  - Verify no duplicate headers in final HTTP responses for each adapter
  - _Requirements: 4.2, 4.4_

- [x] 11. Create performance tests and optimization



  - Write performance tests to measure header deduplication overhead
  - Create stress tests for high-concurrency header processing
  - Implement memory usage monitoring for header processing
  - Optimize header deduplication for minimal performance impact
  - _Requirements: 4.5_

- [x] 12. Add configuration options and documentation



  - Create configuration schema for header deduplication settings
  - Add options for debug mode, strict mode, and custom rules
  - Document header conflict resolution rules and priority system
  - Create troubleshooting guide for header-related issues
  - _Requirements: 3.1, 3.2_

- [x] 13. Validate fix with end-to-end testing





  - Test complete request/response cycle with various header combinations
  - Verify browser compatibility and HTTP client compatibility
  - Test with real-world scenarios including compression and CORS
  - Validate that duplicate Content-Length headers are eliminated
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 4.3_