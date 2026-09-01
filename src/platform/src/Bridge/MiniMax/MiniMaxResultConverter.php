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
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MiniMaxResultConverter implements ResultConverterInterface
{
    use FinishReasonAwareTrait;

    use HttpStatusErrorHandlingTrait;

    /**
     * How long MiniMax may reasonably take, carried in the job handle so a caller does not have to
     * know that video generation runs an order of magnitude longer than speech synthesis.
     */
    private const AUDIO_MAX_DURATION = 120;

    private const VIDEO_MAX_DURATION = 600;

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

        $this->throwOnBusinessError($result->getData());

        return match (true) {
            str_contains($url, '/chat/completions') => $this->withFinishReason(
                new TextResult($result->getData()['choices'][0]['message']['content']),
                FinishReasonMapper::map($result->getData()['choices'][0]['finish_reason'] ?? null),
            ),
            // Unlike the synchronous endpoint, the asynchronous one delivers a tar bundling the audio
            // with a `.titles` and an `.extra` file, so the job client has to unpack the mp3 to make
            // both endpoints produce the same thing.
            str_contains($url, '/t2a_async_v2') => $this->startJob($result->getData(), 'query/t2a_async_query_v2', 'audio/mpeg', self::AUDIO_MAX_DURATION, 'mp3'),
            str_contains($url, '/t2a_v2') => new BinaryResult($this->decodeHexAudio($result->getData()), 'audio/mpeg'),
            str_contains($url, '/image_generation') => $this->convertImage($result->getData()),
            str_contains($url, '/music_generation') => new BinaryResult($this->decodeHexAudio($result->getData()), 'audio/mpeg'),
            str_contains($url, '/video_generation') => $this->startJob($result->getData(), 'query/video_generation', 'video/mp4', self::VIDEO_MAX_DURATION),
            default => throw new RuntimeException(\sprintf('Unsupported MiniMax response for url "%s".', $url)),
        };
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    /**
     * MiniMax reports a rejected request with HTTP 200 and the reason in `base_resp`, so the status
     * code alone does not tell whether a response carries a result. Observed against the live API:
     * an unknown voice on `t2a_v2` yields `2054 "voice id not exist"`, an unsupported model on
     * `image_generation` yields `2013 "invalid params, ..."`, and an empty account yields
     * `1008 "insufficient balance"` - all with HTTP 200, all otherwise indistinguishable from a
     * success apart from the payload keys being absent. Every successful response carries
     * `status_code: 0`.
     *
     * @param array<string, mixed> $data
     */
    private function throwOnBusinessError(array $data): void
    {
        $statusCode = $data['base_resp']['status_code'] ?? 0;

        if (0 === $statusCode) {
            return;
        }

        throw new RuntimeException(\sprintf('MiniMax rejected the request: "%s" (status code "%s").', $data['base_resp']['status_msg'] ?? 'unknown error', $statusCode));
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
     * MiniMax answered with a task identifier instead of a payload, so the invocation produces a
     * reference to that task rather than a result. Resolving it - polling, and downloading the file
     * it produces - is the job of {@see MiniMaxJobClient}; the handle carries what that client needs
     * to know about the endpoint the task came from.
     *
     * @param array<string, mixed> $data
     * @param int                  $maxDuration   how long this endpoint may reasonably take, in seconds
     * @param string|null          $archiveMember file extension to unpack from the downloaded tar,
     *                                            or null when the download is the payload itself
     */
    private function startJob(array $data, string $queryPath, string $mimeType, int $maxDuration, ?string $archiveMember = null): JobResult
    {
        $taskId = $data['task_id'] ?? throw new RuntimeException('The MiniMax response does not contain a task identifier.');

        return new JobResult(new JobHandle((string) $taskId, [
            'query_path' => $queryPath,
            'mime_type' => $mimeType,
            'archive_member' => $archiveMember,
            'file_id' => $data['file_id'] ?? null,
        ], maxDuration: $maxDuration));
    }
}
