<?php

declare(strict_types=1);

use Think\Runtime\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Unit', 'Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeRuntime', function () {
    return $this->toBeInstanceOf(\Think\Runtime\Contract\RuntimeInterface::class);
});

expect()->extend('toBeResolver', function () {
    return $this->toBeInstanceOf(\Think\Runtime\Contract\ResolverInterface::class);
});

expect()->extend('toBeRunner', function () {
    return $this->toBeInstanceOf(\Think\Runtime\Contract\RunnerInterface::class);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createMockApp(): object
{
    return new class {
        public function handle($request)
        {
            return 'Hello World';
        }
        
        public function getEnvironment(): string
        {
            return 'test';
        }
        
        public function isDebug(): bool
        {
            return true;
        }
        
        public function terminate($request, $response): void
        {
            // Mock terminate
        }
    };
}

function createMockRequest(): object
{
    return new class {
        public function method(): string
        {
            return 'GET';
        }
        
        public function uri(): string
        {
            return '/test';
        }
        
        public function getUri()
        {
            return $this;
        }
        
        public function getPath(): string
        {
            return '/test';
        }
        
        public function getQuery(): string
        {
            return '';
        }
        
        public function getHost(): string
        {
            return 'localhost';
        }
        
        public function getPort(): ?int
        {
            return 8080;
        }
        
        public function getScheme(): string
        {
            return 'http';
        }
        
        public function getMethod(): string
        {
            return 'GET';
        }
        
        public function getHeaders(): array
        {
            return [];
        }
        
        public function getHeaderLine(string $name): string
        {
            return '';
        }
        
        public function hasHeader(string $name): bool
        {
            return false;
        }
        
        public function getBody()
        {
            return '';
        }
    };
}
