<?php

declare(strict_types=1);

namespace Think\Runtime\Runner;

use Think\Runtime\Contract\RunnerInterface;

/**
 * Runner for void/null applications.
 */
class VoidRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(): int
    {
        return 0;
    }
}
