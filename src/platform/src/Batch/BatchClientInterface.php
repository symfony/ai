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
 * Provider HTTP contract for the post-submission phase of a batch job.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
interface BatchClientInterface
{
    public function getBatch(string $batchId): BatchJob;

    public function canFetchResults(BatchJob $job): bool;

    /**
     * @return iterable<BatchResult>
     */
    public function fetchResults(BatchJob $job): iterable;

    public function cancelBatch(string $batchId): BatchJob;
}
