<?php

declare(strict_types=1);

namespace Think\Runtime\Contract;

/**
 * Application interface for runtime-compatible applications.
 * 
 * This interface defines the contract for applications that can be
 * executed by the runtime system.
 */
interface ApplicationInterface
{
    /**
     * Handle a request and return a response.
     * 
     * @param mixed $request The request object (HTTP request, console input, etc.)
     * @return mixed The response object
     */
    public function handle($request);

    /**
     * Get the application environment.
     *
     * @return string The environment name (e.g., 'dev', 'prod', 'test')
     */
    public function getEnvironment(): string;

    /**
     * Check if the application is in debug mode.
     *
     * @return bool True if debug mode is enabled
     */
    public function isDebug(): bool;

    /**
     * Terminate the application.
     * 
     * This method is called after the response has been sent.
     *
     * @param mixed $request The request object
     * @param mixed $response The response object
     * @return void
     */
    public function terminate($request, $response): void;
}
