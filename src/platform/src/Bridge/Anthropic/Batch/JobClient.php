<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Batch;

use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter;
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
 * The results arrive as one Messages API response per line, converted by the bridge's own converter.
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
    public const KIND = 'anthropic_batch';

    /**
     * Anthropic drops a batch that has not finished within 24 hours, used when one states no
     * window of its own.
     */
    public const DEFAULT_MAX_DURATION = 86400;

    /**
     * A batch runs for minutes at least, so asking about it once a minute is often enough.
     */
    public const DEFAULT_POLL_INTERVAL = 60.0;

    /**
     * Anthropic knows three states and no batch-level failure: a batch whose requests all errored,
     * and one that was canceled, both end as `ended` and hand out what they have. `canceling` is
     * not terminal - the batch is still finishing what it started.
     */
    private const STATES = [
        'in_progress' => JobStateCase::RUNNING,
        'canceling' => JobStateCase::RUNNING,
        'ended' => JobStateCase::SUCCEEDED,
    ];

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of an Anthropic-compatible endpoint, without trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.anthropic.com',
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
     * The items of the batch, including those a canceled or expired one got through before it stopped.
     */
    public function getResult(JobHandle $handle): BatchResult
    {
        $data = $this->fetch($handle);
        $resultsUrl = $data['results_url'] ?? null;

        // Anthropic has no batch-level failure, and a canceled batch ends as `ended` with partial
        // results - so what decides whether there is something to hand out is the results being
        // available, which Anthropic states by filling `results_url`, and never the status.
        if (!\is_string($resultsUrl) || '' === $resultsUrl) {
            $status = self::toStatus($data);

            throw new JobFailedException($status, \sprintf('The Anthropic batch "%s" has no results to fetch, its status is "%s".', $handle->getId(), $status->getRaw()));
        }

        return new BatchResult($this->readItems($handle->getId()));
    }

    /**
     * Asks Anthropic to stop the batch, which then enters `canceling` and ends up `ended` with the
     * results it got through.
     */
    public function cancel(JobHandle $handle): JobStatus
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/v1/messages/batches/%s/cancel', $this->baseUrl, urlencode($handle->getId())), [
            'headers' => $this->createHeaders(),
        ]);

        $this->throwOnHttpError($response);

        return self::toStatus($response->toArray(false));
    }

    /**
     * Where the batch stands and how far Anthropic has come, in requests - both from one request,
     * since the two are polled together.
     *
     * @return array{status: JobStatus, total: int, completed: int, failed: int}
     */
    public function getProgress(JobHandle $handle): array
    {
        $data = $this->fetch($handle);
        $counts = $data['request_counts'] ?? [];

        if (!\is_array($counts)) {
            $counts = [];
        }

        $processing = (int) ($counts['processing'] ?? 0);
        $succeeded = (int) ($counts['succeeded'] ?? 0);
        $errored = (int) ($counts['errored'] ?? 0);

        return [
            'status' => self::toStatus($data),
            // Anthropic counts five outcomes where the shared shape has three, and states no total
            // of its own, so it is the sum of the five. The requests a canceled or expired batch
            // never sent are neither completed nor failed: they cost nothing and can be submitted
            // again, which is also how OpenAI leaves them out of both of its counts.
            'total' => $processing + $succeeded + $errored + (int) ($counts['canceled'] ?? 0) + (int) ($counts['expired'] ?? 0),
            'completed' => $succeeded,
            'failed' => $errored,
        ];
    }

    /**
     * @return \Generator<BatchItem>
     */
    private function readItems(string $batchId): \Generator
    {
        // Anthropic recommends streaming the results rather than downloading them at once, so they
        // are read line by line from the documented endpoint - which is what `results_url` points
        // to, but built here so the credentials only ever travel to the configured base URL.
        $response = $this->httpClient->request('GET', \sprintf('%s/v1/messages/batches/%s/results', $this->baseUrl, urlencode($batchId)), [
            'headers' => $this->createHeaders(),
        ]);

        $this->throwOnHttpError($response);

        $buffer = '';

        foreach ($this->httpClient->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();

            while (false !== $end = strpos($buffer, "\n")) {
                $line = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 1);

                if (null !== $decoded = self::decodeLine($line)) {
                    yield $this->toItem($decoded);
                }
            }
        }

        if (null !== $decoded = self::decodeLine($buffer)) {
            yield $this->toItem($decoded);
        }
    }

    /**
     * Anthropic reports per request whether it answered it, failed it, or never sent it at all -
     * the distinction the batch item is built around.
     *
     * @param array<string, mixed> $line
     */
    private function toItem(array $line): BatchItem
    {
        $customId = (string) ($line['custom_id'] ?? '');
        $result = $line['result'] ?? [];

        if (!\is_array($result)) {
            $result = [];
        }

        $type = (string) ($result['type'] ?? '');

        if ('succeeded' === $type) {
            $message = $result['message'] ?? null;

            if (!\is_array($message)) {
                return BatchItem::errored($customId, 'The successful result of the request does not contain a message.', $type);
            }

            try {
                return BatchItem::succeeded($customId, $this->itemConverter->convertData($message), $type);
            } catch (ExceptionInterface $exception) {
                // One unreadable response is that request's problem, not the batch's.
                return BatchItem::errored($customId, $exception->getMessage(), $type);
            }
        }

        if ('errored' === $type) {
            // The failure is an error response of its own, so the message sits one level deeper.
            $error = $result['error']['error'] ?? [];
            $error = \is_array($error) ? $error : [];
            $message = \is_string($error['message'] ?? null) ? $error['message'] : 'unknown error';
            $errorType = $error['type'] ?? null;

            // The item's raw outcome is the result type, so Anthropic's own error type - which
            // tells a malformed request from a transient server error - is kept in the message.
            return BatchItem::errored($customId, \is_string($errorType) ? \sprintf('[%s] %s', $errorType, $message) : $message, $type);
        }

        // A request the batch never got to send is not a failed one: it cost nothing and can be
        // submitted again as it is, where an errored one has to be fixed first.
        return match ($type) {
            'canceled' => BatchItem::canceled($customId, $type),
            'expired' => BatchItem::expired($customId, $type),
            default => BatchItem::unknown($customId, $type, \sprintf('The batch reported the outcome "%s" for this request, which this bridge does not know.', $type)),
        };
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
            throw new RuntimeException(\sprintf('A line of the Anthropic batch results is not valid JSON: "%s"', $exception->getMessage()), previous: $exception);
        }

        if (!\is_array($decoded)) {
            throw new RuntimeException(\sprintf('A line of the Anthropic batch results does not decode to an object, "%s" given.', get_debug_type($decoded)));
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(JobHandle $handle): array
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/v1/messages/batches/%s', $this->baseUrl, urlencode($handle->getId())), [
            'headers' => $this->createHeaders(),
        ]);

        $this->throwOnHttpError($response);

        return $response->toArray(false);
    }

    /**
     * @return array<string, string>
     */
    private function createHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function toStatus(array $data): JobStatus
    {
        $raw = (string) ($data['processing_status'] ?? '');

        // Anthropic reports no batch-level error: a request that failed says so on its result line.
        return new JobStatus(self::STATES[$raw] ?? JobStateCase::UNKNOWN, $raw);
    }
}
