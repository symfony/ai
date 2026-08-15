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
 * How long to wait is the caller's decision, not the bridge's: a text-to-speech job finishes in
 * seconds, a video job routinely runs for minutes.
 *
 *     $handle = $platform->invoke('MiniMax-Hailuo-02', $prompt)->asJob();
 *     $result = (new JobRunner(maxPolls: 600))->wait($jobClient, $handle);
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class JobRunner
{
    /**
     * How long to wait for a job whose handle carries no expectation of its own.
     */
    private const DEFAULT_MAX_DURATION = 120;

    /**
     * @param float    $pollInterval seconds to wait between two polls
     * @param int|null $maxPolls     how often to poll before giving up; null defers to what the job
     *                               says it needs (see {@see JobHandle::getMaxDuration()}), which is
     *                               usually the better answer - pass a number to overrule it
     */
    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly float $pollInterval = 1.0,
        private readonly ?int $maxPolls = null,
    ) {
        if ($this->pollInterval <= 0) {
            throw new InvalidArgumentException(\sprintf('The poll interval must be greater than zero, "%s" given.', $this->pollInterval));
        }

        if (null !== $this->maxPolls && $this->maxPolls < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum number of polls must be at least one, "%d" given.', $this->maxPolls));
        }
    }

    /**
     * @throws JobFailedException  when the job reached a terminal state without a result
     * @throws JobTimeoutException when the job was still running after the last poll
     */
    public function wait(JobClientInterface $jobClient, JobHandle $handle): ResultInterface
    {
        $maxPolls = $this->maxPolls ?? $this->maxPollsFor($handle);

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

        throw new JobTimeoutException($handle, \sprintf('The job "%s" did not finish within %d poll(s) every %s second(s). It may still be running - keep the handle and poll again later.', $handle->getId(), $maxPolls, $this->pollInterval));
    }

    /**
     * Turns what the job says it needs into a number of polls at the configured interval.
     */
    private function maxPollsFor(JobHandle $handle): int
    {
        return max(1, (int) ceil(($handle->getMaxDuration() ?? self::DEFAULT_MAX_DURATION) / $this->pollInterval));
    }
}
