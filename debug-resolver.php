<?php

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Resolver\GenericResolver;

echo "🔍 Debugging Resolver Default Values...\n\n";

$callable = function(string $optional = 'default') { return $optional; };
$resolver = new GenericResolver($callable);

// Get reflection to debug
$reflection = new ReflectionFunction($callable);
$parameters = $reflection->getParameters();
$parameter = $parameters[0];

echo "Parameter details:\n";
echo "  Name: " . $parameter->getName() . "\n";
echo "  Type: " . ($parameter->getType() ? $parameter->getType()->getName() : 'none') . "\n";
echo "  Has default: " . ($parameter->isDefaultValueAvailable() ? 'YES' : 'NO') . "\n";
if ($parameter->isDefaultValueAvailable()) {
    echo "  Default value: " . var_export($parameter->getDefaultValue(), true) . "\n";
}
echo "  Is nullable: " . ($parameter->allowsNull() ? 'YES' : 'NO') . "\n";
echo "  Type is builtin: " . ($parameter->getType() && $parameter->getType()->isBuiltin() ? 'YES' : 'NO') . "\n";

echo "\nTesting resolveParameter method:\n";

// Create a test resolver to access protected method
$testResolver = new class($callable) extends GenericResolver {
    public function testResolveParameter($parameter) {
        return $this->resolveParameter($parameter);
    }
};

$result = $testResolver->testResolveParameter($parameter);
echo "  Resolved value: " . var_export($result, true) . "\n";
echo "  Expected: 'default'\n";
echo "  Match: " . ($result === 'default' ? 'YES' : 'NO') . "\n";
