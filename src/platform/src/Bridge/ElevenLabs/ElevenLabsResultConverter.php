<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ElevenLabsResultConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ElevenLabs;
    }

    public function convert(RawHttpResult|RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (200 !== $response->getStatusCode()) {
            $errorMessage = $this->extractErrorMessage($response)
                ?? \sprintf('The ElevenLabs API returned a non-successful status code "%d".', $response->getStatusCode());

            throw new RuntimeException($errorMessage);
        }

        return match (true) {
            str_contains($response->getInfo('url'), 'text-to-speech') && \array_key_exists('stream', $options) && $options['stream'] => new StreamResult($this->convertToGenerator($response)),
            str_contains($response->getInfo('url'), 'text-to-speech') => new BinaryResult($result->getObject()->getContent(), 'audio/mpeg'),
            str_contains($response->getInfo('url'), 'speech-to-text') => new TextResult($result->getData()['text']),
            default => throw new RuntimeException('Unsupported ElevenLabs response.'),
        };
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }

    private function convertToGenerator(ResponseInterface $response): \Generator
    {
        foreach ($this->httpClient->stream($response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            if ('' === $chunk->getContent()) {
                continue;
            }

            yield $chunk->getContent();
        }
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);

            return $data['detail']['message'] ?? null;
        } catch (JsonException) {
            return null;
        }
    }
}
