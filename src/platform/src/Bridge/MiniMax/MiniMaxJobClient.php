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

use Symfony\AI\Platform\Exception\JobFailedException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobStateCase;
use Symfony\AI\Platform\Job\JobStatus;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the asynchronous tasks MiniMax hands out for video generation and async speech synthesis.
 *
 * MiniMax answers such a request with a `task_id`, exposes the task under an endpoint-specific query
 * path, and delivers the payload as a file that has to be looked up and downloaded separately. Both
 * the query path and the expected MIME type are carried in the {@see JobHandle}, put there by
 * {@see MiniMaxResultConverter} which knows the endpoint the task came from.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MiniMaxJobClient implements JobClientInterface
{
    use HttpStatusErrorHandlingTrait;

    /**
     * The states MiniMax reports, lowercased. Anything else is left as
     * {@see JobStateCase::UNKNOWN} so a new provider state does not abort a running job.
     */
    private const STATES = [
        'queueing' => JobStateCase::QUEUED,
        'preparing' => JobStateCase::QUEUED,
        'processing' => JobStateCase::RUNNING,
        'success' => JobStateCase::SUCCEEDED,
        'fail' => JobStateCase::FAILED,
        'failed' => JobStateCase::FAILED,
        'expired' => JobStateCase::EXPIRED,
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.minimax.io/v1',
    ) {
    }

    public function supports(JobHandle $handle): bool
    {
        return \is_string($handle->get('query_path'));
    }

    public function getStatus(JobHandle $handle): JobStatus
    {
        $data = $this->query($handle);

        $raw = (string) ($data['status'] ?? '');
        $case = self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN;

        $error = $data['base_resp']['status_msg'] ?? null;

        return new JobStatus($case, $raw, \is_string($error) && '' !== $error ? $error : null);
    }

    public function getResult(JobHandle $handle): ResultInterface
    {
        $data = $this->query($handle);

        $raw = (string) ($data['status'] ?? '');
        $case = self::STATES[strtolower($raw)] ?? JobStateCase::UNKNOWN;

        if (JobStateCase::SUCCEEDED !== $case) {
            throw new JobFailedException(new JobStatus($case, $raw), \sprintf('The MiniMax task "%s" is not ready to be fetched, its status is "%s".', $handle->getId(), $raw));
        }

        // The file identifier can already be known from the submit response; the query response wins
        // when it carries one, since it is the more recent of the two.
        $fileId = $data['file_id'] ?? $handle->get('file_id');

        if (null === $fileId) {
            throw new RuntimeException(\sprintf('The MiniMax task "%s" did not return a file identifier.', $handle->getId()));
        }

        $payload = $this->download($fileId);

        if (\is_string($member = $handle->get('archive_member'))) {
            $payload = TarArchive::findByExtension($payload, $member)
                ?? throw new RuntimeException(\sprintf('The archive downloaded for the MiniMax task "%s" contains no "%s" file.', $handle->getId(), $member));
        }

        $mimeType = $handle->get('mime_type');

        return new BinaryResult($payload, \is_string($mimeType) ? $mimeType : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function query(JobHandle $handle): array
    {
        $queryPath = $handle->get('query_path');

        if (!\is_string($queryPath)) {
            throw new RuntimeException(\sprintf('The job handle "%s" does not carry a MiniMax query path.', $handle->getId()));
        }

        $response = $this->httpClient->request('GET', \sprintf('%s/%s?task_id=%s', $this->endpoint, $queryPath, urlencode($handle->getId())), [
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        return $response->toArray(false);
    }

    private function download(mixed $fileId): string
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/files/retrieve?file_id=%s', $this->endpoint, urlencode((string) $fileId)), [
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
