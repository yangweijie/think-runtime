<?php

/**
 * Simple test runner to verify basic functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Runtime\ThinkPHPRuntime;
use Think\Runtime\Runtime\WorkermanRuntime;
use Think\Runtime\Runtime\ReactPHPRuntime;
use Think\Runtime\Resolver\GenericResolver;
use Think\Runtime\Runner\CallableRunner;
use Think\Runtime\Runner\VoidRunner;

echo "Testing ThinkPHP Runtime Components...\n\n";

// Test 1: Basic Runtime Creation
echo "1. Testing Runtime Creation...\n";
try {
    $runtime = new ThinkPHPRuntime();
    echo "   ✓ ThinkPHPRuntime created successfully\n";
    
    $workermanRuntime = new WorkermanRuntime();
    echo "   ✓ WorkermanRuntime created successfully\n";
    
    $reactRuntime = new ReactPHPRuntime();
    echo "   ✓ ReactPHPRuntime created successfully\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Resolver Functionality
echo "\n2. Testing Resolver...\n";
try {
    $callable = function (array $context) {
        return 'test';
    };
    
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    
    echo "   ✓ Resolver created and resolved successfully\n";
    echo "   ✓ Arguments count: " . count($arguments) . "\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Runner Functionality
echo "\n3. Testing Runners...\n";
try {
    $callableRunner = new CallableRunner(function () {
        return 0;
    });
    $exitCode = $callableRunner->run();
    echo "   ✓ CallableRunner executed, exit code: $exitCode\n";
    
    $voidRunner = new VoidRunner();
    $exitCode = $voidRunner->run();
    echo "   ✓ VoidRunner executed, exit code: $exitCode\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Runtime Integration
echo "\n4. Testing Runtime Integration...\n";
try {
    $runtime = new ThinkPHPRuntime();
    
    $appCallable = function (array $context) {
        return new class {
            public function handle($request) {
                return 'Hello World';
            }
        };
    };
    
    $resolver = $runtime->getResolver($appCallable);
    [$callable, $arguments] = $resolver->resolve();
    $application = $callable(...$arguments);
    $runner = $runtime->getRunner($application);
    
    echo "   ✓ Complete runtime workflow executed successfully\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Options Configuration
echo "\n5. Testing Options Configuration...\n";
try {
    $runtime = new ThinkPHPRuntime(['debug' => true, 'env' => 'testing']);
    $options = $runtime->getOptions();
    
    echo "   ✓ Runtime options configured\n";
    echo "   ✓ Debug mode: " . ($options['debug'] ? 'enabled' : 'disabled') . "\n";
    echo "   ✓ Environment: " . $options['env'] . "\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n✅ All basic tests completed!\n";
echo "\nYou can now run the full test suite with:\n";
echo "composer test\n";
