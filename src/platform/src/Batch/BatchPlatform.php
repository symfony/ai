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

use Symfony\AI\Platform\BatchClientInterface;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;

/**
 * Normalizes batch inputs via Contract and delegates HTTP operations to BatchClientInterface.
 *
 * Mirrors the responsibility split of Platform / ModelClientInterface for synchronous calls.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class BatchPlatform implements BatchPlatformInterface
{
    private readonly Contract $contract;

    public function __construct(
        private readonly BatchClientInterface $client,
        private readonly ModelCatalogInterface $modelCatalog,
        ?Contract $contract = null,
    ) {
        $this->contract = $contract ?? Contract::create();
    }

    public function submitBatch(string $model, iterable $inputs, array $options = []): BatchJob
    {
        $modelObj = $this->modelCatalog->getModel($model);

        if (!$this->client->supports($modelObj)) {
            throw new RuntimeException(\sprintf('No BatchClient registered for model "%s".', $model));
        }

        $mergedOptions = array_merge($modelObj->getOptions(), $options);
        $contract = $this->contract;

        $requests = (static function () use ($inputs, $modelObj, $mergedOptions, $contract): \Generator {
            foreach ($inputs as $input) {
                yield [
                    'id' => $input->getId(),
                    'payload' => $contract->createRequestPayload($modelObj, $input->getInput(), $mergedOptions),
                ];
            }
        })();

        return $this->client->submitBatch($modelObj, $requests, $mergedOptions);
    }

    public function getBatch(string $batchId): BatchJob
    {
        return $this->client->getBatch($batchId);
    }

    public function fetchResults(BatchJob $job): iterable
    {
        return $this->client->fetchResults($job);
    }

    public function cancelBatch(string $batchId): BatchJob
    {
        return $this->client->cancelBatch($batchId);
    }
}
