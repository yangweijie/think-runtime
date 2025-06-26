<?php

declare(strict_types=1);

namespace Think\Runtime\Runner\ThinkPHP;

use Think\Runtime\Contract\RunnerInterface;

/**
 * Runner for ThinkPHP Console applications.
 */
class ConsoleRunner implements RunnerInterface
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
            if (method_exists($this->application, 'run')) {
                return $this->application->run();
            }
            
            if (method_exists($this->application, 'execute')) {
                return $this->application->execute();
            }
            
            return 0;
        } catch (\Throwable $e) {
            $this->handleException($e);
            return 1;
        }
    }

    /**
     * Handle exceptions.
     */
    protected function handleException(\Throwable $e): void
    {
        if ($this->options['debug'] ?? false) {
            echo "Console Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo "Trace:\n" . $e->getTraceAsString() . "\n";
        } else {
            echo "Console Error: " . $e->getMessage() . "\n";
        }
    }
}
