<?php

declare(strict_types=1);

namespace Think\Runtime\Contract;

/**
 * Resolver interface for application callables.
 * 
 * A Resolver analyzes a callable's signature and provides the appropriate
 * arguments when the callable needs to be invoked.
 */
interface ResolverInterface
{
    /**
     * Resolve the callable and its arguments.
     * 
     * Returns an array where:
     * - Index 0: The callable (possibly decorated)
     * - Index 1: Array of resolved arguments for the callable
     *
     * @return array{0: callable, 1: array<int, mixed>}
     */
    public function resolve(): array;

    /**
     * Check if the resolver can handle the given callable.
     *
     * @param callable $callable The callable to check
     * @return bool True if this resolver can handle the callable
     */
    public function supports(callable $callable): bool;
}
