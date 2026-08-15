<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\Job;

use Symfony\AI\Platform\Exception\LogicException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * A job client answering a scripted sequence of statuses and counting how often it was asked, so a
 * test can assert not only the outcome but how many polls it took to get there.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ScriptedJobClient implements JobClientInterface
{
    public int $statusCalls = 0;

    public int $resultCalls = 0;

    /**
     * @var list<JobStatus>
     */
    private array $statuses;

    public function __construct(JobStatus ...$statuses)
    {
        $this->statuses = $statuses;
    }

    public function supports(JobHandle $handle): bool
    {
        return true;
    }

    public function getStatus(JobHandle $handle): JobStatus
    {
        ++$this->statusCalls;

        return array_shift($this->statuses) ?? throw new LogicException('The runner polled more often than the test scripted.');
    }

    public function getResult(JobHandle $handle): ResultInterface
    {
        ++$this->resultCalls;

        return new TextResult('done');
    }
}
