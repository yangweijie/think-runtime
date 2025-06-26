<?php

declare(strict_types=1);

use Think\Runtime\Resolver\GenericResolver;
use Think\Runtime\Contract\ResolverInterface;

beforeEach(function () {
    // Reset global state
    $_GET = [];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SESSION = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'HTTP_HOST' => 'localhost',
    ];
    $_ENV = [];
});

it('implements ResolverInterface', function () {
    $callable = function () {};
    $resolver = new GenericResolver($callable);
    
    expect($resolver)->toBeInstanceOf(ResolverInterface::class);
});

it('supports all callables', function () {
    $callable = function () {};
    $resolver = new GenericResolver($callable);
    
    expect($resolver->supports($callable))->toBeTrue();
});

it('resolves callable with no parameters', function () {
    $callable = function () {
        return 'test';
    };
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    expect($resolvedCallable)->toBe($callable)
        ->and($arguments)->toBeArray()
        ->and($arguments)->toHaveCount(0);
});

it('resolves context array parameter', function () {
    $callable = function (array $context) {
        return $context;
    };
    
    $_SERVER['TEST_VAR'] = 'test_value';
    $_ENV['ENV_VAR'] = 'env_value';
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    expect($arguments)->toHaveCount(1)
        ->and($arguments[0])->toBeArray()
        ->and($arguments[0])->toHaveKey('TEST_VAR')
        ->and($arguments[0])->toHaveKey('ENV_VAR')
        ->and($arguments[0]['TEST_VAR'])->toBe('test_value')
        ->and($arguments[0]['ENV_VAR'])->toBe('env_value');
});

it('resolves argv array parameter', function () {
    $callable = function (array $argv) {
        return $argv;
    };
    
    $_SERVER['argv'] = ['script.php', 'arg1', 'arg2'];
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    expect($arguments)->toHaveCount(1)
        ->and($arguments[0])->toBe(['script.php', 'arg1', 'arg2']);
});

it('resolves request array parameter', function () {
    $callable = function (array $request) {
        return $request;
    };
    
    $_GET = ['query' => 'value'];
    $_POST = ['body' => 'data'];
    $_FILES = ['file' => 'upload'];
    $_SESSION = ['session' => 'data'];
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    expect($arguments)->toHaveCount(1)
        ->and($arguments[0])->toBeArray()
        ->and($arguments[0])->toHaveKey('query')
        ->and($arguments[0])->toHaveKey('body')
        ->and($arguments[0])->toHaveKey('files')
        ->and($arguments[0])->toHaveKey('session')
        ->and($arguments[0]['query'])->toBe(['query' => 'value'])
        ->and($arguments[0]['body'])->toBe(['body' => 'data']);
});

it('resolves multiple parameters', function () {
    $callable = function (array $context, array $argv, array $request) {
        return [$context, $argv, $request];
    };
    
    $_SERVER['TEST'] = 'value';
    $_SERVER['argv'] = ['script.php'];
    $_GET = ['test' => 'query'];
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    expect($arguments)->toHaveCount(3)
        ->and($arguments[0])->toBeArray() // context
        ->and($arguments[1])->toBeArray() // argv
        ->and($arguments[2])->toBeArray(); // request
});

it('uses default values for optional parameters', function () {
    $callable = function (array $context = [], string $optional = 'default') {
        return [$context, $optional];
    };
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    expect($arguments)->toHaveCount(2)
        ->and($arguments[0])->toBeArray()
        ->and($arguments[1])->toBe('default');
});

it('handles nullable parameters', function () {
    $callable = function (?string $nullable = null) {
        return $nullable;
    };
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    expect($arguments)->toHaveCount(1)
        ->and($arguments[0])->toBeNull();
});

it('throws exception for unresolvable required parameters', function () {
    // Use a custom class that cannot be resolved
    $callable = function (DateTime $required) {
        return $required;
    };

    $resolver = new GenericResolver($callable);

    expect(fn() => $resolver->resolve())
        ->toThrow(InvalidArgumentException::class);
});

it('resolves named parameters correctly', function () {
    $callable = function (array $context, array $argv) {
        return compact('context', 'argv');
    };
    
    $_SERVER['TEST'] = 'server_value';
    $_SERVER['argv'] = ['test_script'];
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    expect($arguments[0])->toHaveKey('TEST') // context parameter
        ->and($arguments[1])->toBe(['test_script']); // argv parameter
});
