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

use Symfony\AI\Platform\Batch\BatchSubmitClientInterface;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Streams the request body as a JSON array to create a Message Batch.
 *
 * @see https://docs.anthropic.com/en/api/creating-message-batches
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class SubmitModelClient implements BatchSubmitClientInterface
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

    public function submitBatch(Model $model, iterable $requests, array $options = []): RawResultInterface
    {
        $response = $this->httpClient->request('POST', self::BASE_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'Content-Type' => 'application/json',
            ],
            'body' => $this->streamBody($model, $requests, $options),
        ]);

        return new RawHttpResult($response);
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
}
