<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Batch;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\RegionAwareTrait;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter as OpenResponsesResultConverter;
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
 * The results arrive as a file of one Responses API object per line, converted by the bridge's own converter.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class JobClient implements JobClientInterface
{
    use HttpStatusErrorHandlingTrait;
    use RegionAwareTrait;

    /**
     * Stated on every handle this bridge hands out, so a client does not pick up the batch of
     * another provider.
     */
    public const KIND = 'openai_batch';

    /**
     * The longest OpenAI gives itself for a batch, used when one states no window of its own.
     */
    public const DEFAULT_MAX_DURATION = 86400;

    /**
     * A batch runs for minutes at least, so asking about it once a minute is often enough.
     */
    public const DEFAULT_POLL_INTERVAL = 60.0;

    /**
     * `cancelling` is not terminal: the batch is still finishing what it started.
     */
    private const STATES = [
        'validating' => JobStateCase::QUEUED,
        'in_progress' => JobStateCase::RUNNING,
        'finalizing' => JobStateCase::RUNNING,
        'cancelling' => JobStateCase::RUNNING,
        'completed' => JobStateCase::SUCCEEDED,
        'failed' => JobStateCase::FAILED,
        'expired' => JobStateCase::EXPIRED,
        'cancelled' => JobStateCase::CANCELED,
    ];

    /**
     * Codes OpenAI reports for requests a batch never got to send. Only `batch_expired` is
     * documented; `cancelled` is how OpenAI spells the batch state, so both spellings are accepted.
     */
    private const UNSENT = [
        'batch_expired' => JobStateCase::EXPIRED,
        'batch_cancelled' => JobStateCase::CANCELED,
        'batch_canceled' => JobStateCase::CANCELED,
    ];

    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        ?string $region = null,
        private readonly OpenResponsesResultConverter $itemConverter = new OpenResponsesResultConverter(),
    ) {
        $this->baseUrl = self::getBaseUrl($region);
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
     * The items of the batch, including those a canceled or expired one got through before it stopped.
     */
    public function getResult(JobHandle $handle): BatchResult
    {
        $data = $this->fetch($handle);

        $outputFileId = $data['output_file_id'] ?? null;
        $errorFileId = $data['error_file_id'] ?? null;

        if (!\is_string($outputFileId) && !\is_string($errorFileId)) {
            $status = self::toStatus($data);

            throw new JobFailedException($status, \sprintf('The OpenAI batch "%s" has no results to fetch, its status is "%s".%s', $handle->getId(), $status->getRaw(), null !== $status->getError() ? ' '.$status->getError() : ''));
        }

        $endpoint = $handle->get('endpoint', ModelClient::PATH);

        if (ModelClient::PATH !== $endpoint) {
            throw new RuntimeException(\sprintf('The OpenAI batch "%s" was submitted to "%s", which this bridge cannot convert the results of.', $handle->getId(), \is_string($endpoint) ? $endpoint : get_debug_type($endpoint)));
        }

        return new BatchResult($this->readItems($outputFileId, $errorFileId));
    }

    /**
     * Asks OpenAI to stop the batch, which then enters `cancelling` and ends up `cancelled` within the hour.
     */
    public function cancel(JobHandle $handle): JobStatus
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/v1/batches/%s/cancel', $this->baseUrl, urlencode($handle->getId())), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        return self::toStatus($response->toArray(false));
    }

    /**
     * Where the batch stands and how far OpenAI has come, in requests - both from one request, since
     * the two are polled together.
     *
     * @return array{status: JobStatus, total: int, completed: int, failed: int}
     */
    public function getProgress(JobHandle $handle): array
    {
        $data = $this->fetch($handle);
        $counts = $data['request_counts'] ?? [];

        return [
            'status' => self::toStatus($data),
            'total' => (int) ($counts['total'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
        ];
    }

    /**
     * @return \Generator<BatchItem>
     */
    private function readItems(?string $outputFileId, ?string $errorFileId): \Generator
    {
        foreach ([$outputFileId, $errorFileId] as $fileId) {
            if (!\is_string($fileId)) {
                continue;
            }

            foreach ($this->readLines($fileId) as $line) {
                yield $this->toItem($line);
            }
        }
    }

    /**
     * @param array<string, mixed> $line
     */
    private function toItem(array $line): BatchItem
    {
        $customId = (string) ($line['custom_id'] ?? '');

        if (null !== ($error = $line['error'] ?? null)) {
            $error = \is_array($error) ? $error : ['message' => (string) $error];
            $code = \is_string($error['code'] ?? null) ? $error['code'] : null;

            // A request the batch never got to send is not a failed one: it cost nothing and can be
            // submitted again as it is, where an errored one has to be fixed first.
            return match (self::UNSENT[$code] ?? null) {
                JobStateCase::EXPIRED => BatchItem::expired($customId, $code),
                JobStateCase::CANCELED => BatchItem::canceled($customId, $code),
                default => BatchItem::errored($customId, (string) ($error['message'] ?? 'unknown error'), $code),
            };
        }

        $response = $line['response'] ?? [];
        $body = \is_array($response) ? ($response['body'] ?? []) : [];

        if (!\is_array($body)) {
            $body = [];
        }

        $statusCode = \is_array($response) ? ($response['status_code'] ?? null) : null;

        if (!\is_int($statusCode) || $statusCode >= 400) {
            $code = $body['error']['code'] ?? $body['error']['type'] ?? null;

            return BatchItem::errored(
                $customId,
                (string) ($body['error']['message'] ?? \sprintf('The request failed with status code "%s".', $statusCode ?? 'unknown')),
                \is_string($code) ? $code : null,
            );
        }

        try {
            return BatchItem::succeeded($customId, $this->itemConverter->convertData($body));
        } catch (ExceptionInterface $exception) {
            // One unreadable response is that request's problem, not the batch's.
            return BatchItem::errored($customId, $exception->getMessage());
        }
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
            throw new RuntimeException(\sprintf('A line of the OpenAI batch result file is not valid JSON: "%s"', $exception->getMessage()), previous: $exception);
        }

        if (!\is_array($decoded)) {
            throw new RuntimeException(\sprintf('A line of the OpenAI batch result file does not decode to an object, "%s" given.', get_debug_type($decoded)));
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(JobHandle $handle): array
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/v1/batches/%s', $this->baseUrl, urlencode($handle->getId())), [
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
        $error = $data['errors']['data'][0]['message'] ?? null;

        return new JobStatus(self::STATES[$raw] ?? JobStateCase::UNKNOWN, $raw, \is_string($error) && '' !== $error ? $error : null);
    }
}
