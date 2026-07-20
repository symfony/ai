<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Executor;

use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Exception\WorkflowTimeoutException;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * Aborts a wrapped executor that runs longer than a timeout.
 *
 * The timeout is enforced with a SIGALRM signal, so it requires the "pcntl" extension on a
 * platform that supports it (Unix-like systems, CLI). When pcntl is unavailable the wrapped
 * executor runs without a timeout and a warning is logged.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TimeoutExecutor implements ExecutorInterface
{
    /**
     * @param int $timeout Timeout in seconds; a value of 0 or less disables the timeout
     */
    public function __construct(
        private readonly ExecutorInterface $executor,
        private readonly int $timeout,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        if ($this->timeout <= 0) {
            return $this->executor->execute($state, $place);
        }

        if (!\function_exists('pcntl_alarm')) {
            $this->logger?->warning('Workflow place "{place}" timeout is not enforced: the "pcntl" extension is unavailable.', ['place' => $place]);

            return $this->executor->execute($state, $place);
        }

        $timedOut = false;
        $timeout = $this->timeout;

        $previousAsync = pcntl_async_signals(true);
        $previousHandler = pcntl_signal_get_handler(\SIGALRM);

        pcntl_signal(\SIGALRM, static function () use (&$timedOut, $place, $timeout): void {
            $timedOut = true;

            throw new WorkflowTimeoutException($place, $timeout);
        });

        pcntl_alarm($this->timeout);

        try {
            return $this->executor->execute($state, $place);
        } catch (\Throwable $exception) {
            if ($timedOut) {
                // The wrapped executor may have caught and re-wrapped the timeout; surface it cleanly.
                throw $exception instanceof WorkflowTimeoutException ? $exception : new WorkflowTimeoutException($place, $timeout, $exception);
            }

            throw $exception;
        } finally {
            pcntl_alarm(0);
            pcntl_signal(\SIGALRM, $previousHandler);
            pcntl_async_signals($previousAsync);
        }
    }
}
