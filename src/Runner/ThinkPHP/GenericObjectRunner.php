<?php

declare(strict_types=1);

namespace Think\Runtime\Runner\ThinkPHP;

use Think\Runtime\Contract\RunnerInterface;

/**
 * Runner for generic objects with handle method.
 */
class GenericObjectRunner implements RunnerInterface
{
    private object $application;
    private array $options;

    public function __construct(object $application, array $options = [])
    {
        $this->application = $application;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): int
    {
        try {
            // Try to handle a request if the object has a handle method
            if (method_exists($this->application, 'handle')) {
                $request = $this->createRequest();
                $response = $this->application->handle($request);
                $this->sendResponse($response);
                
                // Terminate if possible
                if (method_exists($this->application, 'terminate')) {
                    $this->application->terminate($request, $response);
                }
                
                return 0;
            }
            
            // If no handle method, just return success
            return 0;
            
        } catch (\Throwable $e) {
            $this->handleException($e);
            return 1;
        }
    }

    /**
     * Create request from globals.
     */
    protected function createRequest()
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'query' => $_GET,
            'body' => $_POST,
            'files' => $_FILES,
            'headers' => $this->getHeaders(),
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
     * Handle exceptions.
     */
    protected function handleException(\Throwable $e): void
    {
        if ($this->options['debug'] ?? false) {
            echo "Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo "Trace:\n" . $e->getTraceAsString() . "\n";
        } else {
            echo "Internal Server Error\n";
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
