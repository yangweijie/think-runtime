<?php

declare(strict_types=1);

namespace Think\Runtime\Runner\ThinkPHP;

use Think\Runtime\Contract\RunnerInterface;

/**
 * Runner for ThinkPHP Response objects.
 */
class ResponseRunner implements RunnerInterface
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
            if (method_exists($this->application, 'send')) {
                $this->application->send();
            } elseif (method_exists($this->application, 'getContent')) {
                echo $this->application->getContent();
            } else {
                echo (string) $this->application;
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
            echo "Response Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        } else {
            echo "Internal Server Error\n";
        }
    }
}
