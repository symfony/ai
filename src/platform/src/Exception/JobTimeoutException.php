<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

use Symfony\AI\Platform\Job\JobHandle;

/**
 * Thrown when a job did not reach a terminal state within the budget given to the runner.
 *
 * Unlike {@see JobFailedException} this says nothing about the job itself - it is still running at
 * the provider. The handle survives the exception, so a caller can raise the budget, hand the job to
 * a worker, or come back to it later.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class JobTimeoutException extends RuntimeException
{
    public function __construct(
        private readonly JobHandle $handle,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getHandle(): JobHandle
    {
        return $this->handle;
    }
}
