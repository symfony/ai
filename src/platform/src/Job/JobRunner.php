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
     * @param float $pollInterval seconds to wait between two polls
     * @param int   $maxPolls     how often to poll before giving up
     */
    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly float $pollInterval = 1.0,
        private readonly int $maxPolls = 120,
    ) {
        if ($this->pollInterval <= 0) {
            throw new InvalidArgumentException(\sprintf('The poll interval must be greater than zero, "%s" given.', $this->pollInterval));
        }

        if ($this->maxPolls < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum number of polls must be at least one, "%d" given.', $this->maxPolls));
        }
    }

    /**
     * @throws JobFailedException  when the job reached a terminal state without a result
     * @throws JobTimeoutException when the job was still running after the last poll
     */
    public function wait(JobClientInterface $jobClient, JobHandle $handle): ResultInterface
    {
        for ($poll = 1; $poll <= $this->maxPolls; ++$poll) {
            $status = $jobClient->getStatus($handle);

            if ($status->is(JobStateCase::SUCCEEDED)) {
                return $jobClient->getResult($handle);
            }

            if ($status->isTerminal()) {
                throw new JobFailedException($status, \sprintf('The job "%s" ended as "%s".%s', $handle->getId(), $status->getRaw(), null !== $status->getError() ? ' '.$status->getError() : ''));
            }

            // Not after the last poll: the caller would only wait for a status nobody reads.
            if ($poll < $this->maxPolls) {
                $this->clock->sleep($this->pollInterval);
            }
        }

        throw new JobTimeoutException($handle, \sprintf('The job "%s" did not finish within %d poll(s) every %s second(s). It may still be running - keep the handle and poll again later.', $handle->getId(), $this->maxPolls, $this->pollInterval));
    }
}
