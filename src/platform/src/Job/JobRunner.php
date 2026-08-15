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
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * Blocks until an asynchronous job finishes.
 *
 * This is the only place in the platform that sleeps in a loop. Bridges expose their jobs through a
 * {@see JobClientInterface} and stay free of polling; a caller who does not want to block skips this
 * class entirely and drives {@see JobClientInterface::getStatus()} from a worker or a scheduler.
 *
 * How long the work takes is the provider's knowledge, carried on the handle; how long you are
 * willing to wait for it is yours. State it per call, where the decision usually belongs - the same
 * video job may run for ten minutes in a worker and be given five seconds inside a web request:
 *
 *     $handle = $platform->invoke('MiniMax-Hailuo-02', $prompt)->asJob();
 *
 *     $result = $runner->wait($jobClient, $handle);                    // as long as the job needs
 *     $result = $runner->wait($jobClient, $handle, maxDuration: 5);    // or not a second longer
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
     * @param float    $pollInterval seconds to wait between two polls
     * @param int|null $maxDuration  seconds to wait before giving up, for every job this runner
     *                               waits for; null defers to what each job says it needs (see
     *                               {@see JobHandle::getMaxDuration()}), which is usually the better
     *                               answer - a single call can still overrule both
     */
    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly float $pollInterval = 1.0,
        private readonly ?int $maxDuration = null,
    ) {
        if ($this->pollInterval <= 0) {
            throw new InvalidArgumentException(\sprintf('The poll interval must be greater than zero, "%s" given.', $this->pollInterval));
        }

        self::assertDuration($this->maxDuration);
    }

    /**
     * @param int|null $maxDuration seconds to wait for this job, overruling both the runner's own
     *                              budget and what the job asks for
     *
     * @throws JobFailedException  when the job reached a terminal state without a result
     * @throws JobTimeoutException when the job was still running after the last poll
     */
    public function wait(JobClientInterface $jobClient, JobHandle $handle, ?int $maxDuration = null): ResultInterface
    {
        self::assertDuration($maxDuration);

        $budget = $maxDuration ?? $this->maxDuration ?? $handle->getMaxDuration() ?? self::DEFAULT_MAX_DURATION;
        $maxPolls = $this->maxPollsFor($budget);

        for ($poll = 1; $poll <= $maxPolls; ++$poll) {
            $status = $jobClient->getStatus($handle);

            if ($status->is(JobStateCase::SUCCEEDED)) {
                return $jobClient->getResult($handle);
            }

            if ($status->isTerminal()) {
                throw new JobFailedException($status, \sprintf('The job "%s" ended as "%s".%s', $handle->getId(), $status->getRaw(), null !== $status->getError() ? ' '.$status->getError() : ''));
            }

            // Not after the last poll: the caller would only wait for a status nobody reads.
            if ($poll < $maxPolls) {
                $this->clock->sleep($this->pollInterval);
            }
        }

        throw new JobTimeoutException($handle, \sprintf('The job "%s" did not finish within %d second(s). It may still be running - keep the handle and wait for it again later, or allow more time via the "maxDuration" argument.', $handle->getId(), $budget));
    }

    /**
     * Turns a duration into a number of polls at the configured interval.
     */
    private function maxPollsFor(int $maxDuration): int
    {
        return max(1, (int) ceil($maxDuration / $this->pollInterval));
    }

    private static function assertDuration(?int $maxDuration): void
    {
        if (null !== $maxDuration && $maxDuration < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum duration to wait must be at least one second, "%d" given.', $maxDuration));
        }
    }
}
