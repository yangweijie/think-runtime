<?php

declare(strict_types=1);

namespace Think\Runtime\Runner\Workerman;

use Think\Runtime\Contract\RunnerInterface;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response as WorkermanResponse;

/**
 * Workerman runner for ThinkPHP applications.
 */
class WorkermanRunner implements RunnerInterface
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
        if (!class_exists('Workerman\\Worker')) {
            throw new \RuntimeException('Workerman is not installed. Run: composer require workerman/workerman');
        }

        $host = $this->options['host'] ?? '0.0.0.0';
        $port = $this->options['port'] ?? 8080;
        $workerCount = $this->options['worker_count'] ?? 4;
        $protocol = $this->options['protocol'] ?? 'http';

        // Create Workerman worker
        $worker = new Worker("{$protocol}://{$host}:{$port}");
        $worker->count = $workerCount;
        $worker->name = 'ThinkPHP-Workerman';

        // Set worker properties
        if (isset($this->options['ssl_context'])) {
            $worker->transport = 'ssl';
            $worker->contextOption = $this->options['ssl_context'];
        }

        // Handle worker start
        $worker->onWorkerStart = function ($worker) {
            echo "ThinkPHP Workerman server started on {$worker->getSocketName()}\n";
            
            // Initialize application for this worker
            if (method_exists($this->application, 'initialize')) {
                $this->application->initialize();
            }
        };

        // Handle HTTP requests
        $worker->onMessage = function (TcpConnection $connection, WorkermanRequest $request) {
            try {
                // Convert Workerman request to ThinkPHP request
                $thinkRequest = $this->convertRequest($request);
                
                // Handle request with ThinkPHP application
                $response = $this->application->handle($thinkRequest);
                
                // Convert ThinkPHP response to Workerman response
                $workermanResponse = $this->convertResponse($response);
                
                // Send response
                $connection->send($workermanResponse);
                
                // Terminate application
                if (method_exists($this->application, 'terminate')) {
                    $this->application->terminate($thinkRequest, $response);
                }
                
            } catch (\Throwable $e) {
                $this->handleException($connection, $e);
            }
        };

        // Handle worker stop
        $worker->onWorkerStop = function ($worker) {
            echo "ThinkPHP Workerman worker stopped\n";
        };

        // Start worker
        Worker::runAll();

        return 0;
    }

    /**
     * Convert Workerman request to ThinkPHP request.
     */
    protected function convertRequest(WorkermanRequest $request)
    {
        // Set global variables for ThinkPHP compatibility
        $_SERVER['REQUEST_METHOD'] = $request->method();
        $_SERVER['REQUEST_URI'] = $request->uri();
        $_SERVER['QUERY_STRING'] = $request->queryString();
        $_SERVER['HTTP_HOST'] = $request->host();
        $_SERVER['SERVER_NAME'] = $request->host();
        $_SERVER['SERVER_PORT'] = parse_url($request->uri(), PHP_URL_PORT) ?: 80;
        $_SERVER['HTTPS'] = $request->header('x-forwarded-proto') === 'https' ? 'on' : 'off';
        
        // Set headers
        foreach ($request->header() as $name => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $_SERVER[$key] = $value;
        }

        // Set request data
        $_GET = $request->get();
        $_POST = $request->post();
        $_FILES = $request->file();
        $_COOKIE = $request->cookie();

        // Create ThinkPHP request
        if (class_exists('think\\Request')) {
            return \think\Request::createFromGlobals();
        }

        return $request;
    }

    /**
     * Convert ThinkPHP response to Workerman response.
     */
    protected function convertResponse($response): WorkermanResponse
    {
        $workermanResponse = new WorkermanResponse();

        if (is_object($response)) {
            // Handle ThinkPHP Response object
            if (method_exists($response, 'getStatusCode')) {
                $workermanResponse->withStatus($response->getStatusCode());
            }
            
            if (method_exists($response, 'getHeaders')) {
                foreach ($response->getHeaders() as $name => $values) {
                    $workermanResponse->withHeader($name, implode(', ', $values));
                }
            }
            
            if (method_exists($response, 'getContent')) {
                $workermanResponse->withBody($response->getContent());
            } else {
                $workermanResponse->withBody((string) $response);
            }
        } elseif (is_array($response)) {
            // Handle array response (JSON)
            $workermanResponse->withHeader('Content-Type', 'application/json');
            $workermanResponse->withBody(json_encode($response));
        } else {
            // Handle string response
            $workermanResponse->withBody((string) $response);
        }

        return $workermanResponse;
    }

    /**
     * Handle exceptions.
     */
    protected function handleException(TcpConnection $connection, \Throwable $e): void
    {
        $response = new WorkermanResponse(500);
        
        if ($this->options['debug'] ?? false) {
            $body = "Error: " . $e->getMessage() . "\n";
            $body .= "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            $body .= "Trace:\n" . $e->getTraceAsString();
        } else {
            $body = "Internal Server Error";
        }
        
        $response->withBody($body);
        $connection->send($response);
        
        // Log error
        error_log("Workerman Error: " . $e->getMessage());
    }
}
