<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Voyage /v1/embeddings client (text-only).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingsClient implements EndpointClientInterface
{
    public const ENDPOINT = 'voyage.embeddings';

    private const URL = 'https://api.voyageai.com/v1/embeddings';

    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $response = $this->httpClient->request('POST', self::URL, [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                'input' => $payload,
                'input_type' => $options['input_type'] ?? null,
                'truncation' => $options['truncation'] ?? true,
                'output_dimension' => $options['dimensions'] ?? null,
                'encoding_format' => $options['encoding'] ?? null,
            ],
        ]);

        return new RawHttpResult($response);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        if (!isset($data['data'])) {
            throw new RuntimeException('Response does not contain embedding data.');
        }

        return new VectorResult(array_map(
            static fn (array $item): Vector => new Vector($item['embedding']),
            $data['data'],
        ));
    }
}
