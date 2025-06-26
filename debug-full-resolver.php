<?php

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Resolver\GenericResolver;

echo "🔍 Debugging Full Resolver Flow...\n\n";

$callable = function(string $optional = 'default') { return $optional; };
$resolver = new GenericResolver($callable);

echo "1. Testing resolve() method:\n";
[$resolvedCallable, $arguments] = $resolver->resolve();

echo "   Arguments count: " . count($arguments) . "\n";
echo "   Arguments: " . var_export($arguments, true) . "\n";

echo "\n2. Testing resolveByName method:\n";
$testResolver = new class($callable) extends GenericResolver {
    public function testResolveByName($name) {
        return $this->resolveByName($name);
    }
};

$nameResult = $testResolver->testResolveByName('optional');
echo "   resolveByName('optional'): " . var_export($nameResult, true) . "\n";

echo "\n3. Testing with different parameter name:\n";
$callable2 = function(string $test = 'default') { return $test; };
$resolver2 = new GenericResolver($callable2);
[$resolvedCallable2, $arguments2] = $resolver2->resolve();
echo "   Arguments for 'test' parameter: " . var_export($arguments2, true) . "\n";

echo "\n4. Testing with non-string parameter:\n";
$callable3 = function(int $number = 42) { return $number; };
$resolver3 = new GenericResolver($callable3);
[$resolvedCallable3, $arguments3] = $resolver3->resolve();
echo "   Arguments for 'number' parameter: " . var_export($arguments3, true) . "\n";
