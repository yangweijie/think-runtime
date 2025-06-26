<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "🔍 Debugging Closure Detection...\n\n";

$callable = function(string $optional = 'default') { return $optional; };

echo "Callable analysis:\n";
echo "  Type: " . gettype($callable) . "\n";
echo "  Class: " . get_class($callable) . "\n";
echo "  instanceof Closure: " . ($callable instanceof Closure ? 'YES' : 'NO') . "\n";
echo "  is_object: " . (is_object($callable) ? 'YES' : 'NO') . "\n";
echo "  has __invoke: " . (method_exists($callable, '__invoke') ? 'YES' : 'NO') . "\n";

echo "\nTesting reflection logic:\n";
if (is_array($callable)) {
    echo "  Would use: ReflectionMethod (array)\n";
} elseif ($callable instanceof Closure) {
    echo "  Would use: ReflectionFunction (Closure)\n";
} elseif (is_object($callable) && method_exists($callable, '__invoke')) {
    echo "  Would use: ReflectionMethod (__invoke)\n";
} else {
    echo "  Would use: ReflectionFunction (default)\n";
}
