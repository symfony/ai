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

use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchResult;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\BatchClientInterface;
use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Uploads a JSONL file to the Files API, then creates a batch job referencing it.
 *
 * @see https://platform.openai.com/docs/api-reference/batch
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class ModelClient extends AbstractModelClient implements BatchClientInterface
{
    private const CHAT_ENDPOINT = '/v1/chat/completions';
    private const COMPLETION_WINDOW = '24h';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
        self::validateApiKey($apiKey);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gpt && $model->supports(Capability::BATCH);
    }

    public function submitBatch(Model $model, iterable $requests, array $options = []): BatchJob
    {
        $stream = fopen('php://temp', 'r+');

        try {
            foreach ($requests as $request) {
                fwrite($stream, json_encode([
                    'custom_id' => $request['id'],
                    'method' => 'POST',
                    'url' => self::CHAT_ENDPOINT,
                    'body' => array_merge(['model' => $model->getName()], $options, $request['payload']),
                ])."\n");
            }

            if (0 === ftell($stream)) {
                throw new RuntimeException('Cannot submit an empty batch.');
            }

            rewind($stream);

            $inputFileId = $this->uploadFile($stream);
        } finally {
            fclose($stream);
        }

        $response = $this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/batches', [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'input_file_id' => $inputFileId,
                'endpoint' => self::CHAT_ENDPOINT,
                'completion_window' => self::COMPLETION_WINDOW,
            ],
        ]);

        return $this->jobFromArray($response->toArray());
    }

    public function getBatch(string $batchId): BatchJob
    {
        $response = $this->httpClient->request('GET', self::getBaseUrl($this->region).'/v1/batches/'.$batchId, [
            'auth_bearer' => $this->apiKey,
        ]);

        return $this->jobFromArray($response->toArray());
    }

    public function fetchResults(BatchJob $job): iterable
    {
        if (!$job->isComplete()) {
            throw new RuntimeException(\sprintf('Cannot fetch results for batch "%s": job is not complete (status: %s).', $job->getId(), $job->getStatus()->value));
        }

        if (null === $job->getOutputFileId()) {
            throw new RuntimeException(\sprintf('Batch "%s" has no output file.', $job->getId()));
        }

        return $this->streamResults($job);
    }

    public function cancelBatch(string $batchId): BatchJob
    {
        $response = $this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/batches/'.$batchId.'/cancel', [
            'auth_bearer' => $this->apiKey,
        ]);

        return $this->jobFromArray($response->toArray());
    }

    private function streamResults(BatchJob $job): \Generator
    {
        $response = $this->httpClient->request('GET', self::getBaseUrl($this->region).'/v1/files/'.$job->getOutputFileId().'/content', [
            'auth_bearer' => $this->apiKey,
        ]);

        $stream = $response->toStream();

        while (!feof($stream)) {
            $line = fgets($stream);

            if (false === $line) {
                break;
            }

            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $data = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);

            if (!\is_array($data)) {
                continue;
            }

            yield $this->resultFromArray($data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resultFromArray(array $data): BatchResult
    {
        $customId = $data['custom_id'] ?? '';
        $resultResponse = $data['response'] ?? [];
        $statusCode = $resultResponse['status_code'] ?? 0;

        if (200 === $statusCode) {
            $body = $resultResponse['body'] ?? [];
            $usage = $body['usage'] ?? [];

            return BatchResult::success(
                id: $customId,
                content: $body['choices'][0]['message']['content'] ?? null,
                inputTokens: $usage['prompt_tokens'] ?? 0,
                outputTokens: $usage['completion_tokens'] ?? 0,
            );
        }

        $error = $data['error'] ?? [];

        return BatchResult::failure(
            id: $customId,
            error: $error['message'] ?? \sprintf('HTTP %d', $statusCode),
        );
    }

    /**
     * @param resource $stream
     */
    private function uploadFile($stream): string
    {
        $response = $this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/files', [
            'auth_bearer' => $this->apiKey,
            'body' => [
                'purpose' => 'batch',
                'file' => $stream,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['id'])) {
            throw new RuntimeException('Failed to upload batch input file: missing file ID in response.');
        }

        return $data['id'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jobFromArray(array $data): BatchJob
    {
        $status = match (JobStatus::from($data['status'] ?? '')) {
            JobStatus::VALIDATING, JobStatus::IN_PROGRESS, JobStatus::FINALIZING, JobStatus::CANCELLING => BatchStatus::PROCESSING,
            JobStatus::COMPLETED => BatchStatus::COMPLETED,
            JobStatus::FAILED => BatchStatus::FAILED,
            JobStatus::CANCELLED => BatchStatus::CANCELLED,
            JobStatus::EXPIRED => BatchStatus::EXPIRED,
        };

        $counts = $data['request_counts'] ?? [];

        return new BatchJob(
            id: $data['id'],
            status: $status,
            totalCount: $counts['total'] ?? 0,
            processedCount: ($counts['completed'] ?? 0) + ($counts['failed'] ?? 0),
            failedCount: $counts['failed'] ?? 0,
            outputFileId: $data['output_file_id'] ?? null,
            errorFileId: $data['error_file_id'] ?? null,
        );
    }
}
