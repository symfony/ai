<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Supabase;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 *
 * Requires pgvector extension to be enabled and a pre-configured table/function.
 *
 * @see https://github.com/pgvector/pgvector
 * @see https://supabase.com/docs/guides/ai/vector-columns
 *
 * Supabase store using REST & RPC.
 *
 * Note: Unlike Postgres Store, this store requires manual setup of:
 * 1. pgvector extension
 * 2. Table with vector column
 * 3. RPC function for similarity search
 *
 * This is because Supabase doesn't allow arbitrary SQL execution via REST API.
 */
final readonly class Store implements StoreInterface
{
    public function __construct(
        private HttpClientInterface $http,
        private string $url,
        private string $apiKey,
        private string $table = 'documents',
        private string $vectorFieldName = 'embedding',
        private int $vectorDimension = 1536,
        private string $functionName = 'match_documents',
    ) {
    }

    public function add(VectorDocument ...$documents): void
    {
        if (0 === \count($documents)) {
            return;
        }

        $rows = [];

        foreach ($documents as $document) {
            if (\count($document->vector->getData()) !== $this->vectorDimension) {
                continue;
            }

            $rows[] = [
                'id' => $document->id->toRfc4122(),
                $this->vectorFieldName => $document->vector->getData(),
                'metadata' => $document->metadata->getArrayCopy(),
            ];
        }

        $chunkSize = 200;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $response = $this->http->request(
                'POST',
                \sprintf('%s/rest/v1/%s', $this->url, $this->table),
                [
                    'headers' => [
                        'apikey' => $this->apiKey,
                        'Authorization' => 'Bearer '.$this->apiKey,
                        'Content-Type' => 'application/json',
                        'Prefer' => 'resolution=merge-duplicates',
                    ],
                    'json' => $chunk,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                throw new RuntimeException('Supabase insert failed: '.$response->getContent(false));
            }
        }
    }

    /**
     * @param array{
     *      max_items?: int,
     *      limit?: int,
     *      min_score?: float
     *  } $options
     */
    public function query(Vector $vector, array $options = []): array
    {
        if (\count($vector->getData()) !== $this->vectorDimension) {
            throw new InvalidArgumentException("Vector dimension mismatch: expected {$this->vectorDimension}");
        }

        $matchCount = $options['max_items'] ?? ($options['limit'] ?? 10);
        $threshold = $options['min_score'] ?? 0.0;

        $response = $this->http->request(
            'POST',
            \sprintf('%s/rest/v1/rpc/%s', $this->url, $this->functionName),
            [
                'headers' => [
                    'apikey' => $this->apiKey,
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query_embedding' => $vector->getData(),
                    'match_count' => $matchCount,
                    'match_threshold' => $threshold,
                ],
            ]
        );

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('Supabase query failed: '.$response->getContent(false));
        }

        $records = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $documents = [];

        foreach ($records as $record) {
            if (
                !isset($record['id'], $record[$this->vectorFieldName], $record['metadata'], $record['score'])
                || !\is_string($record['id'])
            ) {
                continue;
            }

            $embedding = \is_array($record[$this->vectorFieldName]) ? $record[$this->vectorFieldName] : json_decode($record[$this->vectorFieldName] ?? '{}', true, 512, \JSON_THROW_ON_ERROR);
            $metadata = \is_array($record['metadata']) ? $record['metadata'] : json_decode($record['metadata'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR);

            $documents[] = new VectorDocument(
                id: Uuid::fromString($record['id']),
                vector: new Vector($embedding),
                metadata: new Metadata($metadata),
                score: (float) $record['score'],
            );
        }

        return $documents;
    }
}
