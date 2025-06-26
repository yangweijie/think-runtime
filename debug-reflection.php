<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "🔍 Debugging Reflection Types...\n\n";

$callable = function(string $optional = 'default') { return $optional; };

echo "1. Testing ReflectionFunction:\n";
$reflectionFunction = new ReflectionFunction($callable);
$paramFunction = $reflectionFunction->getParameters()[0];
echo "   Has default: " . ($paramFunction->isDefaultValueAvailable() ? 'YES' : 'NO') . "\n";
if ($paramFunction->isDefaultValueAvailable()) {
    echo "   Default value: " . var_export($paramFunction->getDefaultValue(), true) . "\n";
}

echo "\n2. Testing ReflectionMethod (__invoke):\n";
$reflectionMethod = new ReflectionMethod($callable, '__invoke');
$paramMethod = $reflectionMethod->getParameters()[0];
echo "   Has default: " . ($paramMethod->isDefaultValueAvailable() ? 'YES' : 'NO') . "\n";
if ($paramMethod->isDefaultValueAvailable()) {
    echo "   Default value: " . var_export($paramMethod->getDefaultValue(), true) . "\n";
}

echo "\n3. Callable type check:\n";
echo "   is_object: " . (is_object($callable) ? 'YES' : 'NO') . "\n";
echo "   has __invoke: " . (method_exists($callable, '__invoke') ? 'YES' : 'NO') . "\n";
echo "   is_array: " . (is_array($callable) ? 'YES' : 'NO') . "\n";

echo "\n4. Testing getReflection logic:\n";
if (is_array($callable)) {
    echo "   Would use ReflectionMethod (array)\n";
} elseif (is_object($callable) && method_exists($callable, '__invoke')) {
    echo "   Would use ReflectionMethod (__invoke)\n";
} else {
    echo "   Would use ReflectionFunction\n";
}
