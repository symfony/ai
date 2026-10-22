<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\VectorPlatformFactory;
use Symfony\AI\Store\Bridge\Doctrine\VectorPlatform\VectorPlatformInterface;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * Stores vectors in a column of the table an entity already lives in, and returns entities when queried.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    private ?VectorPlatformInterface $resolvedVectorPlatform = null;

    /**
     * @param class-string $entityClass The entity holding the vectors
     * @param string       $vectorField The entity field the vectors are stored in
     * @param string|null  $indexName   The name of the vector index, defaults to "<table>_<column>_idx"
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $entityClass,
        private readonly string $vectorField = 'embedding',
        private readonly Distance $distance = Distance::Cosine,
        private readonly ?VectorPlatformInterface $vectorPlatform = null,
        private readonly ?string $indexName = null,
    ) {
    }

    /**
     * Sizes the vector column the entity mapping created and indexes it.
     *
     * @param array{dimensions?: positive-int} $options
     */
    public function setup(array $options = []): void
    {
        $connection = $this->entityManager->getConnection();

        foreach ($this->vectorPlatform()->getSetupSql($this->tableName(), $this->vectorColumn(), $this->indexName(), $options['dimensions'] ?? 1536, $this->distance) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    /**
     * Drops the vector index and clears the vectors, leaving the column to the schema tooling.
     */
    public function drop(array $options = []): void
    {
        $connection = $this->entityManager->getConnection();

        foreach ($this->vectorPlatform()->getDropSql($this->indexName()) as $sql) {
            $connection->executeStatement($sql);
        }

        $this->clear();
    }

    /**
     * Writes the vectors with DBAL, so managed entities keep their loaded vector until refreshed.
     */
    public function add(VectorDocumentInterface|array $documents): void
    {
        if ($documents instanceof VectorDocumentInterface) {
            $documents = [$documents];
        }

        if ([] === $documents) {
            return;
        }

        $vectorPlatform = $this->vectorPlatform();
        $sql = \sprintf(
            'UPDATE %s SET %s = %s WHERE %s = :id',
            $this->tableName(),
            $this->vectorColumn(),
            $vectorPlatform->getVectorParameterSql(':vector'),
            $this->identifierColumn(),
        );

        $connection = $this->entityManager->getConnection();
        $identifierType = $this->identifierType();

        $connection->transactional(static function () use ($documents, $sql, $connection, $vectorPlatform, $identifierType): void {
            foreach ($documents as $document) {
                $connection->executeStatement(
                    $sql,
                    ['vector' => $vectorPlatform->toDatabaseValue($document->getVector()), 'id' => $document->getId()],
                    ['id' => $identifierType],
                );
            }
        });
    }

    /**
     * Clears the vectors of the given entities, leaving the entities themselves untouched.
     */
    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $sql = \sprintf(
            'UPDATE %s SET %s = NULL WHERE %s = :id',
            $this->tableName(),
            $this->vectorColumn(),
            $this->identifierColumn(),
        );

        $connection = $this->entityManager->getConnection();
        $identifierType = $this->identifierType();

        $connection->transactional(static function () use ($ids, $sql, $connection, $identifierType): void {
            foreach ($ids as $id) {
                $connection->executeStatement($sql, ['id' => $id], ['id' => $identifierType]);
            }
        });
    }

    /**
     * Clears the vectors of every entity, leaving the entities themselves untouched.
     */
    public function clear(array $options = []): void
    {
        $this->entityManager->getConnection()->executeStatement(
            \sprintf('UPDATE %s SET %s = NULL', $this->tableName(), $this->vectorColumn()),
        );
    }

    public function count(): int
    {
        return max(0, (int) $this->entityManager->getConnection()->fetchOne(
            \sprintf('SELECT COUNT(*) FROM %s WHERE %s IS NOT NULL', $this->tableName(), $this->vectorColumn()),
        ));
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{limit?: positive-int, maxScore?: float, where?: string, params?: array<string, mixed>, types?: array<string, mixed>} $options
     *
     * @return iterable<EntityVectorDocument>
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $vectorPlatform = $this->vectorPlatform();
        $vectorColumn = $this->vectorColumn();
        $distanceSql = $vectorPlatform->getDistanceSql($vectorColumn, $vectorPlatform->getVectorParameterSql(':vector'), $this->distance);

        $whereClauses = [\sprintf('%s IS NOT NULL', $vectorColumn)];
        $params = ['vector' => $vectorPlatform->toDatabaseValue($query->getVector())];
        $types = [];

        if (isset($options['maxScore'])) {
            // DBAL binds floats as strings, which SQLite compares as text
            $whereClauses[] = \sprintf('%s <= CAST(:maxScore AS DOUBLE PRECISION)', $distanceSql);
            $params['maxScore'] = $options['maxScore'];
        }

        if (isset($options['where'])) {
            $whereClauses[] = \sprintf('(%s)', $options['where']);
            $params += $options['params'] ?? [];
            $types += $options['types'] ?? [];
        }

        $sql = \sprintf(
            'SELECT %s AS id, %s AS vector, %s AS score FROM %s WHERE %s ORDER BY score ASC LIMIT %d',
            $this->identifierColumn(),
            $vectorPlatform->getVectorSelectSql($vectorColumn),
            $distanceSql,
            $this->tableName(),
            implode(' AND ', $whereClauses),
            $options['limit'] ?? 5,
        );

        $rows = $this->entityManager->getConnection()->executeQuery($sql, $params, $types)->fetchAllAssociative();

        if ([] === $rows) {
            return [];
        }

        return $this->hydrate($rows, $vectorPlatform);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<EntityVectorDocument>
     */
    private function hydrate(array $rows, VectorPlatformInterface $vectorPlatform): array
    {
        $metadata = $this->classMetadata();
        $identifierField = $metadata->getSingleIdentifierFieldName();
        $connection = $this->entityManager->getConnection();
        $identifierType = $this->identifierType();

        $identifiers = [];
        foreach ($rows as $row) {
            $identifiers[] = $connection->convertToPHPValue($row['id'], $identifierType);
        }

        // Raw values keep the IN list independent of how the ORM version converts identifier objects
        $entities = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from($this->entityClass, 'e')
            ->where(\sprintf('e.%s IN (:ids)', $identifierField))
            ->setParameter('ids', array_column($rows, 'id'), match (Type::getType($identifierType)->getBindingType()) {
                ParameterType::INTEGER => ArrayParameterType::INTEGER,
                ParameterType::ASCII => ArrayParameterType::ASCII,
                ParameterType::BINARY => ArrayParameterType::BINARY,
                default => ArrayParameterType::STRING,
            })
            ->getQuery()
            ->getResult();

        $entitiesById = [];
        foreach ($entities as $entity) {
            $entitiesById[$this->identifierAsString($metadata->getIdentifierValues($entity)[$identifierField])] = $entity;
        }

        $documents = [];
        foreach ($rows as $i => $row) {
            $id = $this->identifierAsString($identifiers[$i]);
            $entity = $entitiesById[$id] ?? null;

            // A row can disappear between the similarity query and the hydration of its entity.
            if (null === $entity) {
                continue;
            }

            $documents[] = new EntityVectorDocument(
                entity: $entity,
                id: $id,
                vector: $vectorPlatform->toVector((string) $row['vector']),
                score: (float) $row['score'],
            );
        }

        return $documents;
    }

    private function identifierAsString(mixed $identifier): string
    {
        if ($identifier instanceof \Stringable || \is_scalar($identifier)) {
            return (string) $identifier;
        }

        throw new InvalidArgumentException(\sprintf('Cannot use a "%s" identifier as a document id, it is neither scalar nor stringable.', get_debug_type($identifier)));
    }

    /**
     * @return ClassMetadata<object>
     */
    private function classMetadata(): ClassMetadata
    {
        return $this->entityManager->getClassMetadata($this->entityClass);
    }

    private function tableName(): string
    {
        return $this->entityManager->getConfiguration()->getQuoteStrategy()->getTableName(
            $this->classMetadata(),
            $this->entityManager->getConnection()->getDatabasePlatform(),
        );
    }

    private function identifierColumn(): string
    {
        $metadata = $this->classMetadata();

        return $this->entityManager->getConfiguration()->getQuoteStrategy()->getColumnName(
            $metadata->getSingleIdentifierFieldName(),
            $metadata,
            $this->entityManager->getConnection()->getDatabasePlatform(),
        );
    }

    private function identifierType(): string
    {
        $metadata = $this->classMetadata();

        return $metadata->getTypeOfField($metadata->getSingleIdentifierFieldName()) ?? 'string';
    }

    private function vectorColumn(): string
    {
        $metadata = $this->classMetadata();

        if (!$metadata->hasField($this->vectorField)) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" has no field "%s" to store vectors in.', $this->entityClass, $this->vectorField));
        }

        return $this->entityManager->getConfiguration()->getQuoteStrategy()->getColumnName(
            $this->vectorField,
            $metadata,
            $this->entityManager->getConnection()->getDatabasePlatform(),
        );
    }

    private function indexName(): string
    {
        return $this->indexName ?? \sprintf('%s_%s_idx', $this->classMetadata()->getTableName(), $this->classMetadata()->getColumnName($this->vectorField));
    }

    private function vectorPlatform(): VectorPlatformInterface
    {
        return $this->resolvedVectorPlatform ??= $this->vectorPlatform
            ?? VectorPlatformFactory::create($this->entityManager->getConnection()->getDatabasePlatform());
    }
}
