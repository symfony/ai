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

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * Submits a batch of already-normalized requests through the {@see \Symfony\AI\Platform\Provider}.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
interface BatchSubmitClientInterface
{
    public function supports(Model $model): bool;

    /**
     * @param iterable<array{id: string, payload: array<string, mixed>}> $requests
     * @param array<string, mixed>                                       $options
     */
    public function submitBatch(Model $model, iterable $requests, array $options = []): RawResultInterface;
}
