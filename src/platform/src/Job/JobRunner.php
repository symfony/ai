<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Job;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\JobTimeoutException;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * Blocks until an asynchronous job finishes.
 *
 * This is the only place in the platform that sleeps in a loop. Bridges expose their jobs through a
 * {@see JobClientInterface} and stay free of polling; a caller who does not want to block skips this
 * class entirely and drives {@see JobClientInterface::getStatus()} from a worker or a scheduler.
 *
 * How long the work takes, and how often it is worth asking about it, is the provider's knowledge,
 * carried on the handle; how long you are willing to wait and how many requests you are willing to
 * spend is yours. State it per call, where the decision usually belongs - the same video job may run
 * for ten minutes in a worker and be given five seconds inside a web request:
 *
 *     $handle = $platform->invoke('MiniMax-Hailuo-02', $prompt)->asJob();
 *
 *     $result = $runner->wait($jobClient, $handle);                    // as long as the job needs
 *     $result = $runner->wait($jobClient, $handle, maxDuration: 5);    // or not a second longer
 *     $result = $runner->wait($jobClient, $handle, maxPolls: 3);       // or not one request more
 *
 * What comes back is a {@see DeferredResult}, the same thing `Platform::invoke()` hands out, so
 * finishing a job reads like any other invocation - `->asFile()`, `->asBinary()`, `->asText()` -
 * instead of leaving the caller to narrow a bare `ResultInterface` itself.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobRunner
{
    /**
     * How long to wait for a job that states no expectation of its own.
     */
    private const DEFAULT_MAX_DURATION = 120;

    /**
     * How often to poll a job that states no expectation of its own.
     */
    private const DEFAULT_POLL_INTERVAL = 1.0;

    /**
     * Below the clock's own resolution, what is left of the budget is rounding.
     */
    private const CLOCK_RESOLUTION = 0.000001;

    /**
     * @param float|null $pollInterval seconds between two polls, for every job this runner waits for;
     *                                 null defers to {@see JobHandle::getPollInterval()}
     * @param int|null   $maxDuration  seconds to wait before giving up, for every job this runner
     *                                 waits for; null defers to what each job says it needs (see
     *                                 {@see JobHandle::getMaxDuration()}), which is usually the better
     *                                 answer - a single call can still overrule both
     * @param int|null   $maxPolls     how many times to ask at most, whatever the budget allows - unlike
     *                                 the two above, not something a job can state
     */
    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly ?float $pollInterval = null,
        private readonly ?int $maxDuration = null,
        private readonly ?int $maxPolls = null,
    ) {
        self::assertPollInterval($this->pollInterval);
        self::assertDuration($this->maxDuration);
        self::assertPolls($this->maxPolls);
    }

    /**
     * @param int|null   $maxDuration  seconds to wait for this job, overruling both the runner's own
     *                                 budget and what the job asks for
     * @param float|null $pollInterval seconds between two polls of this job, overruling both the
     *                                 runner's own interval and what the job asks for
     * @param int|null   $maxPolls     how many times to ask at most for this job, overruling the
     *                                 runner's own limit
     *
     * @throws InvalidArgumentException when the job client cannot resolve this handle
     * @throws JobFailedException       when the job reached a terminal state without a result
     * @throws JobTimeoutException      when the job was still running after the last poll
     */
    public function wait(JobClientInterface $jobClient, JobHandle $handle, ?int $maxDuration = null, ?float $pollInterval = null, ?int $maxPolls = null): DeferredResult
    {
        self::assertDuration($maxDuration);
        self::assertPollInterval($pollInterval);
        self::assertPolls($maxPolls);

        if (!$jobClient->supports($handle)) {
            throw new InvalidArgumentException(\sprintf('The job "%s" of provider "%s" cannot be resolved by "%s".', $handle->getId(), $handle->getProvider() ?? 'unknown', $jobClient::class));
        }

        $budget = $maxDuration ?? $this->maxDuration ?? $handle->getMaxDuration() ?? self::DEFAULT_MAX_DURATION;
        $interval = $pollInterval ?? $this->pollInterval ?? $handle->getPollInterval() ?? self::DEFAULT_POLL_INTERVAL;
        $limit = $maxPolls ?? $this->maxPolls;

        $deadline = $this->now() + $budget;
        $polls = 0;
        $cappedByPolls = false;

        while (true) {
            ++$polls;
            $status = $jobClient->getStatus($handle);

            if ($status->is(JobStateCase::SUCCEEDED)) {
                // The result is already there; PlainConverter only carries it into a DeferredResult
                // so the caller reaches it through the same accessors as a synchronous invocation.
                return new DeferredResult(new PlainConverter($jobClient->getResult($handle)), new InMemoryRawResult());
            }

            if ($status->isTerminal()) {
                throw new JobFailedException($status, \sprintf('The job "%s" ended as "%s".%s', $handle->getId(), $status->getRaw(), null !== $status->getError() ? ' '.$status->getError() : ''));
            }

            // A ceiling on the requests, not on the time: whichever runs out first ends the wait.
            if (null !== $limit && $polls >= $limit) {
                $cappedByPolls = true;

                break;
            }

            // Sleeping past the deadline would only wait for a status nobody reads.
            if ($this->now() + $interval + self::CLOCK_RESOLUTION >= $deadline) {
                break;
            }

            $this->clock->sleep($interval);
        }

        throw new JobTimeoutException($handle, $cappedByPolls ? \sprintf('The job "%s" did not finish within %d poll(s). It may still be running - keep the handle and wait for it again later, or allow more polls via the "maxPolls" argument.', $handle->getId(), $polls) : \sprintf('The job "%s" did not finish within %d second(s). It may still be running - keep the handle and wait for it again later, or allow more time via the "maxDuration" argument.', $handle->getId(), $budget));
    }

    private function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }

    private static function assertDuration(?int $maxDuration): void
    {
        if (null !== $maxDuration && $maxDuration < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum duration to wait must be at least one second, "%d" given.', $maxDuration));
        }
    }

    private static function assertPollInterval(?float $pollInterval): void
    {
        if (null !== $pollInterval && $pollInterval <= 0) {
            throw new InvalidArgumentException(\sprintf('The poll interval must be greater than zero, "%s" given.', $pollInterval));
        }
    }

    private static function assertPolls(?int $maxPolls): void
    {
        if (null !== $maxPolls && $maxPolls < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum number of polls must be at least one, "%d" given.', $maxPolls));
        }
    }
}
