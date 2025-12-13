<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\MongoDb;

use MongoDB\BSON\Binary;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\CommandException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @see https://www.mongodb.com/docs/atlas/atlas-vector-search/vector-search-overview/
 *
 * For this store you need to create a separate MongoDB Atlas Search index.
 * The index needs to be created with the following settings:
 * {
 *     "fields": [
 *         {
 *             "numDimensions": 1536,
 *             "path": "vector",
 *             "similarity": "euclidean",
 *             "type": "vector"
 *         }
 *     ]
 * }
 *
 * Note, that the `path` key needs to match the $vectorFieldName.
 *
 * For the `similarity` key you can choose between `euclidean`, `cosine` and `dotProduct`.
 * {@see https://www.mongodb.com/docs/atlas/atlas-search/field-types/knn-vector/#define-the-index-for-the-fts-field-type-type}
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    /**
     * @param string $databaseName    The name of the database
     * @param string $collectionName  The name of the collection
     * @param string $indexName       The name of the Atlas Search index
     * @param string $vectorFieldName The name of the field int the index that contains the vector
     * @param bool   $bulkWrite       Use bulk write operations
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $databaseName,
        private readonly string $collectionName,
        private readonly string $indexName,
        private readonly string $vectorFieldName = 'vector',
        private readonly bool $bulkWrite = false,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array{fields?: array<mixed>} $options
     */
    public function setup(array $options = []): void
    {
        if ([] !== $options && !\array_key_exists('fields', $options)) {
            throw new InvalidArgumentException('The only supported option is "fields".');
        }

        try {
            $this->getCollection()->createSearchIndex(
                [
                    'fields' => array_merge([
                        [
                            'numDimensions' => 1536,
                            'path' => $this->vectorFieldName,
                            'similarity' => 'euclidean',
                            'type' => 'vector',
                        ],
                    ], $options['fields'] ?? []),
                ],
                [
                    'name' => $this->indexName,
                    'type' => 'vectorSearch',
                ],
            );
        } catch (CommandException $e) {
            $this->logger->warning($e->getMessage());
        }
    }

    public function drop(array $options = []): void
    {
        $this->getCollection()->drop();
    }

    public function add(VectorDocument ...$documents): void
    {
        $operations = [];

        foreach ($documents as $document) {
            $operation = [
                ['_id' => $this->toBinary($document->id)], // we use binary for the id, because of storage efficiency
                array_filter([
                    'metadata' => $document->metadata->getArrayCopy(),
                    $this->vectorFieldName => $document->vector->getData(),
                ]),
                ['upsert' => true], // insert if not exists
            ];

            if ($this->bulkWrite) {
                $operations[] = ['replaceOne' => $operation];
                continue;
            }

            $this->getCollection()->replaceOne(...$operation);
        }

        if ($this->bulkWrite) {
            $this->getCollection()->bulkWrite($operations);
        }
    }

    /**
     * @param array{
     *     limit?: positive-int,
     *     numCandidates?: positive-int,
     *     filter?: array<mixed>,
     *     minScore?: float,
     * } $options
     */
    public function query(Vector $vector, array $options = []): iterable
    {
        $minScore = null;
        if (\array_key_exists('minScore', $options)) {
            $minScore = $options['minScore'];
            unset($options['minScore']);
        }

        $pipeline = [
            [
                '$vectorSearch' => array_merge([
                    'index' => $this->indexName,
                    'path' => $this->vectorFieldName,
                    'queryVector' => $vector->getData(),
                    'numCandidates' => 200,
                    'limit' => 5,
                ], $options),
            ],
            [
                '$addFields' => [
                    'score' => ['$meta' => 'vectorSearchScore'],
                ],
            ],
        ];

        if (null !== $minScore) {
            $pipeline[] = [
                '$match' => [
                    'score' => ['$gte' => $minScore],
                ],
            ];
        }

        $results = $this->getCollection()->aggregate(
            $pipeline,
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );

        foreach ($results as $result) {
            yield new VectorDocument(
                id: $this->toUuid($result['_id']),
                vector: new Vector($result[$this->vectorFieldName]),
                metadata: new Metadata($result['metadata'] ?? []),
                score: $result['score'],
            );
        }
    }

    private function getCollection(): Collection
    {
        return $this->client->getCollection($this->databaseName, $this->collectionName);
    }

    private function toBinary(Uuid $uuid): Binary
    {
        return new Binary($uuid->toBinary(), Binary::TYPE_UUID);
    }

    private function toUuid(Binary $binary): Uuid
    {
        return Uuid::fromString($binary->getData());
    }
}
