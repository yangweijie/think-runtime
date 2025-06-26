<?php

declare(strict_types=1);

use Think\Runtime\Runner\CallableRunner;
use Think\Runtime\Contract\RunnerInterface;

it('implements RunnerInterface', function () {
    $callable = function () {
        return 0;
    };
    
    $runner = new CallableRunner($callable);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('runs callable and returns exit code', function () {
    $callable = function () {
        return 42;
    };
    
    $runner = new CallableRunner($callable);
    $exitCode = $runner->run();
    
    expect($exitCode)->toBe(42);
});

it('returns 0 for non-integer return values', function () {
    $callable = function () {
        return 'success';
    };
    
    $runner = new CallableRunner($callable);
    $exitCode = $runner->run();
    
    expect($exitCode)->toBe(0);
});

it('returns 0 for null return values', function () {
    $callable = function () {
        return null;
    };
    
    $runner = new CallableRunner($callable);
    $exitCode = $runner->run();
    
    expect($exitCode)->toBe(0);
});

it('can execute callable with side effects', function () {
    $executed = false;
    $callable = function () use (&$executed) {
        $executed = true;
        return 0;
    };
    
    $runner = new CallableRunner($callable);
    $runner->run();
    
    expect($executed)->toBeTrue();
});

it('accepts options in constructor', function () {
    $callable = function () {
        return 0;
    };
    
    $options = ['debug' => true];
    $runner = new CallableRunner($callable, $options);
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});
