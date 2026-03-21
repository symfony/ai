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
 * @author Camille Islasse <cams.development@gmail.com>
 */
interface BatchPlatformInterface
{
    /**
     * Submits a batch of requests to the provider for asynchronous processing.
     *
     * @param iterable<BatchInput> $inputs
     * @param array<string, mixed> $options
     */
    public function submitBatch(string $model, iterable $inputs, array $options = []): BatchJob;

    /**
     * Retrieves the current status of a batch job.
     */
    public function getBatch(string $batchId): BatchJob;

    /**
     * Streams the results of a completed batch job.
     *
     * @return iterable<BatchResult>
     */
    public function fetchResults(BatchJob $job): iterable;

    /**
     * Cancels an in-progress batch job and returns its updated status.
     */
    public function cancelBatch(string $batchId): BatchJob;
}
