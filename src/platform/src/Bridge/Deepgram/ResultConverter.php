<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Deepgram;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($result instanceof InMemoryRawResult) {
            return $this->convertInMemory($result);
        }

        if (!$result instanceof RawHttpResult) {
            throw new RuntimeException(\sprintf('Unsupported raw result of type "%s".', $result::class));
        }

        return $this->convertHttp($result, $options);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    private function convertInMemory(InMemoryRawResult $result): ResultInterface
    {
        $data = $result->getData();

        if (\array_key_exists('content', $data) && \is_string($data['content'])) {
            return new BinaryResult($data['content'], 'audio/mpeg');
        }

        return new TextResult($this->extractTranscript($data));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function convertHttp(RawHttpResult $result, array $options): ResultInterface
    {
        $response = $result->getObject();
        $rawUrl = $response->getInfo('url');
        $url = \is_string($rawUrl) ? $rawUrl : '';

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException($this->extractErrorMessage($response));
        }

        if (str_contains($url, '/speak')) {
            if (true === ($options['stream'] ?? false)) {
                if (null === $this->httpClient) {
                    throw new RuntimeException('Streaming responses require an HTTP client to be passed to the ResultConverter.');
                }

                return new StreamResult($this->streamBinary($response));
            }

            $contentType = $response->getHeaders(false)['content-type'][0] ?? 'audio/mpeg';

            return new BinaryResult($response->getContent(), $contentType);
        }

        if (str_contains($url, '/listen')) {
            return new TextResult($this->extractTranscript($result->getData()));
        }

        throw new RuntimeException(\sprintf('Unsupported Deepgram endpoint "%s".', $url));
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function extractTranscript(array $data): string
    {
        if (\array_key_exists('transcript', $data) && \is_string($data['transcript'])) {
            return $data['transcript'];
        }

        $results = $data['results'] ?? null;
        $channels = \is_array($results) ? ($results['channels'] ?? null) : null;
        if (\is_array($channels) && [] !== $channels) {
            $transcripts = [];
            foreach ($channels as $channel) {
                if (!\is_array($channel)) {
                    continue;
                }
                $alternatives = $channel['alternatives'] ?? null;
                if (!\is_array($alternatives) || !isset($alternatives[0]) || !\is_array($alternatives[0])) {
                    continue;
                }
                $candidate = $alternatives[0]['transcript'] ?? null;
                if (\is_string($candidate) && '' !== $candidate) {
                    $transcripts[] = $candidate;
                }
            }

            if ([] !== $transcripts) {
                return implode(' ', $transcripts);
            }
        }

        $channel = $data['channel'] ?? null;
        if (\is_array($channel)) {
            $alternatives = $channel['alternatives'] ?? null;
            if (\is_array($alternatives) && isset($alternatives[0]) && \is_array($alternatives[0])) {
                $alternative = $alternatives[0]['transcript'] ?? null;
                if (\is_string($alternative)) {
                    return $alternative;
                }
            }
        }

        return '';
    }

    private function streamBinary(ResponseInterface $response): \Generator
    {
        \assert(null !== $this->httpClient);

        foreach ($this->httpClient->stream($response) as $chunk) {
            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            $content = $chunk->getContent();
            if ('' === $content) {
                continue;
            }

            yield new BinaryDelta($content);
        }
    }

    private function extractErrorMessage(ResponseInterface $response): string
    {
        try {
            $data = $response->toArray(false);
        } catch (JsonException) {
            return \sprintf('The Deepgram API returned a non-successful status code "%d".', $response->getStatusCode());
        }

        $message = $data['err_msg']
            ?? $data['error']
            ?? $data['reason']
            ?? $data['message']
            ?? null;

        if (\is_string($message) && '' !== $message) {
            return \sprintf('The Deepgram API returned an error: "%s".', $message);
        }

        return \sprintf('The Deepgram API returned a non-successful status code "%d".', $response->getStatusCode());
    }
}
