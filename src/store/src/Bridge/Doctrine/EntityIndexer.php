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

use Doctrine\ORM\EntityManagerInterface;
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
            $this->embed($entities);
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

            $this->embed($entities);

            $lastIdentifier = $metadata->getIdentifierValues($entities[array_key_last($entities)])[$identifierField];

            // Keeps the identity map from growing with the whole table
            $this->entityManager->clear();
        }
    }

    /**
     * @param list<object> $entities
     */
    private function embed(array $entities): void
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

            $this->embedder->embed($this->entityManager, $embedded);
            $this->entityManager->flush();
        }
    }
}
