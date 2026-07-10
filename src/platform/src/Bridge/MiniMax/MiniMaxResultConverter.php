<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReasonAwareTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MiniMaxResultConverter implements ResultConverterInterface
{
    use FinishReasonAwareTrait;

    use HttpStatusErrorHandlingTrait;

    /**
     * Delay, in seconds, between two polls of an asynchronous task.
     */
    private const POLL_INTERVAL = 1;

    /**
     * Maximum number of polls before giving up on an asynchronous audio task (~2 minutes).
     */
    private const MAX_AUDIO_POLLS = 120;

    /**
     * Maximum number of polls before giving up on a video task; video generation is
     * considerably slower than audio and routinely runs for several minutes (~10 minutes).
     */
    private const MAX_VIDEO_POLLS = 600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.minimax.io/v1',
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof MiniMax;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $url = (string) $response->getInfo('url');

        return match (true) {
            str_contains($url, '/chat/completions') => $this->withFinishReason(
                new TextResult($result->getData()['choices'][0]['message']['content']),
                FinishReasonMapper::map($result->getData()['choices'][0]['finish_reason'] ?? null),
            ),
            str_contains($url, '/t2a_async_v2') => $this->handleAsyncTask($result->getData(), 'query/t2a_async_query_v2', 'audio/mpeg', self::MAX_AUDIO_POLLS),
            str_contains($url, '/t2a_v2') => new BinaryResult($this->decodeHexAudio($result->getData()), 'audio/mpeg'),
            str_contains($url, '/image_generation') => $this->convertImage($result->getData()),
            str_contains($url, '/music_generation') => new BinaryResult($this->decodeHexAudio($result->getData()), 'audio/mpeg'),
            str_contains($url, '/video_generation') => $this->handleAsyncTask($result->getData(), 'query/video_generation', 'video/mp4', self::MAX_VIDEO_POLLS),
            default => throw new RuntimeException(\sprintf('Unsupported MiniMax response for url "%s".', $url)),
        };
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * @return \Generator<int, TextDelta|MetadataDelta>
     */
    private function convertStream(RawResultInterface $result): \Generator
    {
        $sawChunk = false;
        $finishReason = null;

        foreach ($result->getDataStream() as $chunk) {
            if (!\is_array($chunk)) {
                continue;
            }

            $sawChunk = true;

            if (null !== ($chunk['choices'][0]['finish_reason'] ?? null)) {
                $finishReason ??= FinishReasonMapper::map($chunk['choices'][0]['finish_reason']);
            }

            $content = $chunk['choices'][0]['delta']['content'] ?? '';

            if ('' === $content) {
                continue;
            }

            yield new TextDelta($content);
        }

        if ($sawChunk && null === $finishReason) {
            throw new IncompleteStreamException('The MiniMax stream ended before a finish reason.');
        }

        if (null !== $finishReason) {
            yield new MetadataDelta('finish_reason', $finishReason);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertImage(array $data): ResultInterface
    {
        if ([] !== ($data['data']['image_base64'] ?? [])) {
            $results = array_map(
                static fn (string $image): BinaryResult => new BinaryResult(base64_decode($image), 'image/jpeg'),
                $data['data']['image_base64'],
            );
        } else {
            $results = array_map(
                static fn (string $url): TextResult => new TextResult($url),
                $data['data']['image_urls'] ?? [],
            );
        }

        if ([] === $results) {
            throw new RuntimeException('The MiniMax response does not contain any image.');
        }

        if (1 === \count($results)) {
            return $results[0];
        }

        return new ChoiceResult(array_values($results));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function decodeHexAudio(array $data): string
    {
        $audio = $data['data']['audio'] ?? throw new RuntimeException('The MiniMax response does not contain any audio.');

        $decoded = hex2bin($audio);

        if (false === $decoded) {
            throw new RuntimeException('The MiniMax audio payload is not valid hexadecimal.');
        }

        return $decoded;
    }

    /**
     * Polls an asynchronous task until it reaches a terminal state, then downloads the resulting file.
     *
     * @param array<string, mixed> $data
     */
    private function handleAsyncTask(array $data, string $queryPath, string $mimeType, int $maxPolls): BinaryResult
    {
        $taskId = $data['task_id'] ?? throw new RuntimeException('The MiniMax response does not contain a task identifier.');
        $fileId = $data['file_id'] ?? null;

        for ($poll = 0; $poll < $maxPolls; ++$poll) {
            $response = $this->httpClient->request('GET', \sprintf('%s/%s?task_id=%s', $this->endpoint, $queryPath, $taskId), [
                'auth_bearer' => $this->apiKey,
            ]);

            $this->throwOnHttpError($response);

            $status = $response->toArray(false);

            $fileId = $status['file_id'] ?? $fileId;
            $state = strtolower((string) ($status['status'] ?? ''));

            if ('success' === $state) {
                return new BinaryResult($this->download($fileId), $mimeType);
            }

            if (\in_array($state, ['fail', 'failed', 'expired'], true)) {
                throw new RuntimeException(\sprintf('The MiniMax task "%s" failed with status "%s".', $taskId, $status['status'] ?? ''));
            }

            $this->clock->sleep(self::POLL_INTERVAL);
        }

        throw new RuntimeException(\sprintf('The MiniMax task "%s" did not complete in time.', $taskId));
    }

    private function download(mixed $fileId): string
    {
        if (null === $fileId) {
            throw new RuntimeException('The MiniMax task did not return a file identifier.');
        }

        $response = $this->httpClient->request('GET', \sprintf('%s/files/retrieve?file_id=%s', $this->endpoint, $fileId), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        $file = $response->toArray(false);

        $downloadUrl = $file['file']['download_url'] ?? throw new RuntimeException('The MiniMax file does not contain a download URL.');

        $download = $this->httpClient->request('GET', $downloadUrl);

        $this->throwOnHttpError($download);

        return $download->getContent();
    }
}
