<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\MariaDb;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Requires MariaDb >=11.7.
 *
 * @see https://mariadb.org/rag-with-mariadb-vector/
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    /**
     * @param string $tableName       The name of the table
     * @param string $indexName       The name of the vector search index
     * @param string $vectorFieldName The name of the field in the index that contains the vector
     */
    public function __construct(
        private readonly \PDO $connection,
        private readonly string $tableName,
        private readonly string $indexName,
        private readonly string $vectorFieldName,
        private readonly Metric $metric = Metric::Euclidean,
        private readonly int $dimensions = 1536,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $serverVersion = $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);

        if (!str_contains((string) $serverVersion, 'MariaDB') || version_compare($serverVersion, '11.7.0') < 0) {
            throw new InvalidArgumentException('You need MariaDB >=11.7 to use this feature.');
        }

        if (Metric::Distance === $this->metric && version_compare($serverVersion, '11.8') < 0) {
            throw new InvalidArgumentException('You need MariaDB >=11.8 to use "Metric::Distance", VEC_DISTANCE() does not exist before.');
        }

        // MariaDB serves a query from the vector index only if both use the same distance
        $indexDistance = match ($this->metric) {
            Metric::Cosine => ' DISTANCE=cosine',
            Metric::Euclidean => ' DISTANCE=euclidean',
            // VEC_DISTANCE() follows the distance of the index, the server default applies
            Metric::Distance => '',
        };

        $this->connection->exec(
            \sprintf(
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS %1$s (
                        id UUID NOT NULL PRIMARY KEY,
                        metadata JSON,
                        `%2$s` VECTOR(%4$d) NOT NULL,
                        VECTOR INDEX %3$s (`%2$s`)%5$s
                    )
                    SQL,
                $this->tableName,
                $this->vectorFieldName,
                $this->indexName,
                $this->dimensions,
                $indexDistance,
            ),
        );
    }

    public function drop(array $options = []): void
    {
        $this->connection->exec(\sprintf('DROP TABLE IF EXISTS %s', $this->tableName));
    }

    public function count(): int
    {
        $statement = $this->connection->query(\sprintf('SELECT COUNT(*) FROM %s', $this->tableName));

        return (int) $statement->fetchColumn();
    }

    public static function fromPdo(\PDO $connection, string $tableName, string $indexName = 'embedding', string $vectorFieldName = 'embedding', Metric $metric = Metric::Euclidean, int $dimensions = 1536): self
    {
        return new self($connection, $tableName, $indexName, $vectorFieldName, $metric, $dimensions);
    }

    /**
     * @throws InvalidArgumentException When DBAL connection doesn't use PDO driver
     * @throws DBALException            When DBAL operations fail (e.g., getting native connection)
     */
    public static function fromDbal(Connection $connection, string $tableName, string $indexName = 'embedding', string $vectorFieldName = 'embedding', Metric $metric = Metric::Euclidean, int $dimensions = 1536): self
    {
        $pdo = $connection->getNativeConnection();

        if (!$pdo instanceof \PDO) {
            throw new InvalidArgumentException('Only DBAL connections using PDO driver are supported.');
        }

        return self::fromPdo($pdo, $tableName, $indexName, $vectorFieldName, $metric, $dimensions);
    }

    public function add(VectorDocumentInterface|array $documents, array $options = []): void
    {
        if ($documents instanceof VectorDocumentInterface) {
            $documents = [$documents];
        }

        $statement = $this->connection->prepare(
            \sprintf(
                <<<'SQL'
                    INSERT INTO %1$s (id, metadata, `%2$s`)
                    VALUES (:id, :metadata, VEC_FromText(:vector))
                    ON DUPLICATE KEY UPDATE metadata = :metadata2, `%2$s` = VEC_FromText(:vector2)
                    SQL,
                $this->tableName,
                $this->vectorFieldName,
            ),
        );

        foreach ($documents as $document) {
            $statement->bindValue(':id', $document->getId());
            $statement->bindValue(':metadata', json_encode($document->getMetadata()->getArrayCopy()));
            $statement->bindValue(':vector', json_encode($document->getVector()->getData()));
            $statement->bindValue(':metadata2', json_encode($document->getMetadata()->getArrayCopy()));
            $statement->bindValue(':vector2', json_encode($document->getVector()->getData()));

            $statement->execute();
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

        $placeholders = implode(', ', array_fill(0, \count($ids), '?'));

        $statement = $this->connection->prepare(
            \sprintf(
                'DELETE FROM %s WHERE id IN (%s)',
                $this->tableName,
                $placeholders,
            ),
        );

        foreach ($ids as $index => $id) {
            $statement->bindValue($index + 1, $id);
        }

        $statement->execute();
    }

    public function clear(array $options = []): void
    {
        $this->connection->exec(\sprintf('TRUNCATE TABLE %s', $this->tableName));
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{
     *     limit?: positive-int,
     *     maxScore?: float|null,
     *     where?: string,
     *     params?: array<string, mixed>,
     * } $options
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $vector = $query->getVector();
        $where = null;

        $maxScore = $options['maxScore'] ?? null;
        if (null !== $maxScore) {
            $where = \sprintf('WHERE %1$s(`%2$s`, q.query_vector) <= :maxScore', $this->metric->getComparisonFunction(), $this->vectorFieldName);
        }

        if ($options['where'] ?? false) {
            if ($where) {
                $where .= ' AND ('.$options['where'].')';
            } else {
                $where = 'WHERE '.$options['where'];
            }
        }

        // A derived table parses the query vector once, instead of once per row
        $statement = $this->connection->prepare(
            \sprintf(
                <<<'SQL'
                    SELECT id, VEC_ToText(`%1$s`) embedding, metadata, %5$s(`%1$s`, q.query_vector) AS score
                    FROM %2$s
                    JOIN (SELECT VEC_FromText(:embedding) AS query_vector) q
                    %3$s
                    ORDER BY score ASC
                    LIMIT %4$d
                    SQL,
                $this->vectorFieldName,
                $this->tableName,
                $where ?? '',
                $options['limit'] ?? 5,
                $this->metric->getComparisonFunction(),
            ),
        );

        $params = [
            'embedding' => json_encode($vector->getData()),
            ...$options['params'] ?? [],
        ];

        if (null !== $maxScore) {
            $params['maxScore'] = $maxScore;
        }

        foreach ($params as $key => $value) {
            $statement->bindValue(':'.$key, $value);
        }

        $statement->execute();

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            yield new VectorDocument(
                id: Uuid::fromRfc4122($result['id']),
                vector: new Vector(json_decode((string) $result['embedding'], true)),
                metadata: new Metadata(json_decode($result['metadata'] ?? '{}', true)),
                score: $result['score'],
            );
        }
    }
}
