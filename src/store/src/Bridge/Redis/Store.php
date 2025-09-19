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
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

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
     * @param array{vector_size?: positive-int, index_method?: string, extra_schema?: list<string>} $options
     *
     * - For Mistral: ['vector_size' => 1024]
     * - For OpenAI: ['vector_size' => 1536]
     * - For Gemini: ['vector_size' => 3072] (default)
     */
    public function setup(array $options = []): void
    {
        $vectorSize = $options['vector_size'] ?? 3072;
        $indexMethod = $options['index_method'] ?? 'FLAT'; // Or 'HNSW' for approximate search
        $distanceMetric = $this->distance->getRedisMetric();
        $extraSchema = $options['extra_schema'] ?? [];

        // Create the index with vector field for JSON documents
        try {
            $this->redis->rawCommand(
                'FT.CREATE', $this->indexName, 'ON', 'JSON',
                'PREFIX', '1', $this->keyPrefix,
                'SCHEMA',
                '$.id', 'AS', 'id', 'TEXT',
                '$.embedding', 'AS', 'embedding', 'VECTOR', $indexMethod, '6', 'TYPE', 'FLOAT32', 'DIM', $vectorSize, 'DISTANCE_METRIC', $distanceMetric,
                ...$extraSchema,
            );
        } catch (\RedisException $e) {
            // Index might already exist, check if it's a "already exists" error
            if (!str_contains($e->getMessage(), 'Index already exists')) {
                throw new RuntimeException(\sprintf('Failed to create Redis index: "%s".', $e->getMessage()), 0, $e);
            }
        }
    }

    public function drop(): void
    {
        try {
            $this->redis->rawCommand('FT.DROPINDEX', $this->indexName);
        } catch (\RedisException $e) {
            // Ignore "Unknown Index name" error
            if (!str_contains($e->getMessage(), 'Unknown Index name')) {
                throw new RuntimeException(\sprintf('Failed to drop Redis index: "%s".', $e->getMessage()), 0, $e);
            }
        }
    }

    public function add(VectorDocument ...$documents): void
    {
        $pipeline = $this->redis->multi(\Redis::PIPELINE);

        foreach ($documents as $document) {
            $key = $this->keyPrefix.$document->id->toRfc4122();
            $data = [
                'id' => $document->id->toRfc4122(),
                'metadata' => $document->metadata->getArrayCopy(),
                'embedding' => $document->vector->getData(),
            ];

            $pipeline->rawCommand('JSON.SET', $key, '$', json_encode($data, \JSON_THROW_ON_ERROR));
        }

        $pipeline->exec();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return VectorDocument[]
     */
    public function query(Vector $vector, array $options = []): array
    {
        $limit = $options['limit'] ?? 5;
        $maxScore = $options['maxScore'] ?? null;
        $whereFilter = $options['where'] ?? '*';

        $query = "({$whereFilter}) => [KNN {$limit} @embedding \$query_vector AS vector_score]";

        try {
            $results = $this->redis->rawCommand(
                'FT.SEARCH',
                $this->indexName,
                $query,
                'PARAMS', 2, 'query_vector', $this->toRedisVector($vector),
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

        $documents = [];
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

            $documents[] = new VectorDocument(
                id: Uuid::fromString($data['$.id']),
                vector: new Vector($data['$.embedding'] ?? []),
                metadata: new Metadata($data['$.metadata'] ?? []),
                score: $score,
            );
        }

        return $documents;
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
