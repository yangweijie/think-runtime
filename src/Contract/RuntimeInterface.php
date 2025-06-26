<?php

declare(strict_types=1);

namespace Think\Runtime\Contract;

/**
 * Runtime interface for ThinkPHP applications.
 * 
 * A Runtime is responsible for:
 * 1. Resolving arguments for the application callable
 * 2. Creating appropriate runners for different application types
 * 3. Managing the application lifecycle
 */
interface RuntimeInterface
{
    /**
     * Get a resolver for the given callable.
     * 
     * The resolver is responsible for analyzing the callable signature
     * and providing the appropriate arguments when the callable is invoked.
     *
     * @param callable $callable The application callable
     * @return ResolverInterface The resolver for this callable
     */
    public function getResolver(callable $callable): ResolverInterface;

    /**
     * Get a runner for the given application.
     * 
     * The runner is responsible for executing the application in the
     * appropriate runtime environment (e.g., HTTP server, console, etc.).
     *
     * @param object|null $application The application instance
     * @return RunnerInterface The runner for this application
     */
    public function getRunner(?object $application): RunnerInterface;

    /**
     * Get runtime options.
     *
     * @return array<string, mixed> Runtime configuration options
     */
    public function getOptions(): array;

    /**
     * Set runtime options.
     *
     * @param array<string, mixed> $options Runtime configuration options
     * @return static
     */
    public function withOptions(array $options): static;
}
