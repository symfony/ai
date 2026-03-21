<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchResult;

/**
 * HTTP-level contract for AI providers that support asynchronous batch processing.
 *
 * Receives already-normalized payloads from BatchPlatform, mirrors the role
 * of ModelClientInterface in the synchronous invocation flow.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
interface BatchClientInterface
{
    public function supports(Model $model): bool;

    /**
     * @param iterable<array{id: string, payload: array<string, mixed>}> $requests Normalized payloads
     * @param array<string, mixed>                                       $options  Merged model + invocation options
     */
    public function submitBatch(Model $model, iterable $requests, array $options = []): BatchJob;

    public function getBatch(string $batchId): BatchJob;

    /**
     * @return iterable<BatchResult>
     */
    public function fetchResults(BatchJob $job): iterable;

    public function cancelBatch(string $batchId): BatchJob;
}
