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
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
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
    /**
     * @param HttpClientInterface $httpClient HTTP client scoped to the OpenSearch instance, see {@see StoreFactory}
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $indexName,
        private readonly string $vectorsField = '_vectors',
        private readonly int $dimensions = 1536,
        private readonly string $spaceType = 'l2',
    ) {
    }

    public function setup(array $options = []): void
    {
        $indexExistResponse = $this->httpClient->request('HEAD', $this->indexName);

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
        $indexExistResponse = $this->httpClient->request('HEAD', $this->indexName);

        if (404 === $indexExistResponse->getStatusCode()) {
            throw new InvalidArgumentException(\sprintf('The index "%s" does not exist.', $this->indexName));
        }

        $this->request('DELETE', $this->indexName);
    }

    public function count(): int
    {
        $result = $this->request('GET', \sprintf('%s/_count', $this->indexName));

        return $result['count'] ?? 0;
    }

    public function add(VectorDocumentInterface|array $documents): void
    {
        if ($documents instanceof VectorDocumentInterface) {
            $documents = [$documents];
        }

        $documentToIndex = fn (VectorDocumentInterface $document): array => [
            'index' => [
                '_index' => $this->indexName,
                '_id' => $document->getId(),
            ],
        ];

        $documentToPayload = fn (VectorDocumentInterface $document): array => [
            $this->vectorsField => $document->getVector()->getData(),
            'metadata' => json_encode($document->getMetadata()->getArrayCopy()),
        ];

        $result = $this->request('POST', '_bulk', static function () use ($documents, $documentToIndex, $documentToPayload) {
            foreach ($documents as $document) {
                yield json_encode($documentToIndex($document)).\PHP_EOL.json_encode($documentToPayload($document)).\PHP_EOL;
            }
        });

        $this->assertBulkSucceeded($result);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $result = $this->request('POST', '_bulk', function () use ($ids) {
            foreach ($ids as $id) {
                yield json_encode([
                    'delete' => [
                        '_index' => $this->indexName,
                        '_id' => $id,
                    ],
                ]).\PHP_EOL;
            }
        });

        $this->assertBulkSucceeded($result);
    }

    public function clear(array $options = []): void
    {
        // "conflicts=proceed" keeps a concurrent write from aborting the deletion halfway through, and
        // the scroll size is raised since OpenSearch defaults to 100, which means a lot of round trips
        $result = $this->request('POST', \sprintf('%s/_delete_by_query?refresh=true&conflicts=proceed&scroll_size=1000', $this->indexName), [
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

        $response = $this->httpClient->request($method, $path, $finalOptions);

        return $response->toArray();
    }

    /**
     * A bulk request is answered with 200 even when individual operations failed.
     *
     * @param array{
     *     errors?: bool,
     *     items?: list<array<string, array{
     *         _id?: string,
     *         error?: array{type?: string, reason?: string, caused_by?: array{type?: string, reason?: string}},
     *     }>>,
     * } $result
     */
    private function assertBulkSucceeded(array $result): void
    {
        if (true !== ($result['errors'] ?? false)) {
            return;
        }

        $items = $result['items'] ?? [];
        $failures = [];
        foreach ($items as $item) {
            $operation = current($item);
            if (false !== $operation && isset($operation['error'])) {
                $failures[] = $operation;
            }
        }

        $message = \sprintf('Failed to process %d of %d bulk operation(s) on the "%s" index', \count($failures), \count($items), $this->indexName);

        if ([] !== $failures) {
            $error = $failures[0]['error'];
            $message .= \sprintf(', first error for document "%s": [%s] %s', $failures[0]['_id'] ?? '', $error['type'] ?? 'unknown', $error['reason'] ?? 'no reason given');

            if (isset($error['caused_by'])) {
                $message .= \sprintf(' (caused by [%s] %s)', $error['caused_by']['type'] ?? 'unknown', $error['caused_by']['reason'] ?? 'no reason given');
            }
        }

        throw new RuntimeException($message);
    }

    /**
     * @param array{
     *     '_id'?: string,
     *     '_source': array<string, mixed>,
     *     '_score': float,
     * } $document
     */
    private function convertToVectorDocument(array $document): VectorDocumentInterface
    {
        $id = $document['_id'] ?? throw new InvalidArgumentException('Missing "_id" field in the document data.');

        $vector = !\array_key_exists($this->vectorsField, $document['_source']) || null === $document['_source'][$this->vectorsField]
            ? new NullVector()
            : new Vector($document['_source'][$this->vectorsField]);

        return new VectorDocument($id, $vector, new Metadata(json_decode($document['_source']['metadata'], true)), $document['_score'] ?? null);
    }
}
