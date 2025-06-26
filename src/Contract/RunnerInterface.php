<?php

declare(strict_types=1);

namespace Think\Runtime\Contract;

/**
 * Runner interface for executing applications.
 * 
 * A Runner is responsible for executing an application instance
 * in the appropriate runtime environment.
 */
interface RunnerInterface
{
    /**
     * Run the application.
     * 
     * This method should execute the application and return an exit code.
     * - 0: Success
     * - Non-zero: Error
     *
     * @return int Exit code
     */
    public function run(): int;
}
