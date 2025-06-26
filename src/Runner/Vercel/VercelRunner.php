<?php

declare(strict_types=1);

namespace Think\Runtime\Runner\Vercel;

use Think\Runtime\Contract\RunnerInterface;

/**
 * Vercel runner for ThinkPHP applications.
 * 
 * Supports Vercel serverless functions with optimized cold start and request handling.
 */
class VercelRunner implements RunnerInterface
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
            // Set up error handling for serverless environment
            $this->setupErrorHandling();

            // Handle CORS preflight requests
            if ($this->isPreflightRequest()) {
                $this->handlePreflightRequest();
                return 0;
            }

            // Create request from serverless environment
            $request = $this->createRequest();

            // Handle the request
            $response = $this->application->handle($request);

            // Send response with appropriate headers
            $this->sendResponse($response);

            // Terminate the application if it supports termination
            if (method_exists($this->application, 'terminate')) {
                $this->application->terminate($request, $response);
            }

            return 0;

        } catch (\Throwable $e) {
            $this->handleException($e);
            return 1;
        }
    }

    /**
     * Set up error handling for serverless environment.
     */
    protected function setupErrorHandling(): void
    {
        // Set memory limit if specified
        if (isset($this->options['function_memory_size'])) {
            ini_set('memory_limit', $this->options['function_memory_size'] . 'M');
        }

        // Set execution time limit
        if (isset($this->options['max_execution_time'])) {
            set_time_limit($this->options['max_execution_time']);
        }

        // Configure error reporting for serverless
        if ($this->options['vercel_env'] === 'production') {
            error_reporting(E_ERROR | E_PARSE);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }
    }

    /**
     * Check if this is a CORS preflight request.
     */
    protected function isPreflightRequest(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'OPTIONS' && 
               isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
    }

    /**
     * Handle CORS preflight request.
     */
    protected function handlePreflightRequest(): void
    {
        if ($this->options['enable_cors'] ?? true) {
            $this->setCorsHeaders();
        }
        
        http_response_code(200);
        echo '';
    }

    /**
     * Set CORS headers.
     */
    protected function setCorsHeaders(): void
    {
        $origins = $this->options['cors_origins'] ?? '*';
        $methods = $this->options['cors_methods'] ?? 'GET,POST,PUT,DELETE,OPTIONS';
        $headers = $this->options['cors_headers'] ?? 'Content-Type,Authorization';

        header("Access-Control-Allow-Origin: $origins");
        header("Access-Control-Allow-Methods: $methods");
        header("Access-Control-Allow-Headers: $headers");
        header('Access-Control-Max-Age: 86400');
        
        // Handle credentials
        if ($origins !== '*') {
            header('Access-Control-Allow-Credentials: true');
        }
    }

    /**
     * Create request from serverless environment.
     */
    protected function createRequest(): array
    {
        // Parse the request body for POST/PUT requests
        $body = [];
        $rawBody = file_get_contents('php://input');
        
        if (!empty($rawBody)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $body = json_decode($rawBody, true) ?? [];
            } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($rawBody, $body);
            }
        }

        // Merge with $_POST for form data
        $body = array_merge($_POST, $body);

        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'query' => $_GET,
            'body' => $body,
            'files' => $_FILES,
            'headers' => $this->getHeaders(),
            'server' => $_SERVER,
            'cookies' => $_COOKIE,
            'raw_content' => $rawBody,
            'vercel_context' => $this->getVercelContext(),
        ];
    }

    /**
     * Get Vercel-specific context information.
     */
    protected function getVercelContext(): array
    {
        return [
            'environment' => $this->options['vercel_env'] ?? 'development',
            'region' => $this->options['vercel_region'] ?? 'iad1',
            'function_name' => $this->options['function_name'] ?? 'index',
            'function_version' => $this->options['function_version'] ?? '$LATEST',
            'deployment_url' => $this->options['vercel_url'] ?? null,
            'is_cold_start' => !isset($GLOBALS['_vercel_warm']),
        ];
    }

    /**
     * Send response with appropriate headers.
     */
    protected function sendResponse($response): void
    {
        // Set CORS headers if enabled
        if ($this->options['enable_cors'] ?? true) {
            $this->setCorsHeaders();
        }

        // Set caching headers if enabled
        if ($this->options['enable_static_cache'] ?? true) {
            $maxAge = $this->options['cache_control_max_age'] ?? 3600;
            header("Cache-Control: public, max-age=$maxAge");
        }

        // Handle different response types
        if (is_object($response)) {
            $this->sendObjectResponse($response);
        } elseif (is_array($response)) {
            $this->sendJsonResponse($response);
        } else {
            $this->sendStringResponse($response);
        }

        // Mark as warm for subsequent requests
        $GLOBALS['_vercel_warm'] = true;
    }

    /**
     * Send object response.
     */
    protected function sendObjectResponse($response): void
    {
        if (method_exists($response, 'getStatusCode')) {
            http_response_code($response->getStatusCode());
        }
        
        if (method_exists($response, 'getHeaders')) {
            foreach ($response->getHeaders() as $name => $value) {
                header("$name: $value");
            }
        }
        
        if (method_exists($response, 'getContent')) {
            echo $response->getContent();
        } else {
            echo (string) $response;
        }
    }

    /**
     * Send JSON response.
     */
    protected function sendJsonResponse(array $response): void
    {
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Send string response.
     */
    protected function sendStringResponse($response): void
    {
        echo (string) $response;
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

    /**
     * Handle exceptions in serverless environment.
     */
    protected function handleException(\Throwable $e): void
    {
        // Log the error if logging is enabled
        if ($this->options['enable_logging'] ?? true) {
            error_log("Vercel Function Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }

        // Set error response
        http_response_code(500);
        
        if (($this->options['vercel_env'] ?? 'development') !== 'production') {
            // Development mode - show detailed error
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            // Production mode - show generic error
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => 'An unexpected error occurred',
            ]);
        }
    }
}
