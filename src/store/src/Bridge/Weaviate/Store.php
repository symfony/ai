<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Weaviate;

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
        private readonly string $collection,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        if ($this->collectionExists()) {
            return;
        }

        $this->request('POST', 'v1/schema', [
            'class' => $this->collection,
        ]);
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $this->request('POST', 'v1/batch/objects', [
            'fields' => [
                'ALL',
            ],
            'objects' => array_map($this->convertToIndexableArray(...), $documents),
        ]);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $whereConditions = array_map(static fn (string $id) => [
            'path' => ['id'],
            'operator' => 'Equal',
            'valueText' => $id,
        ], $ids);

        $this->request('DELETE', 'v1/batch/objects', [
            'match' => [
                'class' => $this->collection,
                'where' => 1 === \count($whereConditions) ? $whereConditions[0] : [
                    'operator' => 'Or',
                    'operands' => $whereConditions,
                ],
            ],
        ]);
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $vector = $query->getVector();
        $results = $this->request('POST', 'v1/graphql', [
            'query' => \sprintf('{
                Get {
                    %s (
                        nearVector: {
                            vector: [%s]
                        }
                    ) {
                        uuid,
                        vector,
                        _metadata
                    }
                }
            }', $this->collection, implode(', ', $vector->getData())),
        ]);

        foreach ($results['data']['Get'][$this->collection] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    public function drop(array $options = []): void
    {
        $this->request('DELETE', \sprintf('v1/schema/%s', $this->collection), []);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);

        $finalPayload = [
            'auth_bearer' => $this->apiKey,
        ];

        if ([] !== $payload) {
            $finalPayload['json'] = $payload;
        }

        $response = $this->httpClient->request($method, $url, $finalPayload);

        if (200 === $response->getStatusCode() && '' === $response->getContent(false)) {
            return [];
        }

        return $response->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return [
            'class' => $this->collection,
            'id' => $document->getId(),
            'vector' => $document->getVector()->getData(),
            'properties' => [
                'uuid' => $document->getId(),
                'vector' => $document->getVector()->getData(),
                '_metadata' => json_encode($document->getMetadata()->getArrayCopy()),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['uuid'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists('vector', $data) || null === $data['vector']
            ? new NullVector()
            : new Vector($data['vector']);

        return new VectorDocument($id, $vector, new Metadata(json_decode($data['_metadata'], true)));
    }

    private function collectionExists(): bool
    {
        $response = $this->request('GET', 'v1/schema', []);

        if (!isset($response['classes'])) {
            return false;
        }

        foreach ($response['classes'] as $class) {
            if (0 === strcasecmp($class['class'], $this->collection)) {
                return true;
            }
        }

        return false;
    }
}
