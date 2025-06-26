<?php

declare(strict_types=1);

use Think\Runtime\Runner\VoidRunner;
use Think\Runtime\Contract\RunnerInterface;

it('implements RunnerInterface', function () {
    $runner = new VoidRunner();
    
    expect($runner)->toBeInstanceOf(RunnerInterface::class);
});

it('always returns 0', function () {
    $runner = new VoidRunner();
    $exitCode = $runner->run();
    
    expect($exitCode)->toBe(0);
});

it('can be instantiated without parameters', function () {
    $runner = new VoidRunner();
    
    expect($runner)->toBeInstanceOf(VoidRunner::class);
});
