<?php

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Runtime\ThinkPHPRuntime;
use Think\Runtime\Resolver\GenericResolver;

echo "🔍 Debugging Failed Tests...\n\n";

// Debug Test 1: Resolver with Default Values
echo "1. Testing Resolver with Default Values:\n";
$callable = function(string $optional = 'default') { return $optional; };
$resolver = new GenericResolver($callable);
[$resolvedCallable, $arguments] = $resolver->resolve();

echo "   Arguments count: " . count($arguments) . "\n";
echo "   First argument: " . var_export($arguments[0], true) . "\n";
echo "   Expected: 'default'\n";
echo "   Match: " . ($arguments[0] === 'default' ? 'YES' : 'NO') . "\n\n";

// Debug Test 2: Callable Runner
echo "2. Testing Callable Runner:\n";
$runtime = new ThinkPHPRuntime();
$callable = function() { return 42; };
$runner = $runtime->getRunner($callable);
$exitCode = $runner->run();

echo "   Exit code: $exitCode\n";
echo "   Expected: 42\n";
echo "   Match: " . ($exitCode === 42 ? 'YES' : 'NO') . "\n";
echo "   Runner type: " . get_class($runner) . "\n\n";

// Check what runner is being used for callable
echo "3. Checking Runner Selection:\n";
$runners = $runtime->getOptions();
echo "   Available runners in runtime:\n";

// Let's check the AbstractRuntime logic
$reflection = new ReflectionClass($runtime);
$method = $reflection->getMethod('getRunner');
$method->setAccessible(true);

try {
    $runner = $method->invoke($runtime, $callable);
    echo "   Selected runner: " . get_class($runner) . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}
