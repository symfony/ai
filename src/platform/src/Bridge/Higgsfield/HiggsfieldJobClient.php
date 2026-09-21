<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield;

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
 * Resolves the generation requests Higgsfield hands out, polling `/requests/{id}/status` and
 * downloading the media URL it carries once done.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HiggsfieldJobClient implements JobClientInterface
{
    use HttpStatusErrorHandlingTrait;

    /**
     * Generations run for minutes, so polling them every second only burns rate limit.
     */
    public const DEFAULT_POLL_INTERVAL = 5.0;

    public const DEFAULT_MAX_DURATION = 900;

    /**
     * Anything else stays {@see JobStateCase::UNKNOWN}, so a new provider state does not abort a job.
     */
    private const STATES = [
        'queued' => JobStateCase::QUEUED,
        'in_progress' => JobStateCase::RUNNING,
        'completed' => JobStateCase::SUCCEEDED,
        'failed' => JobStateCase::FAILED,
        // The generation ran, the provider refuses to deliver what came out of it.
        'nsfw' => JobStateCase::FAILED,
        'canceled' => JobStateCase::CANCELED,
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(JobHandle $handle): bool
    {
        return \is_string($handle->get('status_path'));
    }

    public function getStatus(JobHandle $handle): JobStatus
    {
        $data = $this->query($handle);

        $raw = (string) ($data['status'] ?? '');
        $case = self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN;

        return new JobStatus($case, $raw, $case->isTerminal() && JobStateCase::SUCCEEDED !== $case ? self::extractError($data) : null);
    }

    public function getResult(JobHandle $handle): ResultInterface
    {
        $data = $this->query($handle);

        $raw = (string) ($data['status'] ?? '');
        $case = self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN;

        if (JobStateCase::SUCCEEDED !== $case) {
            throw new JobFailedException(new JobStatus($case, $raw), \sprintf('The Higgsfield request "%s" is not ready to be fetched, its status is "%s".', $handle->getId(), $raw));
        }

        $response = $this->httpClient->request('GET', self::extractMediaUrl($data));

        $this->throwOnError($response);

        return new BinaryResult($response->getContent(), $response->getHeaders()['content-type'][0] ?? null);
    }

    /**
     * The message Higgsfield put into a response, wherever it put it.
     *
     * @param array<string, mixed> $data
     */
    public static function extractError(array $data): ?string
    {
        foreach (['detail', 'error', 'message'] as $key) {
            if (isset($data[$key]) && \is_string($data[$key])) {
                return $data[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function query(JobHandle $handle): array
    {
        $statusPath = $handle->get('status_path');

        if (!\is_string($statusPath)) {
            throw new RuntimeException(\sprintf('The job handle "%s" does not carry a Higgsfield status path.', $handle->getId()));
        }

        $response = $this->httpClient->request('GET', '/'.$statusPath);

        $this->throwOnError($response);

        return $response->toArray(false);
    }

    /**
     * Beyond the statuses the shared handling knows, any other error - an exhausted balance, a refused
     * request - would otherwise read as an unknown state and be polled until the budget runs out.
     */
    private function throwOnError(ResponseInterface $response): void
    {
        $this->throwOnHttpError($response);

        if (400 <= $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Higgsfield API error (HTTP %d): "%s".', $response->getStatusCode(), $this->extractErrorMessage($response) ?? 'Unknown error'));
        }
    }

    /**
     * Overrides the shared lookup: Higgsfield puts its message into `detail` rather than `error.message`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            return self::extractError($response->toArray(false));
        } catch (DecodingExceptionInterface) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractMediaUrl(array $data): string
    {
        if (isset($data['video']['url']) && \is_string($data['video']['url'])) {
            return $data['video']['url'];
        }

        if (isset($data['images'][0]['url']) && \is_string($data['images'][0]['url'])) {
            return $data['images'][0]['url'];
        }

        throw new RuntimeException('The Higgsfield response does not contain any media URL.');
    }
}
