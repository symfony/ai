<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Elasticsearch;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $indexName,
        private readonly string $vectorsField = '_vectors',
        private readonly int $dimensions = 1536,
        private readonly string $similarity = 'cosine',
    ) {
    }

    public function setup(array $options = []): void
    {
        $indexExistResponse = $this->httpClient->request('HEAD', $this->indexName);

        if (200 === $indexExistResponse->getStatusCode()) {
            return;
        }

        $this->request('PUT', $this->indexName, [
            'mappings' => [
                'properties' => [
                    $this->vectorsField => [
                        'type' => 'dense_vector',
                        'dims' => $options['dimensions'] ?? $this->dimensions,
                        'similarity' => $options['similarity'] ?? $this->similarity,
                    ],
                ],
            ],
        ]);
    }

    public function drop(array $options = []): void
    {
        $indexExistResponse = $this->httpClient->request('HEAD', $this->indexName);

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

        $documentToDelete = fn (string $id): array => [
            'delete' => [
                '_index' => $this->indexName,
                '_id' => $id,
            ],
        ];

        $this->request('POST', '_bulk', static function () use ($ids, $documentToDelete) {
            foreach ($ids as $id) {
                yield json_encode($documentToDelete($id)).\PHP_EOL;
            }
        });
    }

    public function clear(array $options = []): void
    {
        // "conflicts=proceed" keeps a concurrent write from aborting the deletion halfway through,
        // which would leave the store with an arbitrary subset of its documents removed
        $result = $this->request('POST', \sprintf('%s/_delete_by_query?refresh=true&conflicts=proceed', $this->indexName), [
            'query' => [
                'match_all' => new \stdClass(),
            ],
        ]);

        // a delete-by-query is answered with 200 even when deleting individual documents failed
        if (\is_array($result['failures'] ?? null) && [] !== $result['failures']) {
            throw new RuntimeException(\sprintf('Failed to delete %d document(s) while clearing the "%s" index.', \count($result['failures']), $this->indexName));
        }
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

        $k = $options['k'] ?? 100;
        if (!\is_int($k)) {
            throw new InvalidArgumentException('The "k" option must be an integer.');
        }

        $numCandidates = $options['num_candidates'] ?? max($k * 2, 100);
        if (!\is_int($numCandidates)) {
            throw new InvalidArgumentException('The "num_candidates" option must be an integer.');
        }

        $documents = $this->request('POST', \sprintf('%s/_search', $this->indexName), [
            'knn' => [
                'field' => $this->vectorsField,
                'query_vector' => $vector->getData(),
                'k' => $k,
                'num_candidates' => $numCandidates,
            ],
        ]);

        $hits = $documents['hits'] ?? null;
        if (!\is_array($hits)) {
            throw new RuntimeException('The Elasticsearch search response is malformed.');
        }

        $innerHits = $hits['hits'] ?? null;
        if (!\is_array($innerHits)) {
            throw new RuntimeException('The Elasticsearch search response does not contain a result set.');
        }

        foreach ($innerHits as $document) {
            if (!\is_array($document)) {
                throw new RuntimeException('The Elasticsearch search response contains an invalid hit.');
            }

            yield $this->convertToVectorDocument($document);
        }
    }

    /**
     * @param \Closure|array<string, mixed> $payload
     *
     * @return array<mixed>
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

        $response = $this->httpClient->request($method, $path, $finalOptions);

        return $response->toArray();
    }

    /**
     * @param array<mixed> $document
     */
    private function convertToVectorDocument(array $document): VectorDocument
    {
        $id = $document['_id'] ?? throw new InvalidArgumentException('Missing "_id" field in the document data.');
        if (!\is_string($id)) {
            throw new InvalidArgumentException('The document "_id" field must be a string.');
        }

        $source = $document['_source'] ?? null;
        if (!\is_array($source)) {
            throw new InvalidArgumentException('Missing "_source" field in the document data.');
        }

        $rawVector = $source[$this->vectorsField] ?? null;
        if (null === $rawVector) {
            $vector = new NullVector();
        } else {
            if (!\is_array($rawVector)) {
                throw new InvalidArgumentException('The document vector must be an array of numbers.');
            }

            $components = [];
            foreach ($rawVector as $component) {
                if (!\is_int($component) && !\is_float($component)) {
                    throw new InvalidArgumentException('The document vector must contain only numbers.');
                }

                $components[] = (float) $component;
            }

            $vector = new Vector($components);
        }

        $rawMetadata = $source['metadata'] ?? null;
        if (!\is_string($rawMetadata)) {
            throw new InvalidArgumentException('The document metadata must be a JSON encoded string.');
        }

        $metadata = json_decode($rawMetadata, true);
        if (!\is_array($metadata)) {
            throw new InvalidArgumentException('The document metadata is not a valid JSON object.');
        }

        $normalizedMetadata = [];
        foreach ($metadata as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('The document metadata must be keyed by strings.');
            }

            $normalizedMetadata[$key] = $value;
        }

        $score = $document['_score'] ?? null;
        if (null !== $score && !\is_int($score) && !\is_float($score)) {
            throw new InvalidArgumentException('The document "_score" field must be a number.');
        }

        return new VectorDocument(
            Uuid::fromString($id),
            $vector,
            new Metadata($normalizedMetadata),
            null === $score ? null : (float) $score,
        );
    }
}
