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

use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Resolves asynchronous jobs previously started through `Platform::invoke()`.
 *
 * Implementations perform exactly one request per call and never sleep: how often a job is polled,
 * and for how long, is the caller's decision - see {@see JobRunner} for the blocking variant.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface JobClientInterface
{
    public function supports(JobHandle $handle): bool;

    /**
     * Asks the provider where the job stands, in a single request.
     *
     * @throws ExceptionInterface
     */
    public function getStatus(JobHandle $handle): JobStatus;

    /**
     * Fetches the finished job's result.
     *
     * Only meaningful once {@see getStatus()} reported {@see JobStateCase::SUCCEEDED}; implementations
     * throw when the job did not finish successfully.
     *
     * @throws ExceptionInterface
     */
    public function getResult(JobHandle $handle): ResultInterface;
}
