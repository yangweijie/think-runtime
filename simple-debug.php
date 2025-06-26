<?php

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Resolver\GenericResolver;

echo "🔍 Simple Debug...\n\n";

$callable = function(string $optional = 'default') { return $optional; };
$resolver = new GenericResolver($callable);

// Let's manually trace through the resolveParameter logic
$reflection = new ReflectionFunction($callable);
$parameter = $reflection->getParameters()[0];

echo "Parameter analysis:\n";
echo "  Name: " . $parameter->getName() . "\n";
echo "  Type: " . $parameter->getType()->getName() . "\n";
echo "  Has default: " . ($parameter->isDefaultValueAvailable() ? 'YES' : 'NO') . "\n";
echo "  Default value: " . var_export($parameter->getDefaultValue(), true) . "\n";
echo "  Type is builtin: " . ($parameter->getType()->isBuiltin() ? 'YES' : 'NO') . "\n";

echo "\nManual resolution logic:\n";

// Step 1: Check default value (should be first priority)
if ($parameter->isDefaultValueAvailable()) {
    echo "1. ✅ Has default value: " . var_export($parameter->getDefaultValue(), true) . "\n";
    echo "   Should return this value!\n";
} else {
    echo "1. ❌ No default value\n";
}

// Let's see what the actual resolver returns
[$resolvedCallable, $arguments] = $resolver->resolve();
echo "\nActual resolver result: " . var_export($arguments[0], true) . "\n";

// Let's check if there's a bug in our current code
echo "\nChecking current resolveParameter implementation...\n";

// Create a simple test to see what's happening
class DebugResolver extends GenericResolver {
    public function debugResolve() {
        $reflection = $this->getReflection($this->callable);
        $parameters = $reflection->getParameters();
        $parameter = $parameters[0];
        
        echo "In resolveParameter:\n";
        
        // Check our current logic order
        if ($parameter->isDefaultValueAvailable()) {
            echo "  Default available: " . var_export($parameter->getDefaultValue(), true) . "\n";
            return $parameter->getDefaultValue();
        }
        
        echo "  No default found (this shouldn't happen)\n";
        return 'ERROR';
    }
}

$debugResolver = new DebugResolver($callable);
$debugResult = $debugResolver->debugResolve();
echo "Debug result: " . var_export($debugResult, true) . "\n";
