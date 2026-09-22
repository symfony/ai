<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Video;

use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the video generation jobs started through the `POST /api/v1/videos` endpoint.
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class JobClient implements JobClientInterface
{
    use HttpStatusErrorHandlingTrait;

    public const TYPE = 'openrouter-video';

    /**
     * The states OpenRouter reports. Anything else is left as {@see JobStateCase::UNKNOWN} so a new
     * provider state does not abort a running job.
     */
    private const STATES = [
        'pending' => JobStateCase::QUEUED,
        'queued' => JobStateCase::QUEUED,
        'in_progress' => JobStateCase::RUNNING,
        'completed' => JobStateCase::SUCCEEDED,
        'failed' => JobStateCase::FAILED,
        'cancelled' => JobStateCase::CANCELED,
        'expired' => JobStateCase::EXPIRED,
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'https://openrouter.ai/api',
    ) {
    }

    public function supports(JobHandle $handle): bool
    {
        return self::TYPE === $handle->get('type');
    }

    public function getStatus(JobHandle $handle): JobStatus
    {
        return $this->toStatus($this->query($handle));
    }

    public function getResult(JobHandle $handle): ResultInterface
    {
        $data = $this->query($handle);
        $status = $this->toStatus($data);

        if (!$status->is(JobStateCase::SUCCEEDED)) {
            throw new JobFailedException($status, \sprintf('The OpenRouter video job "%s" is not ready to be fetched, its status is "%s".', $handle->getId(), $status->getRaw()));
        }

        $contentUrl = $data['unsigned_urls'][0] ?? null;

        if (!\is_string($contentUrl) || '' === $contentUrl) {
            $contentUrl = \sprintf('%s/v1/videos/%s/content?index=0', $this->baseUrl, rawurlencode($handle->getId()));
        }

        $response = $this->httpClient->request('GET', $contentUrl, [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        return new BinaryResult($response->getContent(), $response->getHeaders()['content-type'][0] ?? 'video/mp4');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function toStatus(array $data): JobStatus
    {
        $raw = (string) ($data['status'] ?? '');
        $case = self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN;

        $error = $data['error'] ?? null;
        if (\is_array($error)) {
            $error = $error['message'] ?? null;
        }

        return new JobStatus($case, $raw, \is_string($error) && '' !== $error ? $error : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function query(JobHandle $handle): array
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/v1/videos/%s', $this->baseUrl, rawurlencode($handle->getId())), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        return $response->toArray(false);
    }
}
