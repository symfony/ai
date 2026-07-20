<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Inworld;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class InworldResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Inworld;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        if (!$response instanceof ResponseInterface) {
            throw new RuntimeException('Unexpected response object from Inworld API.');
        }

        if (200 !== $response->getStatusCode()) {
            $errorMessage = $this->extractErrorMessage($response)
                ?? \sprintf('The Inworld API returned a non-successful status code "%d".', $response->getStatusCode());

            throw new RuntimeException($errorMessage);
        }

        $url = $this->getUrl($response);

        if (str_contains($url, 'tts/v1/voice:stream')) {
            return new StreamResult($this->convertToGenerator($result));
        }

        if (str_contains($url, 'tts/v1/voice')) {
            $data = $result->getData();
            $audio = $data['audioContent'] ?? null;

            if (!\is_string($audio) || '' === $audio) {
                throw new RuntimeException('The Inworld API returned an empty audio content.');
            }

            return BinaryResult::fromBase64($audio, 'audio/mpeg');
        }

        if (str_contains($url, 'stt/v1/transcribe')) {
            $data = $result->getData();
            $transcription = $data['transcription'] ?? null;

            if (!\is_array($transcription)) {
                throw new RuntimeException('The Inworld API returned an invalid transcription payload.');
            }

            $transcript = $transcription['transcript'] ?? null;

            if (!\is_string($transcript)) {
                throw new RuntimeException('The Inworld API returned an invalid transcription payload.');
            }

            return new TextResult($transcript);
        }

        throw new RuntimeException('Unsupported Inworld response.');
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    private function convertToGenerator(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $chunk) {
            if (!\is_array($chunk)) {
                continue;
            }

            $innerResult = $chunk['result'] ?? null;

            if (!\is_array($innerResult)) {
                continue;
            }

            $audio = $innerResult['audioContent'] ?? null;

            if (!\is_string($audio) || '' === $audio) {
                continue;
            }

            $decoded = base64_decode($audio, true);

            if (false === $decoded) {
                continue;
            }

            yield new BinaryDelta($decoded, 'audio/mpeg');
        }
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (JsonException) {
            return null;
        }

        if (isset($data['message']) && \is_string($data['message'])) {
            return $data['message'];
        }

        if (isset($data['error']) && \is_array($data['error']) && isset($data['error']['message']) && \is_string($data['error']['message'])) {
            return $data['error']['message'];
        }

        return null;
    }

    private function getUrl(ResponseInterface $response): string
    {
        $url = $response->getInfo('url');

        if (!\is_string($url)) {
            throw new RuntimeException('Unable to read the response URL from the Inworld API.');
        }

        return $url;
    }
}
