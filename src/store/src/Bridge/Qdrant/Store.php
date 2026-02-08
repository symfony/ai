<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Qdrant;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $collectionName,
        private readonly int $embeddingsDimension = 1536,
        private readonly string $embeddingsDistance = 'Cosine',
        private readonly bool $async = false,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $collectionExistResponse = $this->request('GET', \sprintf('collections/%s/exists', $this->collectionName));

        if ($collectionExistResponse['result']['exists']) {
            return;
        }

        $this->request('PUT', \sprintf('collections/%s', $this->collectionName), [
            'vectors' => [
                'size' => $this->embeddingsDimension,
                'distance' => $this->embeddingsDistance,
            ],
        ]);
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $this->request(
            'PUT',
            \sprintf('collections/%s/points', $this->collectionName),
            [
                'points' => array_map($this->convertToIndexableArray(...), $documents),
            ],
            ['wait' => $this->async ? 'false' : 'true'],
        );
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        $this->request(
            'POST',
            \sprintf('collections/%s/points/delete', $this->collectionName),
            [
                'points' => $ids,
            ],
            ['wait' => $this->async ? 'false' : 'true'],
        );
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{
     *     filter?: array<string, mixed>,
     *     limit?: positive-int,
     *     offset?: positive-int
     * } $options
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $payload = [
            'query' => $query->getVector()->getData(),
            'with_payload' => true,
            'with_vector' => true,
        ];

        if (isset($options['filter'])) {
            $payload['filter'] = $options['filter'];
        }

        if (\array_key_exists('limit', $options)) {
            $payload['limit'] = $options['limit'];
        }

        if (\array_key_exists('offset', $options)) {
            $payload['offset'] = $options['offset'];
        }

        $response = $this->request('POST', \sprintf('collections/%s/points/query', $this->collectionName), $payload);

        foreach ($response['result']['points'] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    public function drop(array $options = []): void
    {
        $this->request('DELETE', \sprintf('collections/%s', $this->collectionName));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $queryParameters
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = [], array $queryParameters = []): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);

        $response = $this->httpClient->request($method, $url, [
            'headers' => [
                'api-key' => $this->apiKey,
            ],
            'query' => $queryParameters,
            'json' => $payload,
        ]);

        return $response->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return [
            'id' => $document->getId(),
            'vector' => $document->getVector()->getData(),
            'payload' => $document->getMetadata()->getArrayCopy(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists('vector', $data) || null === $data['vector']
            ? new NullVector()
            : new Vector($data['vector']);

        return new VectorDocument(
            id: $id,
            vector: $vector,
            metadata: new Metadata($data['payload']),
            score: $data['score'] ?? null
        );
    }
}
