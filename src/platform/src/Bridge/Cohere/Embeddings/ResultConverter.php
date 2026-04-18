<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Embeddings;

use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\MetaBilledUnitsTokenUsageExtractor;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): VectorResult
    {
        $httpResponse = $result->getObject();

        if (401 === $httpResponse->getStatusCode()) {
            throw new AuthenticationException($this->extractErrorMessage($httpResponse->getContent(false)) ?? 'Unauthorized');
        }

        if (400 === $httpResponse->getStatusCode()) {
            throw new BadRequestException($this->extractErrorMessage($httpResponse->getContent(false)) ?? 'Bad Request');
        }

        if (429 === $httpResponse->getStatusCode()) {
            $retryAfter = $httpResponse->getHeaders(false)['retry-after'][0] ?? null;
            throw new RateLimitExceededException($retryAfter ? (int) $retryAfter : null);
        }

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['embeddings']['float'])) {
            throw new RuntimeException('Response does not contain embedding data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $embedding): Vector => new Vector($embedding),
                $data['embeddings']['float'],
            ),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new MetaBilledUnitsTokenUsageExtractor();
    }

    private function extractErrorMessage(string $body): ?string
    {
        if ('' === $body) {
            return null;
        }

        $data = json_decode($body, true);
        if (!\is_array($data)) {
            return null;
        }

        return $data['error']['message'] ?? $data['message'] ?? null;
    }
}
