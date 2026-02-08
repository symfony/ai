<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ClickHouse;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $databaseName = 'default',
        private readonly string $tableName = 'embedding',
    ) {
    }

    public function setup(array $options = []): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS {{ table }} (
                id UUID,
                metadata String,
                embedding Array(Float32),
            ) ENGINE = MergeTree()
            ORDER BY id
        SQL;

        $this->execute('POST', $sql);
    }

    public function drop(array $options = []): void
    {
        $this->execute('POST', 'DROP TABLE IF EXISTS {{ table }}');
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $rows = [];

        foreach ($documents as $document) {
            $rows[] = $this->formatVectorDocument($document);
        }

        $this->insertBatch($rows);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $this->execute('POST', 'DELETE FROM {{ table }} WHERE id IN {ids:Array(UUID)}', [
            'ids' => "['".implode("','", $ids)."']",
        ]);
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    public function query(QueryInterface $query, array $options = [], ?float $minScore = null): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new InvalidArgumentException(\sprintf('Query must be instance of "%s", "%s" given.', VectorQuery::class, $query::class));
        }

        $sql = <<<'SQL'
            SELECT
                id,
                embedding,
                metadata,
                cosineDistance(embedding, {query_vector:Array(Float32)}) as score
            FROM {{ table }}
            WHERE length(embedding) = length({query_vector:Array(Float32)}) {{ where }}
            ORDER BY score ASC
            LIMIT {limit:UInt32}
        SQL;

        if (isset($options['where'])) {
            $sql = str_replace('{{ where }}', 'AND '.$options['where'], $sql);
        } else {
            $sql = str_replace('{{ where }}', '', $sql);
        }

        $results = $this
            ->execute('GET', $sql, [
                'query_vector' => $this->toClickHouseVector($query->getVector()),
                'limit' => $options['limit'] ?? 5,
                ...$options['params'] ?? [],
            ])
            ->toArray()['data']
        ;

        foreach ($results as $result) {
            yield new VectorDocument(
                id: $result['id'],
                vector: new Vector($result['embedding']),
                metadata: new Metadata(json_decode($result['metadata'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR)),
                score: $result['score'],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatVectorDocument(VectorDocument $document): array
    {
        return [
            'id' => $document->getId(),
            'metadata' => json_encode($document->getMetadata()->getArrayCopy(), \JSON_THROW_ON_ERROR),
            'embedding' => $document->getVector()->getData(),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function execute(string $method, string $sql, array $params = []): ResponseInterface
    {
        $sql = str_replace('{{ table }}', $this->tableName, $sql);

        $options = [
            'query' => [
                'query' => $sql,
                'database' => $this->databaseName,
                'default_format' => 'JSON',
            ],
        ];

        foreach ($params as $key => $value) {
            $options['query']['param_'.$key] = $value;
        }

        return $this->httpClient->request($method, '/', $options);
    }

    /**
     * @param array<array<string, mixed>> $rows
     */
    private function insertBatch(array $rows): void
    {
        if (!$rows) {
            return;
        }

        $sql = 'INSERT INTO {{ table }} FORMAT JSONEachRow';
        $sql = str_replace('{{ table }}', $this->tableName, $sql);

        $jsonData = '';
        foreach ($rows as $row) {
            $jsonData .= json_encode($row)."\n";
        }

        $options = [
            'query' => [
                'query' => $sql,
                'database' => $this->databaseName,
            ],
            'body' => $jsonData,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        $response = $this->httpClient->request('POST', '/', $options);

        if (200 !== $response->getStatusCode()) {
            $content = $response->getContent(false);

            throw new RuntimeException(\sprintf('Could not insert data into ClickHouse. Http status code: %d. Response: "%s".', $response->getStatusCode(), $content));
        }
    }

    private function toClickHouseVector(VectorInterface $vector): string
    {
        return '['.implode(',', $vector->getData()).']';
    }
}
