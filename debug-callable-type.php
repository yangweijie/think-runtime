<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "🔍 Debugging Callable Type Issue...\n\n";

$callable = function(string $optional = 'default') { return $optional; };

echo "1. Direct callable analysis:\n";
echo "   Type: " . gettype($callable) . "\n";
echo "   Class: " . get_class($callable) . "\n";
echo "   instanceof Closure: " . ($callable instanceof Closure ? 'YES' : 'NO') . "\n";

echo "\n2. Testing with GenericResolver:\n";
use Think\Runtime\Resolver\GenericResolver;

// This should trigger our debug output
$resolver = new GenericResolver($callable);

echo "\n3. Testing with different callable types:\n";

// Test with regular function
function testFunction(string $optional = 'default') { return $optional; }
echo "   Regular function:\n";
$resolver2 = new GenericResolver('testFunction');

// Test with array callable
class TestClass {
    public static function testMethod(string $optional = 'default') { return $optional; }
}
echo "   Array callable:\n";
$resolver3 = new GenericResolver([TestClass::class, 'testMethod']);

// Test with object method
$obj = new TestClass();
echo "   Object method:\n";
$resolver4 = new GenericResolver([$obj, 'testMethod']);

echo "\n4. Testing closure creation in different ways:\n";
$closure1 = function(string $optional = 'default') { return $optional; };
echo "   Closure 1 instanceof Closure: " . ($closure1 instanceof Closure ? 'YES' : 'NO') . "\n";

$closure2 = Closure::fromCallable(function(string $optional = 'default') { return $optional; });
echo "   Closure 2 instanceof Closure: " . ($closure2 instanceof Closure ? 'YES' : 'NO') . "\n";
