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

use Symfony\AI\Platform\Batch\BatchClientInterface;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchResult;
use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Polls status and streams results from the OpenAI Batch API.
 *
 * @see https://platform.openai.com/docs/api-reference/batch
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class ModelClient extends AbstractModelClient implements BatchClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
        self::validateApiKey($apiKey);
    }

    public function getBatch(string $batchId): BatchJob
    {
        $response = $this->httpClient->request('GET', self::getBaseUrl($this->region).'/v1/batches/'.$batchId, [
            'auth_bearer' => $this->apiKey,
        ]);

        return JobFactory::fromArray($response->toArray());
    }

    public function canFetchResults(BatchJob $job): bool
    {
        // Results become available as soon as the batch produced an output/error file, even when
        // it was cancelled or expired: the requests that did complete stay retrievable.
        return null !== $job->getOutputFileId() || null !== $job->getErrorFileId();
    }

    public function fetchResults(BatchJob $job): iterable
    {
        if (!$this->canFetchResults($job)) {
            throw new RuntimeException(\sprintf('Cannot fetch results for batch "%s": no output available (status: "%s").', $job->getId(), $job->getStatus()->value));
        }

        return $this->streamResults($job);
    }

    public function cancelBatch(string $batchId): BatchJob
    {
        $response = $this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/batches/'.$batchId.'/cancel', [
            'auth_bearer' => $this->apiKey,
        ]);

        return JobFactory::fromArray($response->toArray());
    }

    private function streamResults(BatchJob $job): \Generator
    {
        foreach ([$job->getOutputFileId(), $job->getErrorFileId()] as $fileId) {
            if (null === $fileId) {
                continue;
            }

            // Re-yield rather than `yield from` so keys stay unique across both files
            // (otherwise iterator_to_array() would collapse the two 0-indexed streams).
            foreach ($this->streamFile($fileId) as $result) {
                yield $result;
            }
        }
    }

    private function streamFile(string $fileId): \Generator
    {
        $response = $this->httpClient->request('GET', self::getBaseUrl($this->region).'/v1/files/'.$fileId.'/content', [
            'auth_bearer' => $this->apiKey,
        ]);

        $stream = StreamWrapper::createResource($response, $this->httpClient);

        try {
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
        } finally {
            fclose($stream);
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
                content: $this->extractText($body['output'] ?? []),
                inputTokens: $usage['input_tokens'] ?? 0,
                outputTokens: $usage['output_tokens'] ?? 0,
            );
        }

        // A failed request carries its message either in the response body (per-request 4xx/5xx)
        // or at the top level (error-file entries).
        $message = $resultResponse['body']['error']['message']
            ?? $data['error']['message']
            ?? \sprintf('HTTP %d', $statusCode);

        return BatchResult::failure(
            id: $customId,
            error: $message,
        );
    }

    /**
     * Concatenates the `output_text` parts of a Responses `output` array; refusals are surfaced as text.
     *
     * @param array<int, array<string, mixed>> $output
     */
    private function extractText(array $output): ?string
    {
        $text = '';
        foreach ($output as $item) {
            foreach ($item['content'] ?? [] as $part) {
                $type = $part['type'] ?? '';

                if ('output_text' === $type && isset($part['text'])) {
                    $text .= $part['text'];
                } elseif ('refusal' === $type && isset($part['refusal'])) {
                    $text .= \sprintf('Model refused to generate output: %s', $part['refusal']);
                }
            }
        }

        return '' === $text ? null : $text;
    }
}
