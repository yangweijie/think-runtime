<?php

declare(strict_types=1);

use Think\Runtime\Runner\ThinkPHP\HttpKernelRunner;
use Think\Runtime\Contract\RunnerInterface;

beforeEach(function () {
    // Reset global state
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'HTTP_HOST' => 'localhost',
    ];
});

it('implements RunnerInterface', function () {
    $app = createMockApp();
    $runner = new HttpKernelRunner($app);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('runs application and returns 0 on success', function () {
    $app = createMockApp();
    $runner = new HttpKernelRunner($app);

    $exitCode = $runner->run();

    expect($exitCode)->toBe(0);
})->expectsOutput('Hello World');

it('handles application with terminate method', function () {
    $app = new class {
        public bool $terminated = false;

        public function handle($request)
        {
            return new class {
                public function send(): void
                {
                    echo 'Response';
                }
            };
        }

        public function terminate($request, $response): void
        {
            $this->terminated = true;
        }
    };

    $runner = new HttpKernelRunner($app);
    $runner->run();

    expect($app->terminated)->toBeTrue();
})->expectsOutput('Response');

it('handles different response types', function () {
    // Test string response
    $app = new class {
        public function handle($request)
        {
            return 'String Response';
        }
    };

    $runner = new HttpKernelRunner($app);
    $exitCode = $runner->run();

    expect($exitCode)->toBe(0);
})->expectsOutput('String Response');

it('handles array response as JSON', function () {
    $app = new class {
        public function handle($request)
        {
            return ['message' => 'Hello', 'status' => 'success'];
        }
    };

    $runner = new HttpKernelRunner($app);
    $exitCode = $runner->run();

    expect($exitCode)->toBe(0);
})->expectsOutput('{"message":"Hello","status":"success"}');

it('returns 1 on exception', function () {
    $app = new class {
        public function handle($request)
        {
            throw new Exception('Test exception');
        }
    };

    $runner = new HttpKernelRunner($app, ['debug' => false]);
    $exitCode = $runner->run();

    expect($exitCode)->toBe(1);
})->expectsOutput("Internal Server Error\n");

it('shows detailed error in debug mode', function () {
    $app = new class {
        public function handle($request)
        {
            throw new Exception('Test exception');
        }
    };

    $runner = new HttpKernelRunner($app, ['debug' => true]);

    // Capture output manually for this test since we need to check content
    ob_start();
    $exitCode = $runner->run();
    $output = ob_get_contents();
    ob_end_flush(); // Use flush instead of clean to avoid closing buffer

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Error: Test exception');
});

it('creates request from globals', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/test?param=value';
    $_GET = ['param' => 'value'];
    $_POST = ['data' => 'test'];
    $_FILES = ['upload' => 'file'];
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token';

    $app = new class {
        public $capturedRequest = null;

        public function handle($request)
        {
            $this->capturedRequest = $request;
            return 'OK';
        }
    };

    $runner = new HttpKernelRunner($app);
    $runner->run();

    expect($app->capturedRequest)->not->toBeNull();
})->expectsOutput('OK');
