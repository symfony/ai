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
use Symfony\AI\Store\DroppableStoreInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\InitializableStoreInterface;
use Symfony\AI\Store\VectorStoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class Store implements InitializableStoreInterface, DroppableStoreInterface, VectorStoreInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
        #[\SensitiveParameter] private string $apiKey,
        private string $database,
        private string $collection,
        private string $vectorFieldName = '_vectors',
        private int $dimensions = 1536,
        private string $metricType = 'COSINE',
    ) {
    }

    public function add(VectorDocument ...$documents): void
    {
        $this->request('POST', 'v2/vectordb/entities/insert', [
            'collectionName' => $this->collection,
            'data' => array_map($this->convertToIndexableArray(...), $documents),
        ]);
    }

    public function query(Vector $vector, array $options = []): array
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

        return array_map($this->convertToVectorDocument(...), $documents['data']);
    }

    public function initialize(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('POST', 'v2/vectordb/databases/create', [
            'dbName' => $this->database,
        ]);

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

    public function drop(): void
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
            ? new NullVector() : new Vector($data[$this->vectorFieldName]);

        $score = $data['distance'] ?? null;

        return new VectorDocument(Uuid::fromString($id), $vector, new Metadata(json_decode($data['_metadata'], true)), $score);
    }
}
