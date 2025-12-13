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
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;
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

    public function add(VectorDocument ...$documents): void
    {
        $this->request('POST', 'v2/vectordb/entities/insert', [
            'collectionName' => $this->collection,
            'data' => array_map($this->convertToIndexableArray(...), $documents),
        ]);
    }

    public function query(Vector $vector, array $options = []): iterable
    {
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

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);
        $result = $this->httpClient->request($method, $url, [
            'auth_bearer' => $this->apiKey,
            'json' => $payload,
        ]);

        return $result->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return [
            'id' => $document->id->toRfc4122(),
            '_metadata' => json_encode($document->metadata->getArrayCopy()),
            $this->vectorFieldName => $document->vector->getData(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
            ? new NullVector()
            : new Vector($data[$this->vectorFieldName]);

        $score = $data['distance'] ?? null;

        return new VectorDocument(Uuid::fromString($id), $vector, new Metadata(json_decode($data['_metadata'], true)), $score);
    }
}
