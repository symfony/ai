<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ResultConverter;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Model\EmbeddingsModel;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

/**
 * This default result converter assumes the same response format as OpenAI's embeddings endpoint.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class EmbeddingsResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof EmbeddingsModel;
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        $data = $result->getData();

        if (!isset($data['data'])) {
            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(
            ...array_map(
                static fn (array $item): Vector => new Vector($item['embedding']),
                $data['data']
            ),
        );
    }
}
