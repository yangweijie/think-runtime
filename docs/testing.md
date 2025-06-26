# Testing Guide

## Overview

This project uses [Pest](https://pestphp.com/) as the testing framework. Pest provides an elegant and expressive testing experience for PHP.

## Installation

The testing dependencies are already included in the `composer.json` file:

```bash
composer install --dev
```

## Running Tests

### Basic Test Execution

```bash
# Run all tests
composer test

# Or use Pest directly
./vendor/bin/pest
```

### Test Coverage

```bash
# Run tests with coverage report
composer test-coverage

# Or use Pest directly
./vendor/bin/pest --coverage
```

### Watch Mode

```bash
# Run tests in watch mode (re-runs on file changes)
composer test-watch

# Or use Pest directly
./vendor/bin/pest --watch
```

## Test Structure

The tests are organized into three main categories:

### Unit Tests (`tests/Unit/`)

Test individual classes and methods in isolation:

- `Runtime/` - Tests for runtime implementations
- `Resolver/` - Tests for parameter resolvers
- `Runner/` - Tests for application runners

### Feature Tests (`tests/Feature/`)

Test specific features and functionality:

- `ComposerPluginTest.php` - Tests for the Composer plugin

### Integration Tests (`tests/Integration/`)

Test the complete workflow and component interactions:

- `RuntimeIntegrationTest.php` - End-to-end runtime testing

## Test Helpers

### Base Test Case

All tests extend the `TestCase` class which provides:

- Global state reset between tests
- Mock object creation helpers
- Environment setup utilities

### Mock Factory

The `MockFactory` class provides methods to create mock objects:

```php
use Think\Runtime\Tests\Helpers\MockFactory;

// Create mock request
$request = MockFactory::createRequest([
    'method' => 'POST',
    'uri' => '/api/test',
    'body' => ['data' => 'value']
]);

// Create mock response
$response = MockFactory::createResponse([
    'status' => 200,
    'content' => 'Success'
]);
```

### Global Helper Functions

Available in all tests:

```php
// Create a mock application
$app = createMockApp();

// Create a mock request
$request = createMockRequest();
```

## Custom Expectations

The test suite includes custom expectations:

```php
expect($runtime)->toBeRuntime();
expect($resolver)->toBeResolver();
expect($runner)->toBeRunner();
```

## Writing Tests

### Basic Test Structure

```php
<?php

use Think\Runtime\Runtime\ThinkPHPRuntime;

it('can create runtime instance', function () {
    $runtime = new ThinkPHPRuntime();
    
    expect($runtime)->toBeRuntime();
});
```

### Using beforeEach and afterEach

```php
beforeEach(function () {
    $this->runtime = new ThinkPHPRuntime();
    // Reset global state
    $_SERVER = ['REQUEST_METHOD' => 'GET'];
});

afterEach(function () {
    // Clean up after each test
});
```

### Testing Exceptions

```php
it('throws exception for invalid input', function () {
    expect(fn() => $runtime->getRunner(new stdClass()))
        ->toThrow(InvalidArgumentException::class);
});
```

### Testing Output

```php
it('outputs correct response', function () {
    ob_start();
    $runner->run();
    $output = ob_get_clean();
    
    expect($output)->toBe('Expected output');
});
```

## Test Configuration

### PHPUnit Configuration

The `phpunit.xml` file configures:

- Test suites organization
- Code coverage settings
- Environment variables
- PHP settings

### Environment Variables

Tests run with these environment variables:

- `APP_ENV=testing`
- `APP_DEBUG=true`

## Continuous Integration

### GitHub Actions

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: [8.0, 8.1, 8.2, 8.3]
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: mbstring, xml, ctype, json
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run tests
        run: composer test-coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
```

## Best Practices

### 1. Test Isolation

Each test should be independent and not rely on other tests:

```php
beforeEach(function () {
    // Reset state before each test
    $this->resetGlobalState();
});
```

### 2. Descriptive Test Names

Use descriptive test names that explain what is being tested:

```php
it('returns 0 exit code when application runs successfully', function () {
    // Test implementation
});
```

### 3. Arrange, Act, Assert

Structure tests with clear sections:

```php
it('processes request correctly', function () {
    // Arrange
    $runtime = new ThinkPHPRuntime();
    $app = createMockApp();
    
    // Act
    $runner = $runtime->getRunner($app);
    $exitCode = $runner->run();
    
    // Assert
    expect($exitCode)->toBe(0);
});
```

### 4. Mock External Dependencies

Mock external dependencies to ensure tests are fast and reliable:

```php
it('handles external service failure', function () {
    $mockService = Mockery::mock(ExternalService::class);
    $mockService->shouldReceive('call')->andThrow(new Exception());
    
    // Test error handling
});
```

### 5. Test Edge Cases

Include tests for edge cases and error conditions:

```php
it('handles empty input gracefully', function () {
    expect($resolver->resolve(null))->not->toThrow();
});
```

## Debugging Tests

### Running Specific Tests

```bash
# Run specific test file
./vendor/bin/pest tests/Unit/Runtime/ThinkPHPRuntimeTest.php

# Run specific test
./vendor/bin/pest --filter "can create runtime instance"
```

### Debug Output

```php
it('debugs test execution', function () {
    dump($variable); // Output variable for debugging
    ray($data); // Use Ray for debugging (if installed)
    
    expect($result)->toBe($expected);
});
```

### Verbose Output

```bash
# Run tests with verbose output
./vendor/bin/pest -v

# Run tests with very verbose output
./vendor/bin/pest -vv
```
