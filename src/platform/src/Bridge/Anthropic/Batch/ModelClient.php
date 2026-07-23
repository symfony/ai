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

use Symfony\AI\Platform\Batch\BatchClientInterface;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Polls status and streams results from the Anthropic Message Batches API.
 *
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

    public function getBatch(string $batchId): BatchJob
    {
        $response = $this->httpClient->request('GET', self::BASE_URL.'/'.$batchId, [
            'headers' => $this->getHeaders(),
        ]);

        return JobFactory::fromArray($response->toArray());
    }

    public function canFetchResults(BatchJob $job): bool
    {
        // Anthropic exposes the results stream as soon as the batch has ended, even when it
        // was cancelled or expired: the requests that did complete remain retrievable.
        return $job->isTerminal();
    }

    public function fetchResults(BatchJob $job): iterable
    {
        if (!$this->canFetchResults($job)) {
            throw new RuntimeException(\sprintf('Cannot fetch results for batch "%s": job has not ended (status: "%s").', $job->getId(), $job->getStatus()->value));
        }

        return $this->streamResults($job);
    }

    public function cancelBatch(string $batchId): BatchJob
    {
        $response = $this->httpClient->request('POST', self::BASE_URL.'/'.$batchId.'/cancel', [
            'headers' => $this->getHeaders(),
        ]);

        return JobFactory::fromArray($response->toArray());
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
            $usage = $message['usage'] ?? [];

            return BatchResult::success(
                id: $customId,
                content: $this->extractText($message['content'] ?? []),
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
     * Concatenates the text blocks of a message, ignoring non-text blocks (thinking, tool_use).
     *
     * @param array<int, array<string, mixed>> $content
     */
    private function extractText(array $content): ?string
    {
        $text = '';
        foreach ($content as $block) {
            if ('text' === ($block['type'] ?? '') && isset($block['text'])) {
                $text .= $block['text'];
            }
        }

        return '' === $text ? null : $text;
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
