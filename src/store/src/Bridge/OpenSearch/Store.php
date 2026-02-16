<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\OpenSearch;

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
        private readonly string $endpoint,
        private readonly string $indexName,
        private readonly string $vectorsField = '_vectors',
        private readonly int $dimensions = 1536,
        private readonly string $spaceType = 'l2',
    ) {
    }

    public function setup(array $options = []): void
    {
        $indexExistResponse = $this->httpClient->request('HEAD', \sprintf('%s/%s', $this->endpoint, $this->indexName));

        if (200 === $indexExistResponse->getStatusCode()) {
            return;
        }

        $this->request('PUT', $this->indexName, [
            'settings' => [
                'index.knn' => true,
            ],
            'mappings' => [
                'properties' => [
                    $this->vectorsField => [
                        'type' => 'knn_vector',
                        'dimension' => $options['dimensions'] ?? $this->dimensions,
                        'space_type' => $options['space_type'] ?? $this->spaceType,
                    ],
                ],
            ],
        ]);
    }

    public function drop(array $options = []): void
    {
        $indexExistResponse = $this->httpClient->request('HEAD', \sprintf('%s/%s', $this->endpoint, $this->indexName));

        if (404 === $indexExistResponse->getStatusCode()) {
            throw new InvalidArgumentException(\sprintf('The index "%s" does not exist.', $this->indexName));
        }

        $this->request('DELETE', $this->indexName);
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $documentToIndex = fn (VectorDocument $document): array => [
            'index' => [
                '_index' => $this->indexName,
                '_id' => $document->getId(),
            ],
        ];

        $documentToPayload = fn (VectorDocument $document): array => [
            $this->vectorsField => $document->getVector()->getData(),
            'metadata' => json_encode($document->getMetadata()->getArrayCopy()),
        ];

        $this->request('POST', '_bulk', static function () use ($documents, $documentToIndex, $documentToPayload) {
            foreach ($documents as $document) {
                yield json_encode($documentToIndex($document)).\PHP_EOL.json_encode($documentToPayload($document)).\PHP_EOL;
            }
        });
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $this->request('POST', '_bulk', function () use ($ids) {
            foreach ($ids as $id) {
                yield json_encode([
                    'delete' => [
                        '_index' => $this->indexName,
                        '_id' => $id,
                    ],
                ]).\PHP_EOL;
            }
        });
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
        $documents = $this->request('POST', \sprintf('%s/_search', $this->indexName), [
            'size' => $options['size'] ?? 100,
            'query' => [
                'knn' => [
                    $this->vectorsField => [
                        'vector' => $vector->getData(),
                        'k' => $options['k'] ?? 100,
                    ],
                ],
            ],
        ]);

        foreach ($documents['hits']['hits'] as $document) {
            yield $this->convertToVectorDocument($document);
        }
    }

    /**
     * @param \Closure|array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, \Closure|array $payload = []): array
    {
        $finalOptions = [];

        if (\is_array($payload) && [] !== $payload) {
            $finalOptions['json'] = $payload;
        }

        if ($payload instanceof \Closure) {
            $finalOptions = [
                'headers' => [
                    'Content-Type' => 'application/x-ndjson',
                ],
                'body' => $payload(),
            ];
        }

        $response = $this->httpClient->request($method, \sprintf('%s/%s', $this->endpoint, $path), $finalOptions);

        return $response->toArray();
    }

    /**
     * @param array{
     *     '_id'?: string,
     *     '_source': array<string, mixed>,
     *     '_score': float,
     * } $document
     */
    private function convertToVectorDocument(array $document): VectorDocument
    {
        $id = $document['_id'] ?? throw new InvalidArgumentException('Missing "_id" field in the document data.');

        $vector = !\array_key_exists($this->vectorsField, $document['_source']) || null === $document['_source'][$this->vectorsField]
            ? new NullVector()
            : new Vector($document['_source'][$this->vectorsField]);

        return new VectorDocument($id, $vector, new Metadata(json_decode($document['_source']['metadata'], true)), $document['_score'] ?? null);
    }
}
