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

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\CommandException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedFeatureException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

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
        private readonly int $embeddingsDimension = 1536,
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
                            'numDimensions' => $this->embeddingsDimension,
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

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $operations = [];

        foreach ($documents as $document) {
            $operation = [
                ['_id' => (string) $document->getId()],
                array_filter([
                    'metadata' => $document->getMetadata()->getArrayCopy(),
                    $this->vectorFieldName => $document->getVector()->getData(),
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

    public function remove(string|array $ids, array $options = []): void
    {
        throw new UnsupportedFeatureException('Method not implemented yet.');
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{
     *     limit?: positive-int,
     *     numCandidates?: positive-int,
     *     filter?: array<mixed>,
     *     minScore?: float,
     * } $options
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $vector = $query->getVector();
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
                id: $result['_id'],
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
}
