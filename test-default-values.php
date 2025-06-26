<?php

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Resolver\GenericResolver;

echo "🧪 Testing Default Values Priority...\n\n";

// Test 1: String with default value
echo "1. String parameter with default value:\n";
$callable1 = function(string $optional = 'default') { return $optional; };
$resolver1 = new GenericResolver($callable1);

// Get reflection to check
$reflection = new ReflectionFunction($callable1);
$parameter = $reflection->getParameters()[0];
echo "   Has default value: " . ($parameter->isDefaultValueAvailable() ? 'YES' : 'NO') . "\n";
echo "   Default value: " . var_export($parameter->getDefaultValue(), true) . "\n";

[$callable, $arguments] = $resolver1->resolve();
echo "   Resolved argument: " . var_export($arguments[0], true) . "\n";
echo "   Expected: 'default'\n";
echo "   Correct: " . ($arguments[0] === 'default' ? 'YES' : 'NO') . "\n\n";

// Test 2: Int with default value
echo "2. Int parameter with default value:\n";
$callable2 = function(int $number = 42) { return $number; };
$resolver2 = new GenericResolver($callable2);
[$callable, $arguments] = $resolver2->resolve();
echo "   Resolved argument: " . var_export($arguments[0], true) . "\n";
echo "   Expected: 42\n";
echo "   Correct: " . ($arguments[0] === 42 ? 'YES' : 'NO') . "\n\n";

// Test 3: String without default value
echo "3. String parameter without default value:\n";
$callable3 = function(string $required) { return $required; };
$resolver3 = new GenericResolver($callable3);
try {
    [$callable, $arguments] = $resolver3->resolve();
    echo "   Resolved argument: " . var_export($arguments[0], true) . "\n";
    echo "   Expected: '' (empty string)\n";
    echo "   Correct: " . ($arguments[0] === '' ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "   Exception: " . $e->getMessage() . "\n";
}

echo "\n4. Testing parameter resolution order:\n";
$testResolver = new class(function(){}) extends GenericResolver {
    public function testParameterResolution() {
        $callable = function(string $optional = 'default') { return $optional; };
        $reflection = new ReflectionFunction($callable);
        $parameter = $reflection->getParameters()[0];
        
        echo "   Step 1 - Check default value: ";
        if ($parameter->isDefaultValueAvailable()) {
            echo "Found default: " . var_export($parameter->getDefaultValue(), true) . "\n";
            return $parameter->getDefaultValue();
        }
        echo "No default\n";
        
        echo "   Step 2 - Check by name: ";
        $byName = $this->resolveByName('optional');
        echo var_export($byName, true) . "\n";
        
        echo "   Step 3 - Check builtin type: ";
        $type = $parameter->getType();
        if ($type && $type->isBuiltin()) {
            $default = match ($type->getName()) {
                'string' => '',
                'int' => 0,
                default => null,
            };
            echo var_export($default, true) . "\n";
            return $default;
        }
        
        return null;
    }
};

$result = $testResolver->testParameterResolution();
echo "   Final result: " . var_export($result, true) . "\n";
