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
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Metric;
use Symfony\AI\Store\Bridge\Doctrine\Dbal\Types\VectorType;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Bridge\Doctrine\Orm\Query\VectorDistance;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * Keeps the vectors in the #[Embedding] field of an entity, and returns entities when queried.
 *
 * It writes vectors only to existing entities, and never creates or deletes entities.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class OrmStore implements StoreInterface
{
    private ?EmbeddingMapping $mapping = null;

    /**
     * @param class-string $entityClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $entityClass,
        private readonly Metric $metric = Metric::Cosine,
    ) {
    }

    /**
     * Sets the vectors on existing entities, ignoring the document metadata, and flushes the whole entity manager.
     *
     * @throws InvalidArgumentException when there is no entity with the id of a document
     */
    public function add(VectorDocumentInterface|array $documents): void
    {
        if ($documents instanceof VectorDocumentInterface) {
            $documents = [$documents];
        }

        if ([] === $documents) {
            return;
        }

        foreach ($documents as $document) {
            $entity = $document instanceof EntityVectorDocument && $document->getEntity() instanceof $this->entityClass && $this->entityManager->contains($document->getEntity())
                ? $document->getEntity()
                : $this->find($document->getId());
            $this->mapping()->setVector($this->entityManager, $entity, $document->getVector());
        }

        $this->entityManager->flush();
    }

    /**
     * Clears the vectors of the given entities, leaving the entities in place, and flushes the whole entity manager.
     *
     * @throws InvalidArgumentException when there is no entity with one of the ids
     */
    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        foreach ($ids as $id) {
            $this->mapping()->setVector($this->entityManager, $this->find($id), null);
        }

        $this->entityManager->flush();
    }

    /**
     * Clears the vectors of every entity with a bulk update, so managed entities keep theirs until refreshed.
     */
    public function clear(array $options = []): void
    {
        $this->entityManager->createQueryBuilder()
            ->update($this->entityClass, 'e')
            ->set($this->vectorField(), 'NULL')
            ->getQuery()
            ->execute();
    }

    /**
     * Counts the entities that have a vector.
     */
    public function count(): int
    {
        return max(0, (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e)')
            ->from($this->entityClass, 'e')
            ->where($this->vectorField().' IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult());
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{limit?: positive-int, maxScore?: float} $options
     *
     * @return list<EntityVectorDocument>
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $configuration = $this->entityManager->getConfiguration();
        if (null === $configuration->getCustomNumericFunction(VectorDistance::NAME)) {
            $configuration->addCustomNumericFunction(VectorDistance::NAME, VectorDistance::class);
        }

        /** @var list<array{0: object, score: float|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->addSelect(\sprintf("%s(%s, :vector, '%s') AS score", VectorDistance::NAME, $this->vectorField(), $this->metric->value))
            ->from($this->entityClass, 'e')
            ->where($this->vectorField().' IS NOT NULL')
            ->orderBy('score', 'ASC')
            ->setMaxResults($options['limit'] ?? 5)
            ->setParameter('vector', $query->getVector(), VectorType::NAME)
            ->getQuery()
            ->getResult();

        $metadata = $this->classMetadata();
        $identifierField = $metadata->getSingleIdentifierFieldName();

        $documents = [];
        foreach ($rows as $row) {
            $score = (float) $row['score'];

            // Rows come ordered by distance, so filtering after the limit equals filtering before it
            if (isset($options['maxScore']) && $score > $options['maxScore']) {
                break;
            }

            $entity = $row[0];
            /** @var VectorInterface $vector */
            $vector = $metadata->getFieldValue($entity, $this->mapping()->getVectorField());

            $documents[] = new EntityVectorDocument(
                entity: $entity,
                id: $this->identifierAsString($metadata->getIdentifierValues($entity)[$identifierField]),
                vector: $vector,
                metadata: new Metadata([Metadata::KEY_TEXT => $this->mapping()->getContent($entity)]),
                score: $score,
            );
        }

        return $documents;
    }

    private function find(int|string $id): object
    {
        $entity = $this->entityManager->find($this->entityClass, $id);

        if (null === $entity) {
            throw new InvalidArgumentException(\sprintf('There is no "%s" entity with the id "%s".', $this->entityClass, $id));
        }

        return $entity;
    }

    private function identifierAsString(mixed $identifier): string
    {
        if ($identifier instanceof \Stringable || \is_scalar($identifier)) {
            return (string) $identifier;
        }

        throw new InvalidArgumentException(\sprintf('Cannot use a "%s" identifier as a document id, it is neither scalar nor stringable.', get_debug_type($identifier)));
    }

    private function vectorField(): string
    {
        return 'e.'.$this->mapping()->getVectorField();
    }

    private function mapping(): EmbeddingMapping
    {
        if (null !== $this->mapping) {
            return $this->mapping;
        }

        $metadata = $this->classMetadata();
        $mapping = EmbeddingMapping::fromClassMetadata($metadata);

        if (null === $mapping) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" has no field marked with #[Embedding].', $this->entityClass));
        }

        if ($metadata->isIdentifierComposite) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" has a composite identifier, the store needs a single one as document id.', $this->entityClass));
        }

        return $this->mapping = $mapping;
    }

    /**
     * @return ClassMetadata<object>
     */
    private function classMetadata(): ClassMetadata
    {
        return $this->entityManager->getClassMetadata($this->entityClass);
    }
}
