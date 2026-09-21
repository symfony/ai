<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Resolves the video generations Venice queues.
 *
 * `video/retrieve` doubles as status and download - it answers JSON while the generation runs, and
 * the video once it is done - and takes the model next to the queue id, so the handle carries both,
 * the model as `queue_model`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VeniceJobClient implements JobClientInterface
{
    use HttpStatusErrorHandlingTrait;

    public const DEFAULT_MAX_DURATION = 600;

    /**
     * Venice reports an `average_execution_time` in the tens of seconds, so asking every second
     * would spend dozens of requests on a generation that cannot be done yet.
     */
    public const DEFAULT_POLL_INTERVAL = 3.0;

    /**
     * Anything else stays {@see JobStateCase::UNKNOWN}, so a new provider state does not abort a job.
     */
    private const STATES = [
        'pending' => JobStateCase::QUEUED,
        'queued' => JobStateCase::QUEUED,
        'processing' => JobStateCase::RUNNING,
        'completed' => JobStateCase::SUCCEEDED,
        'failed' => JobStateCase::FAILED,
        'canceled' => JobStateCase::CANCELED,
    ];

    /**
     * Venice reports a finished generation by delivering the video instead of a status word.
     */
    private const COMPLETED = 'COMPLETED';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(JobHandle $handle): bool
    {
        return \is_string($handle->get('queue_model'));
    }

    public function getStatus(JobHandle $handle): JobStatus
    {
        $response = $this->retrieve($handle);

        // The video itself, not a status report: reading the body would download it for nothing.
        if (!self::isJson($response)) {
            return new JobStatus(JobStateCase::SUCCEEDED, self::COMPLETED);
        }

        $data = $response->toArray(false);

        $raw = self::rawStatus($data);
        $error = $data['error'] ?? $data['message'] ?? null;

        return new JobStatus(self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN, $raw, \is_string($error) && '' !== $error ? $error : null);
    }

    public function getResult(JobHandle $handle): ResultInterface
    {
        $response = $this->retrieve($handle);

        if (!self::isJson($response)) {
            return new BinaryResult($response->getContent(), $response->getHeaders()['content-type'][0] ?? null);
        }

        $raw = self::rawStatus($response->toArray(false));

        throw new JobFailedException(new JobStatus(self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN, $raw), \sprintf('The Venice video generation "%s" is not ready to be fetched, its status is "%s".', $handle->getId(), $raw));
    }

    private function retrieve(JobHandle $handle): ResponseInterface
    {
        $model = $handle->get('queue_model');

        if (!\is_string($model)) {
            throw new RuntimeException(\sprintf('The job handle "%s" does not carry the Venice model the generation was queued for.', $handle->getId()));
        }

        $response = $this->httpClient->request('POST', 'video/retrieve', [
            'json' => [
                'model' => $model,
                'queue_id' => $handle->getId(),
            ],
        ]);

        $this->throwOnHttpError($response);

        // Beyond the statuses the shared handling knows, any other error - an exhausted balance, a
        // refused request - would otherwise read as an unknown state and be polled until the budget runs out.
        if (400 <= $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Venice API error (HTTP %d): "%s".', $response->getStatusCode(), $this->extractErrorMessage($response) ?? 'Unknown error'));
        }

        return $response;
    }

    /**
     * Overrides the shared lookup: Venice may answer a plain `error` string next to `error.message`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        $error = $data['error'] ?? null;

        if (\is_array($error)) {
            $error = $error['message'] ?? null;
        }

        foreach ([$error, $data['message'] ?? null] as $message) {
            if (\is_string($message) && '' !== $message) {
                return $message;
            }
        }

        return null;
    }

    private static function isJson(ResponseInterface $response): bool
    {
        return str_contains($response->getHeaders(false)['content-type'][0] ?? '', 'application/json');
    }

    /**
     * @param array<array-key, mixed> $data the decoded `video/retrieve` payload
     */
    private static function rawStatus(array $data): string
    {
        $status = $data['status'] ?? null;

        return \is_string($status) ? $status : '';
    }
}
