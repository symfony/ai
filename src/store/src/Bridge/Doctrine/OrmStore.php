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
use Symfony\AI\Store\FindSimilarTrait;
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
    use FindSimilarTrait;

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
                : $this->findEntity($document->getId());
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
            $this->mapping()->setVector($this->entityManager, $this->findEntity($id), null);
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

    /**
     * Returns null as well for an entity without a vector.
     */
    public function find(string $id): ?EntityVectorDocument
    {
        $entity = $this->entityManager->find($this->entityClass, $id);

        if (null === $entity) {
            return null;
        }

        $vector = $this->classMetadata()->getFieldValue($entity, $this->mapping()->getRequiredVectorField());

        if (!$vector instanceof VectorInterface) {
            return null;
        }

        return $this->createDocument($entity, $vector);
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    /**
     * @param array{
     *     limit?: positive-int,
     *     maxScore?: float,
     *     where?: string,
     *     params?: array<string, mixed>,
     *     paramTypes?: array<string, ParameterType|ArrayParameterType|string|int>,
     * } $options "where" is a DQL condition on the entity as "e", with "params" bound by name; the "vector" parameter is reserved
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

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->addSelect(\sprintf("%s(%s, :vector, '%s') AS score", VectorDistance::NAME, $this->vectorField(), $this->metric->value))
            ->from($this->entityClass, 'e')
            ->where($this->vectorField().' IS NOT NULL')
            ->orderBy('score', 'ASC')
            ->setMaxResults($options['limit'] ?? 5)
            ->setParameter('vector', $query->getVector(), VectorType::NAME);

        if (isset($options['where'])) {
            $queryBuilder->andWhere($options['where']);
        }

        foreach ($options['params'] ?? [] as $name => $value) {
            if ('vector' === ltrim((string) $name, ':')) {
                throw new InvalidArgumentException('The "vector" parameter is the vector of the query, give your parameter another name.');
            }

            $queryBuilder->setParameter($name, $value, $options['paramTypes'][$name] ?? null);
        }

        /** @var list<array{0: object, score: float|string}> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        $metadata = $this->classMetadata();

        $documents = [];
        foreach ($rows as $row) {
            $score = (float) $row['score'];

            // Rows come ordered by distance, so filtering after the limit equals filtering before it
            if (isset($options['maxScore']) && $score > $options['maxScore']) {
                break;
            }

            $entity = $row[0];
            /** @var VectorInterface $vector */
            $vector = $metadata->getFieldValue($entity, $this->mapping()->getRequiredVectorField());

            $documents[] = $this->createDocument($entity, $vector, $score);
        }

        return $documents;
    }

    private function createDocument(object $entity, VectorInterface $vector, ?float $score = null): EntityVectorDocument
    {
        $metadata = $this->classMetadata();

        return new EntityVectorDocument(
            entity: $entity,
            id: $this->identifierAsString($metadata->getIdentifierValues($entity)[$metadata->getSingleIdentifierFieldName()]),
            vector: $vector,
            metadata: new Metadata([Metadata::KEY_TEXT => $this->mapping()->getContent($entity)]),
            score: $score,
        );
    }

    private function findEntity(int|string $id): object
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
        return 'e.'.$this->mapping()->getRequiredVectorField();
    }

    private function mapping(): EmbeddingMapping
    {
        if (null !== $this->mapping) {
            return $this->mapping;
        }

        $metadata = $this->classMetadata();
        $mapping = EmbeddingMapping::fromClassMetadata($metadata);

        if (null === $mapping) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" has no #[Embedding] attribute.', $this->entityClass));
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
