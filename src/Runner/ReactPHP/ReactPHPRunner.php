<?php

declare(strict_types=1);

namespace Think\Runtime\Runner\ReactPHP;

use Think\Runtime\Contract\RunnerInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response as ReactResponse;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ReactPHP runner for ThinkPHP applications.
 */
class ReactPHPRunner implements RunnerInterface
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
        if (!class_exists('React\\Http\\HttpServer')) {
            throw new \RuntimeException('ReactPHP HTTP is not installed. Run: composer require react/http react/socket');
        }

        $host = $this->options['host'] ?? '127.0.0.1';
        $port = $this->options['port'] ?? 8080;
        $maxRequestSize = $this->options['max_request_size'] ?? 1024 * 1024; // 1MB
        $maxConcurrentRequests = $this->options['max_concurrent_requests'] ?? 100;

        // Create event loop
        $loop = Loop::get();

        // Create HTTP server
        $server = new HttpServer(
            $loop,
            function (ServerRequestInterface $request) {
                return $this->handleRequest($request);
            }
        );

        // Configure server limits
        $server->on('error', function (\Exception $e) {
            echo "Server error: " . $e->getMessage() . "\n";
        });

        // Create socket server
        $socket = new SocketServer("{$host}:{$port}", $loop);

        // Start server
        $server->listen($socket);

        echo "ThinkPHP ReactPHP server started on http://{$host}:{$port}\n";
        echo "Press Ctrl+C to stop the server\n";

        // Handle shutdown signals
        $this->setupSignalHandlers($loop, $socket);

        // Run the event loop
        $loop->run();

        return 0;
    }

    /**
     * Handle HTTP request.
     */
    protected function handleRequest(ServerRequestInterface $request): ReactResponse
    {
        try {
            // Convert PSR-7 request to ThinkPHP request
            $thinkRequest = $this->convertRequest($request);
            
            // Handle request with ThinkPHP application
            $response = $this->application->handle($thinkRequest);
            
            // Convert ThinkPHP response to PSR-7 response
            $reactResponse = $this->convertResponse($response);
            
            // Terminate application
            if (method_exists($this->application, 'terminate')) {
                $this->application->terminate($thinkRequest, $response);
            }
            
            return $reactResponse;
            
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Convert PSR-7 request to ThinkPHP request.
     */
    protected function convertRequest(ServerRequestInterface $request)
    {
        $uri = $request->getUri();
        
        // Set global variables for ThinkPHP compatibility
        $_SERVER['REQUEST_METHOD'] = $request->getMethod();
        $_SERVER['REQUEST_URI'] = $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : '');
        $_SERVER['QUERY_STRING'] = $uri->getQuery();
        $_SERVER['HTTP_HOST'] = $uri->getHost();
        $_SERVER['SERVER_NAME'] = $uri->getHost();
        $_SERVER['SERVER_PORT'] = $uri->getPort() ?: ($uri->getScheme() === 'https' ? 443 : 80);
        $_SERVER['HTTPS'] = $uri->getScheme() === 'https' ? 'on' : 'off';
        $_SERVER['CONTENT_TYPE'] = $request->getHeaderLine('Content-Type');
        $_SERVER['CONTENT_LENGTH'] = $request->getHeaderLine('Content-Length');
        
        // Set headers
        foreach ($request->getHeaders() as $name => $values) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $_SERVER[$key] = implode(', ', $values);
        }

        // Parse query parameters
        parse_str($uri->getQuery(), $_GET);
        
        // Parse body for POST requests
        $_POST = [];
        $_FILES = [];
        
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            $contentType = $request->getHeaderLine('Content-Type');
            $body = (string) $request->getBody();
            
            if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($body, $_POST);
            } elseif (strpos($contentType, 'application/json') !== false) {
                $_POST = json_decode($body, true) ?: [];
            }
        }

        // Handle cookies
        $_COOKIE = [];
        if ($request->hasHeader('Cookie')) {
            $cookies = $request->getHeaderLine('Cookie');
            foreach (explode(';', $cookies) as $cookie) {
                $parts = explode('=', trim($cookie), 2);
                if (count($parts) === 2) {
                    $_COOKIE[trim($parts[0])] = trim($parts[1]);
                }
            }
        }

        // Create ThinkPHP request
        if (class_exists('think\\Request')) {
            return \think\Request::createFromGlobals();
        }

        return $request;
    }

    /**
     * Convert ThinkPHP response to PSR-7 response.
     */
    protected function convertResponse($response): ReactResponse
    {
        $status = 200;
        $headers = [];
        $body = '';

        if (is_object($response)) {
            // Handle ThinkPHP Response object
            if (method_exists($response, 'getStatusCode')) {
                $status = $response->getStatusCode();
            }
            
            if (method_exists($response, 'getHeaders')) {
                $headers = $response->getHeaders();
            }
            
            if (method_exists($response, 'getContent')) {
                $body = $response->getContent();
            } else {
                $body = (string) $response;
            }
        } elseif (is_array($response)) {
            // Handle array response (JSON)
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($response);
        } else {
            // Handle string response
            $body = (string) $response;
        }

        return new ReactResponse($status, $headers, $body);
    }

    /**
     * Handle exceptions.
     */
    protected function handleException(\Throwable $e): ReactResponse
    {
        $status = 500;
        $headers = ['Content-Type' => 'text/plain'];
        
        if ($this->options['debug'] ?? false) {
            $body = "Error: " . $e->getMessage() . "\n";
            $body .= "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            $body .= "Trace:\n" . $e->getTraceAsString();
        } else {
            $body = "Internal Server Error";
        }
        
        // Log error
        error_log("ReactPHP Error: " . $e->getMessage());
        
        return new ReactResponse($status, $headers, $body);
    }

    /**
     * Setup signal handlers for graceful shutdown.
     */
    protected function setupSignalHandlers($loop, $socket): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () use ($loop, $socket) {
                echo "\nReceived SIGTERM, shutting down gracefully...\n";
                $socket->close();
                $loop->stop();
            });
            
            pcntl_signal(SIGINT, function () use ($loop, $socket) {
                echo "\nReceived SIGINT, shutting down gracefully...\n";
                $socket->close();
                $loop->stop();
            });
        }
    }
}
