<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway\Embeddings;

use Symfony\AI\Platform\Bridge\Scaleway\Embeddings;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function convert(RawResultInterface $result, array $options = []): VectorResult
    {
        $data = $result->getData();

        if (!isset($data['data'])) {
            if ($result instanceof RawHttpResult) {
                throw new RuntimeException(\sprintf('Response from Scaleway API does not contain "data" key. StatusCode: "%s". Response: "%s".', $result->getObject()->getStatusCode(), json_encode($result->getData(), \JSON_THROW_ON_ERROR)));
            }

            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(
            ...array_map(
                static fn (array $item): Vector => new Vector($item['embedding']),
                $data['data'],
            ),
        );
    }
}
