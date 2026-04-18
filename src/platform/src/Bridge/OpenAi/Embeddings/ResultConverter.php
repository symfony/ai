<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): VectorResult
    {
        if ($result instanceof RawHttpResult) {
            $httpResponse = $result->getObject();

            if (401 === $httpResponse->getStatusCode()) {
                throw new AuthenticationException($this->extractErrorMessage($httpResponse->getContent(false)) ?? 'Unauthorized');
            }

            if (400 === $httpResponse->getStatusCode()) {
                throw new BadRequestException($this->extractErrorMessage($httpResponse->getContent(false)) ?? 'Bad Request');
            }

            if (429 === $httpResponse->getStatusCode()) {
                $headers = $httpResponse->getHeaders(false);
                $retryAfter = $headers['retry-after'][0] ?? null;
                throw new RateLimitExceededException($retryAfter ? (int) $retryAfter : null);
            }
        }

        $data = $result->getData();

        if (!isset($data['data'])) {
            if ($result instanceof RawHttpResult) {
                throw new RuntimeException(\sprintf('Response from OpenAI API does not contain "data" key. StatusCode: "%s". Response: "%s".', $result->getObject()->getStatusCode(), json_encode($result->getData(), \JSON_THROW_ON_ERROR)));
            }

            throw new RuntimeException('Response does not contain data.');
        }

        return new VectorResult(
            array_map(
                static fn (array $item): Vector => new Vector($item['embedding']),
                $data['data']
            ),
        );
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
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
