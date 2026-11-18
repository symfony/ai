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
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
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
final class DeepgramResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Deepgram;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if (!$result instanceof RawHttpResult) {
            throw new RuntimeException(\sprintf('Unsupported raw result of type "%s".', $result::class));
        }

        $response = $result->getObject();
        $rawUrl = $response->getInfo('url');
        $url = \is_string($rawUrl) ? $rawUrl : '';
        $path = parse_url($url, \PHP_URL_PATH);
        $path = \is_string($path) ? $path : '';

        if (200 !== $response->getStatusCode()) {
            $this->throwOnHttpError($response);

            $errorMessage = $this->extractErrorMessage($response);
            if (null === $errorMessage) {
                throw new RuntimeException(\sprintf('The Deepgram API returned a non-successful status code "%d".', $response->getStatusCode()));
            }

            throw new RuntimeException(\sprintf('The Deepgram API returned an error: "%s".', $errorMessage));
        }

        if (str_ends_with($path, '/speak')) {
            if (true === ($options['stream'] ?? false)) {
                return new StreamResult($this->streamBinary($response));
            }

            $contentType = $response->getHeaders(false)['content-type'][0] ?? 'audio/mpeg';

            return new BinaryResult($response->getContent(), $contentType);
        }

        if (str_ends_with($path, '/listen')) {
            return new TextResult($this->extractTranscript($result->getData()));
        }

        throw new RuntimeException(\sprintf('Unsupported Deepgram endpoint "%s".', $url));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function extractTranscript(array $data): string
    {
        $results = $data['results'] ?? null;
        $channels = \is_array($results) ? ($results['channels'] ?? null) : null;
        if (!\is_array($channels)) {
            throw new RuntimeException('Unexpected Deepgram transcription response: the "results.channels" entry is missing.');
        }

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

        return implode(' ', $transcripts);
    }

    private function streamBinary(ResponseInterface $response): \Generator
    {
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

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (JsonException) {
            return null;
        }

        $message = $data['err_msg']
            ?? $data['error']
            ?? $data['reason']
            ?? $data['message']
            ?? null;

        if (\is_string($message) && '' !== $message) {
            return $message;
        }

        return null;
    }
}
