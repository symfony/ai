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

use Symfony\AI\Platform\Job\JobStatus;

/**
 * Thrown when an asynchronous job reached a terminal state without a result.
 *
 * The job is gone: it failed, expired at the provider, or was canceled. Resubmitting is a new job,
 * polling the same handle again will not change the outcome. {@see getStatus()} carries the
 * provider's own wording and failure message.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class JobFailedException extends RuntimeException
{
    public function __construct(
        private readonly JobStatus $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getStatus(): JobStatus
    {
        return $this->status;
    }
}
