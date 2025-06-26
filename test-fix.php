<?php

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Resolver\GenericResolver;

echo "🧪 Testing Fix...\n\n";

$callable = function(string $optional = 'default') { return $optional; };
$resolver = new GenericResolver($callable);

echo "Testing resolver...\n";
[$resolvedCallable, $arguments] = $resolver->resolve();

echo "Arguments: " . var_export($arguments, true) . "\n";
echo "First argument: " . var_export($arguments[0], true) . "\n";
echo "Expected: 'default'\n";
echo "Match: " . ($arguments[0] === 'default' ? 'YES' : 'NO') . "\n";

// Let's also test the reflection directly
echo "\nDirect reflection test:\n";
$reflection = new ReflectionFunction($callable);
$parameter = $reflection->getParameters()[0];
echo "Has default: " . ($parameter->isDefaultValueAvailable() ? 'YES' : 'NO') . "\n";
echo "Default value: " . var_export($parameter->getDefaultValue(), true) . "\n";

// Test with a fresh instance
echo "\nFresh resolver instance:\n";
$freshResolver = new GenericResolver($callable);
[$freshCallable, $freshArguments] = $freshResolver->resolve();
echo "Fresh arguments: " . var_export($freshArguments, true) . "\n";
