<?php

declare(strict_types=1);

namespace Think\Runtime\Runner\Swoole;

use Think\Runtime\Contract\RunnerInterface;
// Import Swoole classes only if available
if (extension_loaded('swoole')) {
    class_alias('Swoole\Http\Server', 'SwooleHttpServer');
    class_alias('Swoole\Http\Request', 'SwooleHttpRequest');
    class_alias('Swoole\Http\Response', 'SwooleHttpResponse');
} else {
    // Create dummy classes for testing when Swoole is not available
    class SwooleHttpServer {}
    class SwooleHttpRequest {}
    class SwooleHttpResponse {}
}

/**
 * Swoole runner for ThinkPHP applications.
 * 
 * Supports Swoole HTTP server with coroutine and async I/O.
 */
class SwooleRunner implements RunnerInterface
{
    private object $application;
    private array $options;
    private $server = null;

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
        // Check if Swoole extension is available
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is not available. Please install swoole extension.');
        }

        // Create Swoole HTTP server
        $serverClass = extension_loaded('swoole') ? 'Swoole\Http\Server' : 'SwooleHttpServer';
        $this->server = new $serverClass(
            $this->options['host'] ?? '0.0.0.0',
            $this->options['port'] ?? 9501,
            $this->options['mode'] ?? (defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 3),
            $this->options['sock_type'] ?? (defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 1)
        );

        // Configure server
        $this->configureServer();

        // Set event handlers
        $this->setEventHandlers();

        // Start server
        echo "Swoole HTTP server starting on {$this->options['host']}:{$this->options['port']}\n";
        
        $this->server->start();

        return 0;
    }

    /**
     * Configure Swoole server settings.
     */
    protected function configureServer(): void
    {
        $defaultWorkerNum = function_exists('swoole_cpu_num') ? swoole_cpu_num() : 4;
        $swooleLogInfo = defined('SWOOLE_LOG_INFO') ? SWOOLE_LOG_INFO : 3;

        $settings = [
            'worker_num' => $this->options['worker_num'] ?? $defaultWorkerNum,
            'task_worker_num' => $this->options['task_worker_num'] ?? 0,
            'max_request' => $this->options['max_request'] ?? 10000,
            'max_conn' => $this->options['max_conn'] ?? 10000,
            'package_max_length' => $this->options['package_max_length'] ?? 2 * 1024 * 1024,
            'buffer_output_size' => $this->options['buffer_output_size'] ?? 2 * 1024 * 1024,
            'socket_buffer_size' => $this->options['socket_buffer_size'] ?? 2 * 1024 * 1024,
            'heartbeat_check_interval' => $this->options['heartbeat_check_interval'] ?? 60,
            'heartbeat_idle_time' => $this->options['heartbeat_idle_time'] ?? 600,
            'log_file' => $this->options['log_file'] ?? '/tmp/swoole.log',
            'log_level' => $this->options['log_level'] ?? $swooleLogInfo,
            'daemonize' => $this->options['daemonize'] ?? false,
            'pid_file' => $this->options['pid_file'] ?? '/tmp/swoole.pid',
        ];

        // Enable coroutine
        if ($this->options['enable_coroutine'] ?? true) {
            $settings['enable_coroutine'] = true;
        }

        // Enable static file handler
        if ($this->options['enable_static_handler'] ?? true) {
            $settings['enable_static_handler'] = true;
            $settings['document_root'] = $this->options['document_root'] ?? getcwd() . '/public';
        }

        $this->server->set($settings);
    }

    /**
     * Set Swoole server event handlers.
     */
    protected function setEventHandlers(): void
    {
        // Worker start event
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);

        // Request event
        $this->server->on('Request', [$this, 'onRequest']);

        // Task event (if task workers are enabled)
        if (($this->options['task_worker_num'] ?? 0) > 0) {
            $this->server->on('Task', [$this, 'onTask']);
            $this->server->on('Finish', [$this, 'onFinish']);
        }

        // Worker stop event
        $this->server->on('WorkerStop', [$this, 'onWorkerStop']);
    }

    /**
     * Handle worker start event.
     */
    public function onWorkerStart($server, int $workerId): void
    {
        // Set process title
        if ($workerId >= $server->setting['worker_num']) {
            swoole_set_process_name("swoole_task_worker_{$workerId}");
        } else {
            swoole_set_process_name("swoole_worker_{$workerId}");
        }

        // Initialize application for this worker
        if ($this->options['debug'] ?? false) {
            echo "Worker #{$workerId} started\n";
        }
    }

    /**
     * Handle HTTP request.
     */
    public function onRequest($request, $response): void
    {
        try {
            // Create ThinkPHP request from Swoole request
            $thinkRequest = $this->createRequest($request);

            // Handle the request
            $thinkResponse = $this->application->handle($thinkRequest);

            // Send response
            $this->sendResponse($response, $thinkResponse);

            // Terminate the application if it supports termination
            if (method_exists($this->application, 'terminate')) {
                $this->application->terminate($thinkRequest, $thinkResponse);
            }

        } catch (\Throwable $e) {
            $this->handleException($response, $e);
        }
    }

    /**
     * Handle task (if task workers are enabled).
     */
    public function onTask($server, int $taskId, int $srcWorkerId, $data): void
    {
        // Handle background tasks
        if ($this->options['debug'] ?? false) {
            echo "Task #{$taskId} received from worker #{$srcWorkerId}\n";
        }
    }

    /**
     * Handle task finish (if task workers are enabled).
     */
    public function onFinish($server, int $taskId, $data): void
    {
        // Handle task completion
        if ($this->options['debug'] ?? false) {
            echo "Task #{$taskId} finished\n";
        }
    }

    /**
     * Handle worker stop event.
     */
    public function onWorkerStop($server, int $workerId): void
    {
        if ($this->options['debug'] ?? false) {
            echo "Worker #{$workerId} stopped\n";
        }
    }

    /**
     * Create ThinkPHP request from Swoole request.
     */
    protected function createRequest($request): array
    {
        // Convert Swoole request to ThinkPHP compatible format
        return [
            'method' => $request->server['request_method'] ?? 'GET',
            'uri' => $request->server['request_uri'] ?? '/',
            'query' => $request->get ?? [],
            'body' => $request->post ?? [],
            'files' => $request->files ?? [],
            'headers' => $request->header ?? [],
            'server' => $request->server ?? [],
            'cookies' => $request->cookie ?? [],
            'raw_content' => $request->rawContent() ?? '',
        ];
    }

    /**
     * Send response to Swoole response.
     */
    protected function sendResponse($response, $thinkResponse): void
    {
        if (is_object($thinkResponse)) {
            // Handle object response
            if (method_exists($thinkResponse, 'getStatusCode')) {
                $response->status($thinkResponse->getStatusCode());
            }
            
            if (method_exists($thinkResponse, 'getHeaders')) {
                foreach ($thinkResponse->getHeaders() as $name => $value) {
                    $response->header($name, $value);
                }
            }
            
            if (method_exists($thinkResponse, 'getContent')) {
                $response->end($thinkResponse->getContent());
            } else {
                $response->end((string) $thinkResponse);
            }
        } elseif (is_array($thinkResponse)) {
            // Handle array response as JSON
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode($thinkResponse));
        } else {
            // Handle string/other response
            $response->end((string) $thinkResponse);
        }
    }

    /**
     * Handle exceptions.
     */
    protected function handleException($response, \Throwable $e): void
    {
        $response->status(500);
        
        if ($this->options['debug'] ?? false) {
            $response->header('Content-Type', 'text/plain');
            $response->end("Error: " . $e->getMessage() . "\n" .
                          "File: " . $e->getFile() . ":" . $e->getLine() . "\n" .
                          "Trace:\n" . $e->getTraceAsString());
        } else {
            $response->end("Internal Server Error");
        }
    }
}
