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
final class BatchManager implements BatchManagerInterface
{
    public function __construct(
        private readonly BatchClientInterface $client,
    ) {
    }

    public function refresh(BatchJob $job): BatchJob
    {
        return $this->client->getBatch($job->getId());
    }

    public function canFetchResults(BatchJob $job): bool
    {
        return $this->client->canFetchResults($job);
    }

    public function fetchResults(BatchJob $job): iterable
    {
        return $this->client->fetchResults($job);
    }

    public function cancel(BatchJob $job): BatchJob
    {
        return $this->client->cancelBatch($job->getId());
    }
}
