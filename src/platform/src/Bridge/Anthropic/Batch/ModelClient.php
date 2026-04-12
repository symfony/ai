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

use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchResult;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\BatchClientInterface;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://docs.anthropic.com/en/api/creating-message-batches
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class ModelClient implements BatchClientInterface
{
    private const BASE_URL = 'https://api.anthropic.com/v1/messages/batches';
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Claude && $model->supports(Capability::BATCH);
    }

    public function submitBatch(Model $model, iterable $requests, array $options = []): BatchJob
    {
        $response = $this->httpClient->request('POST', self::BASE_URL, [
            'headers' => array_merge($this->getHeaders(), ['Content-Type' => 'application/json']),
            'body' => $this->streamBody($model, $requests, $options),
        ]);

        return $this->jobFromArray($response->toArray());
    }

    public function getBatch(string $batchId): BatchJob
    {
        $response = $this->httpClient->request('GET', self::BASE_URL.'/'.$batchId, [
            'headers' => $this->getHeaders(),
        ]);

        return $this->jobFromArray($response->toArray());
    }

    public function fetchResults(BatchJob $job): iterable
    {
        if (!$job->isComplete()) {
            throw new RuntimeException(\sprintf('Cannot fetch results for batch "%s": job is not complete (status: "%s").', $job->getId(), $job->getStatus()->value));
        }

        return $this->streamResults($job);
    }

    public function cancelBatch(string $batchId): BatchJob
    {
        $response = $this->httpClient->request('POST', self::BASE_URL.'/'.$batchId.'/cancel', [
            'headers' => $this->getHeaders(),
        ]);

        return $this->jobFromArray($response->toArray());
    }

    /**
     * Streams requests as a JSON array body to avoid loading all requests in memory.
     *
     * @param iterable<array{id: string, payload: array<string, mixed>}> $requests
     * @param array<string, mixed>                                       $options
     */
    private function streamBody(Model $model, iterable $requests, array $options): \Generator
    {
        $first = true;
        foreach ($requests as $request) {
            yield $first ? '{"requests":[' : ',';
            yield json_encode([
                'custom_id' => $request['id'],
                'params' => array_merge(['model' => $model->getName()], $options, $request['payload']),
            ], \JSON_THROW_ON_ERROR);
            $first = false;
        }
        if ($first) {
            throw new RuntimeException('Cannot submit an empty batch.');
        }
        yield ']}';
    }

    private function streamResults(BatchJob $job): \Generator
    {
        $response = $this->httpClient->request('GET', self::BASE_URL.'/'.$job->getId().'/results', [
            'headers' => $this->getHeaders(),
        ]);

        $stream = StreamWrapper::createResource($response, $this->httpClient);

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
        $result = $data['result'] ?? [];
        $type = $result['type'] ?? '';

        if ('succeeded' === $type) {
            $message = $result['message'] ?? [];
            $content = $message['content'][0]['text'] ?? null;
            $usage = $message['usage'] ?? [];

            return BatchResult::success(
                id: $customId,
                content: $content,
                inputTokens: $usage['input_tokens'] ?? 0,
                outputTokens: $usage['output_tokens'] ?? 0,
            );
        }

        $error = $result['error'] ?? [];

        return BatchResult::failure(
            id: $customId,
            error: $error['message'] ?? $type,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jobFromArray(array $data): BatchJob
    {
        $counts = $data['request_counts'] ?? [];
        $status = match (ProcessingStatus::from($data['processing_status'] ?? '')) {
            ProcessingStatus::ENDED => ($counts['canceled'] ?? 0) > 0 ? BatchStatus::CANCELLED : BatchStatus::COMPLETED,
            ProcessingStatus::CANCELING => BatchStatus::PROCESSING,
            ProcessingStatus::IN_PROGRESS => BatchStatus::PROCESSING,
        };

        return new BatchJob(
            id: $data['id'],
            status: $status,
            totalCount: (int) array_sum($counts),
            processedCount: ($counts['succeeded'] ?? 0) + ($counts['errored'] ?? 0) + ($counts['canceled'] ?? 0) + ($counts['expired'] ?? 0),
            failedCount: $counts['errored'] ?? 0,
        );
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ];
    }
}
