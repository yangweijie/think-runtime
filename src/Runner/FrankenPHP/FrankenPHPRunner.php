<?php

declare(strict_types=1);

namespace Think\Runtime\Runner\FrankenPHP;

use Think\Runtime\Contract\RunnerInterface;

/**
 * FrankenPHP runner for ThinkPHP applications.
 * 
 * Supports FrankenPHP worker mode with request looping and memory management.
 */
class FrankenPHPRunner implements RunnerInterface
{
    private object $application;
    private array $options;
    private int $loopMax;

    public function __construct(object $application, array $options = [])
    {
        $this->application = $application;
        $this->options = $options;
        $this->loopMax = $options['frankenphp_loop_max'] ?? 500;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): int
    {
        // Check if FrankenPHP functions are available
        if (!function_exists('frankenphp_handle_request')) {
            throw new \RuntimeException('FrankenPHP is not available. Make sure you are running under FrankenPHP server.');
        }

        // Prevent worker script termination when a client connection is interrupted
        if ($this->options['ignore_user_abort'] ?? true) {
            ignore_user_abort(true);
        }

        $xdebugConnectToClient = ($this->options['enable_xdebug'] ?? false) && function_exists('xdebug_connect_to_client');
        
        // Preserve server variables that are not request-specific
        $server = array_filter($_SERVER, static fn (string $key) => !str_starts_with($key, 'HTTP_'), ARRAY_FILTER_USE_KEY);
        $server['APP_RUNTIME_MODE'] = 'web=1&worker=1';

        $thinkRequest = null;
        $thinkResponse = null;

        // Request handler
        $handler = function () use ($server, &$thinkRequest, &$thinkResponse, $xdebugConnectToClient): void {
            // Connect to the Xdebug client if it's available
            if ($xdebugConnectToClient) {
                xdebug_connect_to_client();
            }

            // Merge the environment variables with the ones tied to the current request
            $_SERVER += $server;

            // Create ThinkPHP request from globals
            $thinkRequest = $this->createRequest();

            // Handle the request
            $thinkResponse = $this->application->handle($thinkRequest);

            // Send the response
            $this->sendResponse($thinkResponse);
        };

        $loops = 0;
        
        do {
            // Handle the request using FrankenPHP
            $ret = frankenphp_handle_request($handler);

            // Terminate the application if it supports termination
            if (method_exists($this->application, 'terminate') && $thinkRequest && $thinkResponse) {
                $this->application->terminate($thinkRequest, $thinkResponse);
            }

            // Collect garbage cycles to prevent memory leaks
            if ($this->options['gc_collect_cycles'] ?? true) {
                gc_collect_cycles();
            }

            // Reset request and response for next iteration
            $thinkRequest = null;
            $thinkResponse = null;

        } while ($ret && (-1 === $this->loopMax || ++$loops < $this->loopMax));

        return 0;
    }

    /**
     * Create request from globals.
     */
    protected function createRequest()
    {
        // Always use basic request data for compatibility
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'query' => $_GET,
            'body' => $_POST,
            'files' => $_FILES,
            'headers' => $this->getHeaders(),
            'server' => $_SERVER,
        ];
    }

    /**
     * Send response to client.
     */
    protected function sendResponse($response): void
    {
        if (is_object($response) && method_exists($response, 'send')) {
            $response->send();
        } elseif (is_array($response)) {
            header('Content-Type: application/json');
            echo json_encode($response);
        } elseif (is_string($response)) {
            echo $response;
        } else {
            echo (string) $response;
        }
    }

    /**
     * Get HTTP headers from $_SERVER.
     */
    protected function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
}
