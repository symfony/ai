<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Batch;

use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter as GeminiResultConverter;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Result\BatchItem;
use Symfony\AI\Platform\Result\BatchResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the batches this bridge hands out, needing neither a platform nor a provider.
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class JobClient implements JobClientInterface
{
    use HttpStatusErrorHandlingTrait;

    /**
     * Stated on every handle, so a client does not pick up the batch of another provider.
     */
    public const KIND = 'gemini_batch';

    public const DEFAULT_MAX_DURATION = 172800;

    public const DEFAULT_POLL_INTERVAL = 60.0;

    private const STATES = [
        'PENDING' => JobStateCase::QUEUED,
        'RUNNING' => JobStateCase::RUNNING,
        'SUCCEEDED' => JobStateCase::SUCCEEDED,
        'FAILED' => JobStateCase::FAILED,
        'CANCELLED' => JobStateCase::CANCELED,
        'EXPIRED' => JobStateCase::EXPIRED,
    ];

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Gemini-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://generativelanguage.googleapis.com',
        private readonly GeminiResultConverter $itemConverter = new GeminiResultConverter(),
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(JobHandle $handle): bool
    {
        return self::KIND === $handle->get('kind');
    }

    public function getStatus(JobHandle $handle): JobStatus
    {
        return self::toStatus($this->fetch($handle));
    }

    public function getResult(JobHandle $handle): BatchResult
    {
        $data = $this->fetch($handle);
        $output = $data['response'] ?? $data['output'] ?? $data['dest'] ?? [];
        $output = \is_array($output) ? $output : [];

        $inlined = $output['inlinedResponses'] ?? null;
        $inlined = \is_array($inlined) ? ($inlined['inlinedResponses'] ?? $inlined) : null;
        $file = $output['responsesFile'] ?? $output['fileName'] ?? null;

        if (!\is_array($inlined) && !\is_string($file)) {
            $status = self::toStatus($data);

            throw new JobFailedException($status, \sprintf('The Gemini batch "%s" has no results to fetch, its status is "%s".%s', $handle->getId(), $status->getRaw(), null !== $status->getError() ? ' '.$status->getError() : ''));
        }

        return new BatchResult(\is_array($inlined) ? $this->readInlined($inlined) : $this->readFile($file));
    }

    public function cancel(JobHandle $handle): JobStatus
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/v1beta/%s:cancel', $this->baseUrl, self::encodePath($handle->getId())), [
            'headers' => ['x-goog-api-key' => $this->apiKey],
        ]);

        $this->throwOnHttpError($response);

        return $this->getStatus($handle);
    }

    /**
     * @return array{status: JobStatus, total: int, completed: int, failed: int}
     */
    public function getProgress(JobHandle $handle): array
    {
        $data = $this->fetch($handle);
        $stats = self::metadata($data)['batchStats'] ?? $data['batchStats'] ?? [];

        return [
            'status' => self::toStatus($data),
            'total' => (int) ($stats['requestCount'] ?? 0),
            'completed' => (int) ($stats['successfulRequestCount'] ?? 0),
            'failed' => (int) ($stats['failedRequestCount'] ?? 0),
        ];
    }

    /**
     * @param array<int|string, mixed> $entries
     *
     * @return \Generator<BatchItem>
     */
    private function readInlined(array $entries): \Generator
    {
        foreach (array_values($entries) as $index => $entry) {
            if (\is_array($entry)) {
                yield $this->toItem($entry, (string) ($entry['metadata']['key'] ?? $index));
            }
        }
    }

    /**
     * @return \Generator<BatchItem>
     */
    private function readFile(string $file): \Generator
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/download/v1beta/%s:download?alt=media', $this->baseUrl, self::encodePath($file)), [
            'headers' => ['x-goog-api-key' => $this->apiKey],
        ]);

        $this->throwOnHttpError($response);

        $buffer = '';
        $index = 0;

        foreach ($this->httpClient->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();

            while (false !== $end = strpos($buffer, "\n")) {
                $line = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 1);

                if (null !== $decoded = self::decodeLine($line)) {
                    yield $this->toItem($decoded, (string) ($decoded['key'] ?? $decoded['metadata']['key'] ?? $index));
                    ++$index;
                }
            }
        }

        if (null !== $decoded = self::decodeLine($buffer)) {
            yield $this->toItem($decoded, (string) ($decoded['key'] ?? $decoded['metadata']['key'] ?? $index));
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function toItem(array $entry, string $key): BatchItem
    {
        if (null !== ($error = $entry['error'] ?? null)) {
            $error = \is_array($error) ? $error : ['message' => (string) $error];
            $code = $error['status'] ?? $error['code'] ?? null;

            return BatchItem::errored($key, (string) ($error['message'] ?? 'unknown error'), \is_string($code) || \is_int($code) ? (string) $code : null);
        }

        $response = $entry['response'] ?? null;

        if (!\is_array($response)) {
            return BatchItem::errored($key, 'The entry holds neither a response nor an error.');
        }

        try {
            return BatchItem::succeeded($key, $this->itemConverter->convertData($response));
        } catch (ExceptionInterface $exception) {
            return BatchItem::errored($key, $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeLine(string $line): ?array
    {
        if ('' === trim($line)) {
            return null;
        }

        try {
            $decoded = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(\sprintf('A line of the Gemini batch result file is not valid JSON: "%s"', $exception->getMessage()), previous: $exception);
        }

        if (!\is_array($decoded)) {
            throw new RuntimeException(\sprintf('A line of the Gemini batch result file does not decode to an object, "%s" given.', get_debug_type($decoded)));
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(JobHandle $handle): array
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/v1beta/%s', $this->baseUrl, self::encodePath($handle->getId())), [
            'headers' => ['x-goog-api-key' => $this->apiKey],
        ]);

        $this->throwOnHttpError($response);

        return $response->toArray(false);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function metadata(array $data): array
    {
        return \is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
    }

    private static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function toStatus(array $data): JobStatus
    {
        $raw = (string) (self::metadata($data)['state'] ?? $data['state'] ?? '');
        $case = self::STATES[preg_replace('/^(?:BATCH|JOB)_STATE_/', '', $raw)] ?? JobStateCase::UNKNOWN;

        $error = $data['error'] ?? null;
        $error = \is_array($error) ? ($error['message'] ?? null) : $error;

        return new JobStatus($case, $raw, \is_string($error) && '' !== $error ? $error : null);
    }
}
