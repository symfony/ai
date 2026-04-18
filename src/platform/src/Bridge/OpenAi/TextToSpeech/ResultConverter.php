<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;

use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeech;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (401 === $response->getStatusCode()) {
            throw new AuthenticationException($this->extractErrorMessage($response->getContent(false)) ?? 'Unauthorized');
        }

        if (400 === $response->getStatusCode()) {
            throw new BadRequestException($this->extractErrorMessage($response->getContent(false)) ?? 'Bad Request');
        }

        if (429 === $response->getStatusCode()) {
            $headers = $response->getHeaders(false);
            $retryAfter = $headers['retry-after'][0] ?? null;
            throw new RateLimitExceededException($retryAfter ? (int) $retryAfter : null);
        }

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('The OpenAI Text-to-Speech API returned an error: "%s"', $response->getContent(false)));
        }

        return new BinaryResult($result->getObject()->getContent());
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
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
