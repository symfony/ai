<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Exception;

/**
 * Exception thrown when a workflow place runs longer than its configured execution timeout.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowTimeoutException extends RuntimeException
{
    /**
     * @param non-empty-string $place   The place that timed out
     * @param int              $timeout The exceeded timeout, in seconds
     */
    public function __construct(
        private readonly string $place,
        private readonly int $timeout,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('Workflow place "%s" exceeded its %d second(s) execution timeout.', $place, $timeout), 0, $previous);
    }

    /**
     * @return non-empty-string
     */
    public function getPlace(): string
    {
        return $this->place;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
