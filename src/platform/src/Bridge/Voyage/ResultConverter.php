<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Voyage;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }

        $result = $result->getData();

        if (!isset($result['data'])) {
            throw new RuntimeException('Response does not contain embedding data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $data) => new Vector($data['embedding']),
                $result['data'],
            ),
        );
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }

    /**
     * Overrides the shared lookup: Voyage reports errors as `{"detail": "..."}`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $detail = $response->toArray(false)['detail'] ?? null;
        } catch (DecodingExceptionInterface) {
            return null;
        }

        return \is_string($detail) ? $detail : null;
    }
}
