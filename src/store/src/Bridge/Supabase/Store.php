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
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 *
 * Supabase vector store implementation using REST API and pgvector.
 *
 * This store provides vector storage capabilities through Supabase's REST API
 * with pgvector extension support.
 *
 * This store does not implement {@see ManagedStoreInterface} because Supabase
 * manages schemas through its Dashboard or SQL migrations, not through the REST
 * API. The required table and similarity search function must be created
 * beforehand by the user.
 *
 * @see https://github.com/pgvector/pgvector pgvector extension documentation
 * @see https://supabase.com/docs/guides/ai/vector-columns Supabase vector guide
 */
final class Store implements StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $table = 'documents',
        private readonly string $vectorFieldName = 'embedding',
        private readonly int $vectorDimension = 1536,
        private readonly string $functionName = 'match_documents',
    ) {
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        if (0 === \count($documents)) {
            return;
        }

        $rows = [];

        foreach ($documents as $document) {
            if (\count($document->getVector()->getData()) !== $this->vectorDimension) {
                continue;
            }

            $rows[] = [
                'id' => $document->getId(),
                $this->vectorFieldName => $document->getVector()->getData(),
                'metadata' => $document->getMetadata()->getArrayCopy(),
            ];
        }

        $chunkSize = 200;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $response = $this->httpClient->request(
                'POST',
                \sprintf('%s/rest/v1/%s', $this->url, $this->table),
                [
                    'headers' => $this->getHeaders() + ['Prefer' => 'resolution=merge-duplicates'],
                    'json' => $chunk,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                throw new RuntimeException('Supabase insert failed: '.$response->getContent(false));
            }
        }
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if (0 === \count($ids)) {
            return;
        }

        // Supabase REST API supports batch deletes using the 'in' filter
        // We'll chunk the ids to avoid potential URL length limits
        $chunkSize = 200;

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $idsString = implode(',', array_map(static fn ($id) => '"'.str_replace('"', '""', $id).'"', $chunk));

            $response = $this->httpClient->request(
                'DELETE',
                \sprintf('%s/rest/v1/%s', $this->url, $this->table),
                [
                    'headers' => $this->getHeaders(),
                    'query' => [
                        'id' => \sprintf('in.(%s)', $idsString),
                    ],
                ]
            );

            if ($response->getStatusCode() >= 400) {
                throw new RuntimeException('Supabase delete failed: '.$response->getContent(false));
            }
        }
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{
     *      max_items?: int,
     *      limit?: int,
     *      min_score?: float
     *  } $options
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $vector = $query->getVector();
        if (\count($vector->getData()) !== $this->vectorDimension) {
            throw new InvalidArgumentException("Vector dimension mismatch: expected {$this->vectorDimension}.");
        }

        $matchCount = $options['max_items'] ?? ($options['limit'] ?? 10);
        $threshold = $options['min_score'] ?? 0.0;

        $response = $this->httpClient->request(
            'POST',
            \sprintf('%s/rest/v1/rpc/%s', $this->url, $this->functionName),
            [
                'headers' => $this->getHeaders(),
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

        foreach ($records as $record) {
            if (!isset($record['id'], $record[$this->vectorFieldName], $record['metadata'], $record['score']) || !\is_string($record['id'])) {
                continue;
            }

            $embedding = \is_array($record[$this->vectorFieldName]) ? $record[$this->vectorFieldName] : json_decode($record[$this->vectorFieldName] ?? '{}', true, 512, \JSON_THROW_ON_ERROR);
            $metadata = \is_array($record['metadata']) ? $record['metadata'] : json_decode($record['metadata'], true, 512, \JSON_THROW_ON_ERROR);

            yield new VectorDocument(
                id: $record['id'],
                vector: new Vector($embedding),
                metadata: new Metadata($metadata),
                score: (float) $record['score'],
            );
        }
    }

    public function count(): int
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('%s/rest/v1/%s?select=count', $this->url, $this->table),
            [
                'headers' => [
                    'apikey' => $this->apiKey,
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Prefer' => 'count=exact',
                ],
            ]
        );

        $countHeader = $response->getHeaders()['content-range'][0] ?? '';

        if (preg_match('/\/(\d+)$/', $countHeader, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return [
            'apikey' => $this->apiKey,
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
