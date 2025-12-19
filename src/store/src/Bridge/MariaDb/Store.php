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
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
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
    ) {
    }

    /**
     * @param array{dimensions?: positive-int} $options
     */
    public function setup(array $options = []): void
    {
        if ([] !== $options && !\array_key_exists('dimensions', $options)) {
            throw new InvalidArgumentException('The only supported option is "dimensions".');
        }

        $serverVersion = $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);

        if (!str_contains((string) $serverVersion, 'MariaDB') || version_compare($serverVersion, '11.7.0') < 0) {
            throw new InvalidArgumentException('You need MariaDB >=11.7 to use this feature.');
        }

        $this->connection->exec(
            \sprintf(
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS %1$s (
                        id UUID NOT NULL PRIMARY KEY,
                        metadata JSON,
                        `%2$s` VECTOR(%4$d) NOT NULL,
                        VECTOR INDEX %3$s (`%2$s`)
                    )
                    SQL,
                $this->tableName,
                $this->vectorFieldName,
                $this->indexName,
                $options['dimensions'] ?? 1536,
            ),
        );
    }

    public function drop(array $options = []): void
    {
        $this->connection->exec(\sprintf('DROP TABLE IF EXISTS %s', $this->tableName));
    }

    public static function fromPdo(\PDO $connection, string $tableName, string $indexName = 'embedding', string $vectorFieldName = 'embedding'): self
    {
        return new self($connection, $tableName, $indexName, $vectorFieldName);
    }

    /**
     * @throws InvalidArgumentException When DBAL connection doesn't use PDO driver
     * @throws DBALException            When DBAL operations fail (e.g., getting native connection)
     */
    public static function fromDbal(Connection $connection, string $tableName, string $indexName = 'embedding', string $vectorFieldName = 'embedding'): self
    {
        $pdo = $connection->getNativeConnection();

        if (!$pdo instanceof \PDO) {
            throw new InvalidArgumentException('Only DBAL connections using PDO driver are supported.');
        }

        return self::fromPdo($pdo, $tableName, $indexName, $vectorFieldName);
    }

    public function add(VectorDocument ...$documents): void
    {
        $statement = $this->connection->prepare(
            \sprintf(
                <<<'SQL'
                    INSERT INTO %1$s (id, metadata, `%2$s`)
                    VALUES (:id, :metadata, VEC_FromText(:vector))
                    ON DUPLICATE KEY UPDATE metadata = :metadata, `%2$s` = VEC_FromText(:vector)
                    SQL,
                $this->tableName,
                $this->vectorFieldName,
            ),
        );

        foreach ($documents as $document) {
            $operation = [
                'id' => $document->id->toRfc4122(),
                'metadata' => json_encode($document->metadata->getArrayCopy()),
                'vector' => json_encode($document->vector->getData()),
            ];

            $statement->execute($operation);
        }
    }

    /**
     * @param array{
     *     limit?: positive-int,
     *     maxScore?: float|null,
     * } $options
     */
    public function query(Vector $vector, array $options = []): iterable
    {
        $where = null;

        $maxScore = $options['maxScore'] ?? null;
        if ($maxScore) {
            $where = \sprintf('WHERE VEC_DISTANCE_EUCLIDEAN(`%1$s`, VEC_FromText(:embedding)) <= :maxScore', $this->vectorFieldName);
        }

        if ($options['where'] ?? false) {
            if ($where) {
                $where .= ' AND ('.$options['where'].')';
            } else {
                $where = 'WHERE '.$options['where'];
            }
        }

        $statement = $this->connection->prepare(
            \sprintf(
                <<<'SQL'
                    SELECT id, VEC_ToText(`%1$s`) embedding, metadata, VEC_DISTANCE_EUCLIDEAN(`%1$s`, VEC_FromText(:embedding)) AS score
                    FROM %2$s
                    %3$s
                    ORDER BY score ASC
                    LIMIT %4$d
                    SQL,
                $this->vectorFieldName,
                $this->tableName,
                $where ?? '',
                $options['limit'] ?? 5,
            ),
        );

        $params = [
            'embedding' => json_encode($vector->getData()),
            ...$options['params'] ?? [],
        ];

        if (null !== $maxScore) {
            $params['maxScore'] = $maxScore;
        }

        $statement->execute($params);

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
