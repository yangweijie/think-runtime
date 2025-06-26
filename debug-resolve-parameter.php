<?php

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Resolver\GenericResolver;

echo "🔍 Debugging resolveParameter Method...\n\n";

$callable = function(string $optional = 'default') { return $optional; };

$testResolver = new class($callable) extends GenericResolver {
    public function debugResolveParameter() {
        $reflection = $this->getReflection($this->callable);
        $parameter = $reflection->getParameters()[0];
        
        echo "Parameter: " . $parameter->getName() . "\n";
        echo "Type: " . ($parameter->getType() ? $parameter->getType()->getName() : 'none') . "\n";
        echo "Has default: " . ($parameter->isDefaultValueAvailable() ? 'YES' : 'NO') . "\n";
        
        if ($parameter->isDefaultValueAvailable()) {
            echo "Default value: " . var_export($parameter->getDefaultValue(), true) . "\n";
        }
        
        echo "\nStep-by-step resolution:\n";
        
        // Step 1: Check default value
        echo "1. Checking default value...\n";
        if ($parameter->isDefaultValueAvailable()) {
            $defaultValue = $parameter->getDefaultValue();
            echo "   Found default: " . var_export($defaultValue, true) . "\n";
            echo "   Returning default value\n";
            return $defaultValue;
        }
        echo "   No default value found\n";
        
        // This should not be reached for our test case
        echo "2. This should not be reached!\n";
        return 'ERROR';
    }
    
    public function callResolveParameter() {
        $reflection = $this->getReflection($this->callable);
        $parameter = $reflection->getParameters()[0];
        return $this->resolveParameter($parameter);
    }
};

echo "Direct debug method result:\n";
$debugResult = $testResolver->debugResolveParameter();
echo "Result: " . var_export($debugResult, true) . "\n\n";

echo "Actual resolveParameter method result:\n";
$actualResult = $testResolver->callResolveParameter();
echo "Result: " . var_export($actualResult, true) . "\n";
