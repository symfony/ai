<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Batch;

use Symfony\AI\Platform\Bridge\Mistral\Llm\ModelClient;
use Symfony\AI\Platform\Bridge\Mistral\Llm\ResultConverter;
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
 * The results arrive as a file of one chat completion per line, converted by the bridge's own converter.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JobClient implements JobClientInterface
{
    use HttpStatusErrorHandlingTrait;

    /**
     * Stated on every handle this bridge hands out, so a client does not pick up the batch of
     * another provider.
     */
    public const KIND = 'mistral_batch';

    /**
     * Mistral's own default for `timeout_hours`, used when a handle states no window.
     */
    public const DEFAULT_MAX_DURATION = 86400;

    /**
     * A batch runs for minutes at least, so asking about it once a minute is often enough.
     */
    public const DEFAULT_POLL_INTERVAL = 60.0;

    /**
     * `CANCELLATION_REQUESTED` is not terminal: the job is still finishing what it started, which is
     * also how Mistral's own SDK counts it.
     */
    private const STATES = [
        'QUEUED' => JobStateCase::QUEUED,
        'RUNNING' => JobStateCase::RUNNING,
        'CANCELLATION_REQUESTED' => JobStateCase::RUNNING,
        'SUCCESS' => JobStateCase::SUCCEEDED,
        'FAILED' => JobStateCase::FAILED,
        'TIMEOUT_EXCEEDED' => JobStateCase::EXPIRED,
        'CANCELLED' => JobStateCase::CANCELED,
    ];

    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.mistral.ai',
        private readonly ResultConverter $itemConverter = new ResultConverter(),
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

    /**
     * The items of the batch, including those a canceled or timed out one got through before it stopped.
     */
    public function getResult(JobHandle $handle): BatchResult
    {
        $data = $this->fetch($handle);

        $status = self::toStatus($data);
        $outputFile = $data['output_file'] ?? null;
        $errorFile = $data['error_file'] ?? null;

        if (!\is_string($outputFile) && !\is_string($errorFile)) {
            throw new JobFailedException($status, \sprintf('The Mistral batch "%s" has no results to fetch, its status is "%s".%s', $handle->getId(), $status->getRaw(), null !== $status->getError() ? ' '.$status->getError() : ''));
        }

        $endpoint = $handle->get('endpoint', ModelClient::PATH);

        if (ModelClient::PATH !== $endpoint) {
            throw new RuntimeException(\sprintf('The Mistral batch "%s" was submitted to "%s", which this bridge cannot convert the results of.', $handle->getId(), \is_string($endpoint) ? $endpoint : get_debug_type($endpoint)));
        }

        return new BatchResult($this->readItems($outputFile, $errorFile, $status->getCase()));
    }

    /**
     * Asks Mistral to stop the job, which then enters `CANCELLATION_REQUESTED` and ends up `CANCELLED`.
     */
    public function cancel(JobHandle $handle): JobStatus
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/v1/batch/jobs/%s/cancel', $this->baseUrl, urlencode($handle->getId())), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        return self::toStatus($response->toArray(false));
    }

    /**
     * Where the batch stands and how far Mistral has come, in requests - both from one request, since
     * the two are polled together.
     *
     * `completed` counts the requests that were answered, not the ones Mistral is done with: its
     * `completed_requests` includes the failed ones, where this contract keeps the two apart.
     *
     * @return array{status: JobStatus, total: int, completed: int, failed: int}
     */
    public function getProgress(JobHandle $handle): array
    {
        $data = $this->fetch($handle);

        return [
            'status' => self::toStatus($data),
            'total' => (int) ($data['total_requests'] ?? 0),
            'completed' => (int) ($data['succeeded_requests'] ?? 0),
            'failed' => (int) ($data['failed_requests'] ?? 0),
        ];
    }

    /**
     * @return \Generator<BatchItem>
     */
    private function readItems(?string $outputFile, ?string $errorFile, JobStateCase $state): \Generator
    {
        foreach ([$outputFile, $errorFile] as $fileId) {
            if (!\is_string($fileId)) {
                continue;
            }

            foreach ($this->readLines($fileId) as $line) {
                yield $this->toItem($line, $state);
            }
        }
    }

    /**
     * @param array<string, mixed> $line
     * @param JobStateCase         $state the outcome of the batch itself, which is the only thing
     *                                    saying whether a request failed or was never sent
     */
    private function toItem(array $line, JobStateCase $state): BatchItem
    {
        $customId = (string) ($line['custom_id'] ?? '');
        $response = \is_array($line['response'] ?? null) ? $line['response'] : [];
        $statusCode = \is_int($response['status_code'] ?? null) ? $response['status_code'] : null;
        $body = \is_array($response['body'] ?? null) ? $response['body'] : [];
        $error = $line['error'] ?? null;

        // The failure test of Mistral's own SDK: an error on the line, or a status code of 400 or above.
        if (null !== $error || (null !== $statusCode && 400 <= $statusCode)) {
            [$stated, $code] = self::describeError($error, $body);

            // Mistral states no per-request reason for a request it never sent, so the batch's own
            // outcome is what tells the two apart: a request that was answered carries the status
            // code of that answer, one that never left carries none. Never sending it cost nothing,
            // so it can be submitted again as it is, where an errored one has to be fixed first.
            if (null === $statusCode) {
                // The item's own message is canned for these, so whatever Mistral said is kept as
                // the raw outcome - its code when it named one, its wording otherwise.
                return match ($state) {
                    JobStateCase::CANCELED => BatchItem::canceled($customId, $code ?? $stated),
                    JobStateCase::EXPIRED => BatchItem::expired($customId, $code ?? $stated),
                    default => BatchItem::errored($customId, $stated ?? 'The batch did not answer this request.', $code),
                };
            }

            return BatchItem::errored($customId, $stated ?? \sprintf('The request failed with status code "%d".', $statusCode), $code);
        }

        try {
            return BatchItem::succeeded($customId, $this->itemConverter->convertData($body));
        } catch (ExceptionInterface $exception) {
            // One unreadable response is that request's problem, not the batch's.
            return BatchItem::errored($customId, $exception->getMessage());
        }
    }

    /**
     * Mistral carries a per-request error untyped, so it is taken as it comes: a plain string is the
     * whole of what it states, an object states it under `message`, and the chat endpoint's own
     * error body is the fallback.
     *
     * @param array<string, mixed> $body
     *
     * @return array{string|null, string|null} the wording Mistral stated and the code it named, both
     *                                         null where it named neither
     */
    private static function describeError(mixed $error, array $body): array
    {
        if (\is_string($error) && '' !== $error) {
            return [$error, $error];
        }

        $error = \is_array($error) ? $error : [];
        $message = $error['message'] ?? $body['message'] ?? $body['error']['message'] ?? null;
        $code = $error['code'] ?? $error['type'] ?? $body['type'] ?? $body['code'] ?? null;

        return [
            \is_string($message) && '' !== $message ? $message : null,
            \is_string($code) && '' !== $code ? $code : null,
        ];
    }

    /**
     * @return \Generator<array<string, mixed>>
     */
    private function readLines(string $fileId): \Generator
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/v1/files/%s/content', $this->baseUrl, urlencode($fileId)), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        $buffer = '';

        foreach ($this->httpClient->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();

            while (false !== $end = strpos($buffer, "\n")) {
                $line = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 1);

                if (null !== $decoded = self::decodeLine($line)) {
                    yield $decoded;
                }
            }
        }

        if (null !== $decoded = self::decodeLine($buffer)) {
            yield $decoded;
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
            throw new RuntimeException(\sprintf('A line of the Mistral batch result file is not valid JSON: "%s"', $exception->getMessage()), previous: $exception);
        }

        if (!\is_array($decoded)) {
            throw new RuntimeException(\sprintf('A line of the Mistral batch result file does not decode to an object, "%s" given.', get_debug_type($decoded)));
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(JobHandle $handle): array
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/v1/batch/jobs/%s', $this->baseUrl, urlencode($handle->getId())), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        return $response->toArray(false);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function toStatus(array $data): JobStatus
    {
        $raw = (string) ($data['status'] ?? '');
        $error = $data['errors'][0]['message'] ?? null;

        return new JobStatus(self::STATES[$raw] ?? JobStateCase::UNKNOWN, $raw, \is_string($error) && '' !== $error ? $error : null);
    }
}
