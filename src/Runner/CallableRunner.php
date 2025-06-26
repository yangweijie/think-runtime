<?php

declare(strict_types=1);

namespace Think\Runtime\Runner;

use Think\Runtime\Contract\RunnerInterface;

/**
 * Runner for callable applications.
 */
class CallableRunner implements RunnerInterface
{
    private $application;
    private array $options;

    public function __construct(callable $application, array $options = [])
    {
        $this->application = $application;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): int
    {
        $result = ($this->application)();

        if (is_int($result)) {
            return $result;
        }

        return 0;
    }
}
