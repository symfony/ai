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

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\TimestampCacheKey;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Bridge\Doctrine\Mirror\MirrorListener;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\IndexerInterface;

/**
 * Embeds the entities of an existing table in batches, or the entities it is given.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EntityIndexer implements IndexerInterface
{
    /**
     * @param positive-int $batchSize
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityEmbedder $embedder,
        private readonly int $batchSize = 100,
        private readonly ?MirrorListener $mirror = null,
    ) {
    }

    /**
     * @param string|object|iterable<string|object>                 $input   entity classes to walk their whole table, or entities to embed
     * @param array{only_missing?: bool, batch_size?: positive-int} $options
     */
    public function index(string|iterable|object $input, array $options = []): void
    {
        // the listener embeds with the same embedder, so per-call options would mix vectors of different models
        if (\array_key_exists('platform_options', $options)) {
            throw new InvalidArgumentException('The entity indexer does not take "platform_options", pass them with the model name of the vectorizer instead, e.g. "text-embedding-3-large?dimensions=1536".');
        }

        $entityClasses = [];
        $entities = [];
        foreach (\is_string($input) || !is_iterable($input) ? [$input] : $input as $item) {
            if (\is_string($item)) {
                $entityClasses[] = $item;

                continue;
            }

            if (!$this->entityManager->contains($item)) {
                throw new InvalidArgumentException(\sprintf('The "%s" entity is not managed by the entity manager, persist it or load it before indexing it.', $item::class));
            }

            $entities[] = $item;
        }

        // before the table walks, which clear the entity manager
        if ([] !== $entities) {
            $this->embed($entities, write: function (array $entities): void {
                $this->embedder->embed($this->entityManager, $entities);
                $this->entityManager->flush();
            });
        }

        foreach ($entityClasses as $entityClass) {
            $this->indexTable($entityClass, $options['only_missing'] ?? false, $options['batch_size'] ?? $this->batchSize);
        }
    }

    /**
     * @param positive-int $batchSize
     */
    private function indexTable(string $entityClass, bool $onlyMissing, int $batchSize): void
    {
        if (!class_exists($entityClass)) {
            throw new InvalidArgumentException(\sprintf('Class "%s" does not exist.', $entityClass));
        }

        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $mapping = EmbeddingMapping::fromClassMetadata($metadata);

        if (null === $mapping) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" has no #[Embedding] attribute.', $entityClass));
        }

        if ($onlyMissing && $mapping->isMirrored()) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" is mirrored into another store, "only_missing" has no field to check.', $entityClass));
        }

        if ($metadata->isIdentifierComposite) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" has a composite identifier, the indexer needs a single one to walk the table.', $entityClass));
        }

        $identifierField = $metadata->getSingleIdentifierFieldName();
        $identifierType = $metadata->getTypeOfField($identifierField);
        $lastIdentifier = null;

        // Keyset pagination stays correct while embedded rows drop out of the "only missing" filter
        while (true) {
            $queryBuilder = $this->entityManager->createQueryBuilder()
                ->select('e')
                ->from($entityClass, 'e')
                ->orderBy('e.'.$identifierField, 'ASC')
                ->setMaxResults($batchSize);

            if ($onlyMissing) {
                $queryBuilder->andWhere(\sprintf('e.%s IS NULL', $mapping->getRequiredVectorField()));
            }

            if (null !== $lastIdentifier) {
                $queryBuilder
                    ->andWhere(\sprintf('e.%s > :lastIdentifier', $identifierField))
                    ->setParameter('lastIdentifier', $lastIdentifier, $identifierType);
            }

            $entities = $queryBuilder->getQuery()->getResult();

            if ([] === $entities) {
                return;
            }

            $this->embed($entities, write: $this->update(...));

            $lastIdentifier = $metadata->getIdentifierValues($entities[array_key_last($entities)])[$identifierField];

            // Keeps the identity map from growing with the whole table
            $this->entityManager->clear();
        }
    }

    /**
     * @param list<object>                 $entities
     * @param \Closure(list<object>): void $write    writes the vectors of entities that are not mirrored
     */
    private function embed(array $entities, \Closure $write): void
    {
        $mirrored = [];
        $embedded = [];
        foreach ($entities as $entity) {
            if (EmbeddingMapping::fromClassMetadata($this->entityManager->getClassMetadata($entity::class))?->isMirrored()) {
                $mirrored[] = $entity;
            } else {
                $embedded[] = $entity;
            }
        }

        if ([] !== $mirrored) {
            if (null === $this->mirror) {
                throw new InvalidArgumentException('Mirrored entities need a mirror listener to write them into the store.');
            }

            $this->mirror->mirror($this->entityManager, $mirrored);
        }

        if ([] !== $embedded) {
            if (null !== $this->mirror) {
                throw new InvalidArgumentException(\sprintf('Entity "%s" is not mirrored, put #[Embedding] on the class or remove the mirror from the indexer.', $embedded[0]::class));
            }

            $write($embedded);
        }
    }

    /**
     * Writes with an UPDATE per entity, a flush would run the application's listeners for every row.
     *
     * @param list<object> $entities
     */
    private function update(array $entities): void
    {
        $vectors = $this->embedder->vectorize($this->entityManager, $entities);
        $connection = $this->entityManager->getConnection();
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $statements = [];

        $connection->transactional(function () use ($vectors, $connection, $unitOfWork, &$statements): void {
            foreach ($vectors as $entity) {
                $metadata = $this->entityManager->getClassMetadata($entity::class);
                [$sql, $types, $versionField] = $statements[$entity::class] ??= $this->updateStatement($metadata);

                // the unit of work holds association identifiers flattened to the identifier value of the target
                $parameters = [$vectors[$entity], ...array_values($unitOfWork->getEntityIdentifier($entity))];

                if (null !== $versionField) {
                    $parameters[] = $metadata->getFieldValue($entity, $versionField);
                }

                // no row matches when the entity was edited since it was loaded, that edit wins
                $connection->executeStatement($sql, $parameters, $types);
            }
        });

        $cache = $this->entityManager->getCache();
        if (null === $cache) {
            return;
        }

        $timestampRegion = $this->entityManager->getConfiguration()->getSecondLevelCacheConfiguration()?->getCacheFactory()?->getTimestampRegion();
        foreach (array_keys($statements) as $entityClass) {
            $cache->evictEntityRegion($entityClass);
            // a cached query result without the written entities, like an empty one, stays valid until the timestamp moves
            $timestampRegion?->update(new TimestampCacheKey($this->entityManager->getClassMetadata($entityClass)->rootEntityName));
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array{string, list<string>, string|null} the SQL, the parameter types and the version field
     */
    private function updateStatement(ClassMetadata $metadata): array
    {
        $mapping = EmbeddingMapping::fromClassMetadata($metadata);
        \assert(null !== $mapping);
        $vectorField = $mapping->getRequiredVectorField();
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();
        $quoteStrategy = $this->entityManager->getConfiguration()->getQuoteStrategy();

        // with class table inheritance, the vector is in the table of the class declaring it
        $tableMetadata = $this->entityManager->getClassMetadata($metadata->getFieldMapping($vectorField)->inherited ?? $metadata->getName());
        $table = $quoteStrategy->getTableName($tableMetadata, $platform);
        $identifierColumn = $quoteStrategy->getIdentifierColumnNames($tableMetadata, $platform)[0];

        // DQL would skip the SQL conversion of the vector type, which MariaDB needs
        $sql = \sprintf(
            'UPDATE %s SET %s = %s WHERE %s = ?',
            $table,
            $quoteStrategy->getColumnName($vectorField, $tableMetadata, $platform),
            Type::getType(VectorType::NAME)->convertToDatabaseValueSQL('?', $platform),
            $identifierColumn,
        );
        $types = [VectorType::NAME, $this->identifierType($metadata)];

        $versionField = $metadata->isVersioned ? $metadata->versionField : null;
        if (null !== $versionField) {
            $versionMetadata = $this->entityManager->getClassMetadata($metadata->getFieldMapping($versionField)->inherited ?? $metadata->getName());
            $versionTable = $quoteStrategy->getTableName($versionMetadata, $platform);
            $versionColumn = $quoteStrategy->getColumnName($versionField, $versionMetadata, $platform);

            if ($versionTable === $table) {
                $sql .= \sprintf(' AND %s = ?', $versionColumn);
            } else {
                // with class table inheritance, the version is in the root table
                $sql .= \sprintf(
                    ' AND EXISTS (SELECT 1 FROM %1$s WHERE %1$s.%2$s = %3$s.%4$s AND %1$s.%5$s = ?)',
                    $versionTable,
                    $quoteStrategy->getIdentifierColumnNames($versionMetadata, $platform)[0],
                    $table,
                    $identifierColumn,
                    $versionColumn,
                );
            }

            $versionType = $metadata->getTypeOfField($versionField);
            \assert(null !== $versionType);
            $types[] = $versionType;
        }

        return [$sql, $types, $versionField];
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function identifierType(ClassMetadata $metadata): string
    {
        $identifierField = $metadata->getSingleIdentifierFieldName();
        while ($metadata->hasAssociation($identifierField)) {
            $metadata = $this->entityManager->getClassMetadata($metadata->getAssociationTargetClass($identifierField));
            $identifierField = $metadata->getSingleIdentifierFieldName();
        }

        $identifierType = $metadata->getTypeOfField($identifierField);
        \assert(null !== $identifierType);

        return $identifierType;
    }
}
