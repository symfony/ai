<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Milvus;

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
     * @param HttpClientInterface $httpClient HTTP client scoped to the Milvus instance, see {@see StoreFactory}
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $database,
        private readonly string $collection,
        private readonly string $vectorFieldName = '_vectors',
        private readonly int $dimensions = 1536,
        private readonly string $metricType = 'COSINE',
    ) {
    }

    /**
     * @param array{
     *     forceDatabaseCreation?: bool,
     * } $options
     */
    public function setup(array $options = []): void
    {
        if (\array_key_exists('forceDatabaseCreation', $options) && $options['forceDatabaseCreation']) {
            $this->request('POST', 'v2/vectordb/databases/create', [
                'dbName' => $this->database,
            ]);
        }

        $this->request('POST', 'v2/vectordb/collections/create', [
            'collectionName' => $this->collection,
            'schema' => [
                'autoId' => false,
                'enableDynamicField' => false,
                'fields' => [
                    [
                        'fieldName' => 'id',
                        'dataType' => 'VarChar',
                        'isPrimary' => true,
                        'elementTypeParams' => [
                            'max_length' => '512',
                        ],
                    ],
                    [
                        'fieldName' => '_metadata',
                        'dataType' => 'VarChar',
                        'elementTypeParams' => [
                            'max_length' => '512',
                        ],
                    ],
                    [
                        'fieldName' => $this->vectorFieldName,
                        'dataType' => 'FloatVector',
                        'elementTypeParams' => [
                            'dim' => $this->dimensions,
                        ],
                    ],
                ],
            ],
            'indexParams' => [
                [
                    'fieldName' => $this->vectorFieldName,
                    'metricType' => $this->metricType,
                    'indexName' => \sprintf('%s_%s', $this->collection, $this->vectorFieldName),
                    'indexType' => 'AUTOINDEX',
                ],
            ],
        ]);
    }

    public function add(VectorDocumentInterface|array $documents): void
    {
        if ($documents instanceof VectorDocumentInterface) {
            $documents = [$documents];
        }

        $this->request('POST', 'v2/vectordb/entities/insert', [
            'collectionName' => $this->collection,
            'data' => array_map($this->convertToIndexableArray(...), $documents),
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

        $this->request('POST', 'v2/vectordb/entities/delete', [
            'collectionName' => $this->collection,
            'filter' => \sprintf('id in [%s]', implode(',', array_map(static fn ($id) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $id).'"', $ids))),
        ]);
    }

    public function clear(array $options = []): void
    {
        $this->request('POST', 'v2/vectordb/entities/delete', [
            'collectionName' => $this->collection,
            'filter' => "id != ''",
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
        $payload = [
            'collectionName' => $this->collection,
            'data' => [
                $vector->getData(),
            ],
            'annsField' => $this->vectorFieldName,
            'outputFields' => ['id', '_metadata', $this->vectorFieldName],
        ];

        if (isset($options['limit'])) {
            $payload['limit'] = $options['limit'];
        }

        $documents = $this->request('POST', 'v2/vectordb/entities/search', $payload);

        foreach ($documents['data'] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    public function drop(array $options = []): void
    {
        $this->request('POST', 'v2/vectordb/databases/drop', [
            'dbName' => $this->database,
        ]);
    }

    public function count(): int
    {
        $result = $this->request('POST', 'v2/vectordb/entities/query', [
            'collectionName' => $this->collection,
            'filter' => '',
            'outputFields' => ['count(*)'],
        ]);

        return (int) ($result['data'][0]['count(*)'] ?? 0);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload): array
    {
        $result = $this->httpClient->request($method, $path, [
            'json' => $payload,
        ]);

        $response = $result->toArray();

        // Milvus answers with 200 even when the operation failed, the error is carried in the body
        $code = $response['code'] ?? 0;
        if (\is_int($code) && 0 !== $code) {
            $message = $response['message'] ?? 'Unknown error';

            throw new RuntimeException(\sprintf('Milvus request failed with code %d: "%s".', $code, \is_string($message) ? $message : 'Unknown error'));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocumentInterface $document): array
    {
        return [
            'id' => $document->getId(),
            '_metadata' => json_encode($document->getMetadata()->getArrayCopy()),
            $this->vectorFieldName => $document->getVector()->getData(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocumentInterface
    {
        $id = $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
            ? new NullVector()
            : new Vector($data[$this->vectorFieldName]);

        $score = $data['distance'] ?? null;

        return new VectorDocument($id, $vector, new Metadata(json_decode($data['_metadata'], true)), $score);
    }
}
