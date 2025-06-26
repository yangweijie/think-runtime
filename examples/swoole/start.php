<?php

/**
 * Swoole Server Startup Script
 * 
 * This script starts the Swoole HTTP server with ThinkPHP application.
 * 
 * Usage:
 * php start.php [start|stop|restart|reload|status]
 */

use Think\Runtime\Runtime\SwooleRuntime;

require_once __DIR__ . '/../../vendor/autoload.php';

// Parse command line arguments
$command = $argv[1] ?? 'start';
$pidFile = '/tmp/swoole.pid';

switch ($command) {
    case 'start':
        startServer();
        break;
    
    case 'stop':
        stopServer($pidFile);
        break;
    
    case 'restart':
        stopServer($pidFile);
        sleep(1);
        startServer();
        break;
    
    case 'reload':
        reloadServer($pidFile);
        break;
    
    case 'status':
        showStatus($pidFile);
        break;
    
    default:
        echo "Usage: php start.php [start|stop|restart|reload|status]\n";
        exit(1);
}

function startServer(): void
{
    echo "Starting Swoole HTTP server...\n";
    
    // Create runtime with configuration
    $runtime = new SwooleRuntime([
        'host' => '0.0.0.0',
        'port' => 9501,
        'worker_num' => 4,
        'enable_coroutine' => true,
        'enable_static_handler' => true,
        'document_root' => __DIR__ . '/public',
        'max_request' => 10000,
        'daemonize' => false,
        'log_file' => __DIR__ . '/runtime/swoole.log',
        'pid_file' => '/tmp/swoole.pid',
    ]);
    
    // Create application
    $appCallable = require __DIR__ . '/public/index.php';
    $resolver = $runtime->getResolver($appCallable);
    [$callable, $arguments] = $resolver->resolve();
    $application = $callable(...$arguments);
    
    // Get runner and start server
    $runner = $runtime->getRunner($application);
    $runner->run();
}

function stopServer(string $pidFile): void
{
    if (!file_exists($pidFile)) {
        echo "Server is not running.\n";
        return;
    }
    
    $pid = (int) file_get_contents($pidFile);
    if ($pid && posix_kill($pid, SIGTERM)) {
        echo "Server stopped successfully.\n";
        unlink($pidFile);
    } else {
        echo "Failed to stop server.\n";
    }
}

function reloadServer(string $pidFile): void
{
    if (!file_exists($pidFile)) {
        echo "Server is not running.\n";
        return;
    }
    
    $pid = (int) file_get_contents($pidFile);
    if ($pid && posix_kill($pid, SIGUSR1)) {
        echo "Server reloaded successfully.\n";
    } else {
        echo "Failed to reload server.\n";
    }
}

function showStatus(string $pidFile): void
{
    if (!file_exists($pidFile)) {
        echo "Server is not running.\n";
        return;
    }
    
    $pid = (int) file_get_contents($pidFile);
    if ($pid && posix_kill($pid, 0)) {
        echo "Server is running (PID: $pid).\n";
    } else {
        echo "Server is not running.\n";
        unlink($pidFile);
    }
}
