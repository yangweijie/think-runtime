<?php

declare(strict_types=1);

namespace Think\Runtime\Resolver;

use Think\Runtime\Contract\ResolverInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Closure;

/**
 * Generic resolver for application callables.
 * 
 * Analyzes callable signatures and provides appropriate arguments.
 */
class GenericResolver implements ResolverInterface
{
    private $callable;
    private array $options;

    public function __construct(callable $callable, array $options = [])
    {
        $this->callable = $callable;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(): array
    {
        $reflection = $this->getReflection($this->callable);
        $parameters = $reflection->getParameters();
        $arguments = [];

        foreach ($parameters as $parameter) {
            $arguments[] = $this->resolveParameter($parameter);
        }

        return [$this->callable, $arguments];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(callable $callable): bool
    {
        return true; // Generic resolver supports all callables
    }

    /**
     * Resolve a single parameter.
     */
    protected function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        $name = $parameter->getName();

        // Use default value if available (HIGHEST priority - check first!)
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Handle typed parameters
        if ($type && !$type->isBuiltin()) {
            $typeName = $type->getName();

            // Try to resolve by type
            $resolved = $this->resolveByType($typeName);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Handle named parameters
        $resolved = $this->resolveByName($name);
        if ($resolved !== null) {
            return $resolved;
        }

        // Handle array types
        if ($type && $type->isBuiltin() && $type->getName() === 'array') {
            return $this->resolveArrayParameter($name);
        }

        // Allow null if parameter is nullable
        if ($parameter->allowsNull()) {
            return null;
        }

        // For built-in types, try to provide sensible defaults
        if ($type && $type->isBuiltin()) {
            return match ($type->getName()) {
                'string' => '',
                'int' => 0,
                'float' => 0.0,
                'bool' => false,
                'array' => [],
                default => null,
            };
        }

        throw new \InvalidArgumentException(
            sprintf('Cannot resolve parameter "%s" of type "%s"', $name, $type?->getName() ?? 'mixed')
        );
    }

    /**
     * Resolve parameter by type.
     */
    protected function resolveByType(string $typeName): mixed
    {
        return match ($typeName) {
            'think\\Request' => $this->createThinkRequest(),
            'think\\Console\\Input' => $this->createConsoleInput(),
            'think\\Console\\Output' => $this->createConsoleOutput(),
            default => null,
        };
    }

    /**
     * Resolve parameter by name.
     */
    protected function resolveByName(string $name): mixed
    {
        return match ($name) {
            'context' => $this->getContext(),
            'argv' => $_SERVER['argv'] ?? [],
            'request' => $this->getRequestArray(),
            default => null,
        };
    }

    /**
     * Resolve array parameter.
     */
    protected function resolveArrayParameter(string $name): array
    {
        return match ($name) {
            'context' => $this->getContext(),
            'argv' => $_SERVER['argv'] ?? [],
            'request' => $this->getRequestArray(),
            default => [],
        };
    }

    /**
     * Get context array (combination of $_SERVER and $_ENV).
     */
    protected function getContext(): array
    {
        return $_SERVER + $_ENV;
    }

    /**
     * Get request array.
     */
    protected function getRequestArray(): array
    {
        return [
            'query' => $_GET,
            'body' => $_POST,
            'files' => $_FILES,
            'session' => $_SESSION ?? [],
        ];
    }

    /**
     * Create ThinkPHP Request instance.
     */
    protected function createThinkRequest(): mixed
    {
        if (class_exists('think\\Request')) {
            return \think\Request::createFromGlobals();
        }
        return null;
    }

    /**
     * Create Console Input instance.
     */
    protected function createConsoleInput(): mixed
    {
        if (class_exists('think\\Console\\Input')) {
            return new \think\Console\Input();
        }
        return null;
    }

    /**
     * Create Console Output instance.
     */
    protected function createConsoleOutput(): mixed
    {
        if (class_exists('think\\Console\\Output')) {
            return new \think\Console\Output();
        }
        return null;
    }

    /**
     * Get reflection for callable.
     */
    protected function getReflection(callable $callable): ReflectionFunction|ReflectionMethod
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        // For closures, always use ReflectionFunction to preserve default values
        if ($callable instanceof Closure) {
            return new ReflectionFunction($callable);
        }

        if (is_object($callable) && method_exists($callable, '__invoke')) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction($callable);
    }
}
