<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Batch;

/**
 * Post-submission phase of a batch job: polling, result fetching and cancellation.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
interface BatchManagerInterface
{
    /**
     * Re-fetches the current state of a previously submitted job.
     */
    public function refresh(BatchJob $job): BatchJob;

    /**
     * Whether results can already be fetched for the given job, without throwing.
     */
    public function canFetchResults(BatchJob $job): bool;

    /**
     * Streams the results of a job once they are available.
     *
     * @return iterable<BatchResult>
     */
    public function fetchResults(BatchJob $job): iterable;

    /**
     * Requests cancellation of an in-progress job and returns its updated state.
     */
    public function cancel(BatchJob $job): BatchJob;
}
