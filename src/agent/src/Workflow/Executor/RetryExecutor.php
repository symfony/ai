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
use Symfony\AI\Agent\Exception\WorkflowGuardException;
use Symfony\AI\Agent\Exception\WorkflowMaxStepsExceededException;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\RetryStrategy;
use Symfony\AI\Agent\Workflow\Sleeper\UsleepSleeper;
use Symfony\AI\Agent\Workflow\SleeperInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Exception\RateLimitExceededException;

/**
 * Executor decorator that retries a wrapped executor when it fails.
 *
 * Retries every failure except a guard rejection or a max-steps overflow. When
 * the failure carries a {@see RateLimitExceededException}, its retry-after hint
 * overrides the configured backoff.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class RetryExecutor implements ExecutorInterface
{
    /**
     * @param positive-int     $maxAttempts Total attempts, including the first one
     * @param non-negative-int $baseDelayMs Base backoff delay in milliseconds
     * @param positive-int     $maxDelayMs  Upper bound for the computed backoff delay in milliseconds
     */
    public function __construct(
        private readonly ExecutorInterface $executor,
        private readonly int $maxAttempts = 3,
        private readonly int $baseDelayMs = 1000,
        private readonly RetryStrategy $strategy = RetryStrategy::Exponential,
        private readonly int $maxDelayMs = 30000,
        private readonly SleeperInterface $sleeper = new UsleepSleeper(),
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        $attempt = 0;

        while (true) {
            ++$attempt;

            try {
                return $this->executor->execute($state, $place);
            } catch (\Throwable $exception) {
                if ($attempt >= $this->maxAttempts || !$this->isRetryable($exception)) {
                    throw $exception;
                }

                $delayMs = $this->computeDelayMs($exception, $attempt);

                $this->logger?->warning('Workflow executor at place "{place}" failed (attempt {attempt}/{max}), retrying in {delay}ms: {message}', [
                    'place' => $place,
                    'attempt' => $attempt,
                    'max' => $this->maxAttempts,
                    'delay' => $delayMs,
                    'message' => $exception->getMessage(),
                ]);

                $this->sleeper->sleep($delayMs);
            }
        }
    }

    private function isRetryable(\Throwable $exception): bool
    {
        if ($exception instanceof WorkflowGuardException) {
            return false;
        }

        return !$exception instanceof WorkflowMaxStepsExceededException;
    }

    /**
     * @return non-negative-int
     */
    private function computeDelayMs(\Throwable $exception, int $attempt): int
    {
        $rateLimit = $this->findRateLimitException($exception);
        $retryAfter = $rateLimit?->getRetryAfter();

        if (null !== $retryAfter && $retryAfter > 0) {
            return $retryAfter * 1000;
        }

        return match ($this->strategy) {
            RetryStrategy::Fixed => min($this->baseDelayMs, $this->maxDelayMs),
            // Exponential backoff, doubling each attempt but bounded: the exponent is capped before the
            // shift so it never overflows to a zero (or negative) delay that would turn retries into a
            // tight loop, and the result is clamped to $maxDelayMs.
            RetryStrategy::Exponential => min($this->maxDelayMs, $this->baseDelayMs << min($attempt - 1, 30)),
        };
    }

    private function findRateLimitException(\Throwable $exception): ?RateLimitExceededException
    {
        if ($exception instanceof RateLimitExceededException) {
            return $exception;
        }

        $previous = $exception->getPrevious();

        // AgentExecutor wraps agent failures exactly once, so a single level is enough.
        return $previous instanceof RateLimitExceededException ? $previous : null;
    }
}
