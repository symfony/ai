<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Redis;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $indexName,
        private readonly string $keyPrefix = 'vector:',
        private readonly Distance $distance = Distance::Cosine,
    ) {
    }

    /**
     * @param array{vector_size?: positive-int, index_method?: "FLAT"|"HNSW", extra_schema?: list<string>} $options
     *
     * - For Mistral: ['vector_size' => 1024]
     * - For OpenAI: ['vector_size' => 1536]
     * - For Gemini: ['vector_size' => 3072] (default)
     */
    public function setup(array $options = []): void
    {
        $this->redis->clearLastError();

        $vectorSize = $options['vector_size'] ?? 3072;
        $indexMethod = $options['index_method'] ?? 'FLAT'; // Or 'HNSW' for approximate search
        $extraSchema = $options['extra_schema'] ?? [];

        // Create the index with vector field for JSON documents
        try {
            $this->redis->rawCommand(
                'FT.CREATE', $this->indexName, 'ON', 'JSON',
                'PREFIX', '1', $this->keyPrefix,
                'SCHEMA',
                '$.id', 'AS', 'id', 'TEXT',
                '$.embedding', 'AS', 'embedding', 'VECTOR', $indexMethod, '6', 'TYPE', 'FLOAT32', 'DIM', $vectorSize, 'DISTANCE_METRIC', $this->distance->value,
                ...$extraSchema,
            );
        } catch (\RedisException $e) {
            if (!str_contains($e->getMessage(), 'Index already exists')) {
                throw new RuntimeException(\sprintf('Failed to create Redis index: "%s".', $e->getMessage()), previous: $e);
            }
        }

        $this->redis->clearLastError();
    }

    public function drop(array $options = []): void
    {
        try {
            $this->redis->rawCommand('FT.DROPINDEX', $this->indexName);
        } catch (\RedisException $e) {
            if (!str_contains($e->getMessage(), 'Unknown Index name')) {
                throw new RuntimeException(\sprintf('Failed to drop Redis index: "%s".', $e->getMessage()), previous: $e);
            }
        }
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $this->redis->clearLastError();

        $pipeline = $this->redis->multi(\Redis::PIPELINE);

        foreach ($documents as $document) {
            $key = $this->keyPrefix.$document->getId();
            $data = [
                'id' => $document->getId(),
                'metadata' => $document->getMetadata()->getArrayCopy(),
                'embedding' => $document->getVector()->getData(),
            ];

            $pipeline->rawCommand('JSON.SET', $key, '$', json_encode($data, \JSON_THROW_ON_ERROR));
        }

        $pipeline->exec();

        if ($error = $this->redis->getLastError()) {
            $e = new \RedisException($error);
            throw new RuntimeException(\sprintf('Failed to add documents to Redis: "%s".', $e->getMessage()), 0, $e);
        }
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $this->redis->clearLastError();

        $keys = array_map(fn ($id) => $this->keyPrefix.$id, $ids);

        $this->redis->del($keys);

        if ($error = $this->redis->getLastError()) {
            $e = new \RedisException($error);
            throw new RuntimeException(\sprintf('Failed to remove documents from Redis: "%s".', $e->getMessage()), 0, $e);
        }
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{limit?: positive-int, maxScore?: float, where?: string} $options
     *
     * @return VectorDocument[]
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $limit = $options['limit'] ?? 5;
        $maxScore = $options['maxScore'] ?? null;
        $whereFilter = $options['where'] ?? '*';

        $queryString = "({$whereFilter}) => [KNN {$limit} @embedding \$query_vector AS vector_score]";

        try {
            $results = $this->redis->rawCommand(
                'FT.SEARCH',
                $this->indexName,
                $queryString,
                'PARAMS', 2, 'query_vector', $this->toRedisVector($query->getVector()),
                'RETURN', 4, '$.id', '$.metadata', '$.embedding', 'vector_score',
                'SORTBY', 'vector_score', 'ASC',
                'LIMIT', 0, $limit,
                'DIALECT', 2
            );
        } catch (\RedisException $e) {
            throw new RuntimeException(\sprintf('Failed to execute query: "%s".', $e->getMessage()), 0, $e);
        }

        if (!\is_array($results) || \count($results) < 2) {
            return [];
        }

        $numResults = $results[0];

        // Parse results (skip first element which is the count)
        for ($i = 1; $i <= $numResults; $i += 2) {
            // $docKey = $results[$i];
            $docData = $results[$i + 1];

            // Convert flat array to associative array
            $data = [];
            for ($j = 0; $j < \count($docData); $j += 2) {
                $fieldName = $docData[$j];
                $fieldValue = $docData[$j + 1] ?? null;

                if (\is_string($fieldValue) && json_validate($fieldValue)) {
                    $fieldValue = json_decode($fieldValue, true);
                }

                $data[$fieldName] = $fieldValue;
            }

            if (!isset($data['$.id'], $data['vector_score'])) {
                continue;
            }

            $score = (float) $data['vector_score'];

            // Apply max score filter if specified
            if (null !== $maxScore && $score > $maxScore) {
                continue;
            }

            yield new VectorDocument(
                id: $data['$.id'],
                vector: new Vector($data['$.embedding'] ?? []),
                metadata: new Metadata($data['$.metadata'] ?? []),
                score: $score,
            );
        }
    }

    private function toRedisVector(VectorInterface $vector): string
    {
        $data = $vector->getData();
        $bytes = '';
        foreach ($data as $value) {
            $bytes .= pack('f', $value);
        }

        return $bytes;
    }
}
