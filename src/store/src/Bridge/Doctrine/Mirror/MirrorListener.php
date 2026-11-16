<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Mirror;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RetryableExceptionInterface;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Bridge\Doctrine\EntityEmbedder;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Mirrors the entities with a class level #[Embedding] into a store.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MirrorListener
{
    public const METADATA_CLASS = '_entity_class';
    public const METADATA_ID = '_entity_id';

    /**
     * @var array<class-string, EmbeddingMapping|null>
     */
    private array $mappings = [];

    /**
     * @var list<object>
     */
    private array $upserts = [];

    /**
     * @var list<string>
     */
    private array $removals = [];

    /**
     * @param class-string|null $entityClass mirrors only entities of this class, instead of all entities with #[Embedding] on the class
     */
    public function __construct(
        private readonly EntityEmbedder $embedder,
        private readonly StoreInterface $store,
        private readonly ?string $entityClass = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->upserts = [];
        $this->removals = [];

        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (null !== $this->mapping($entityManager->getClassMetadata($entity::class))) {
                $this->upserts[] = $entity;
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $mapping = $this->mapping($entityManager->getClassMetadata($entity::class));

            if (null !== $mapping && $mapping->isAffectedBy($unitOfWork->getEntityChangeSet($entity))) {
                $this->upserts[] = $entity;
            }
        }

        // Doctrine unsets generated ids while deleting, so the document ids are taken before
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);

            if (null !== $this->mapping($metadata)) {
                $this->removals[] = $this->documentId($metadata, $entity);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // generated ids exist only after the insert
        $upserts = $this->upserts;
        $removals = $this->removals;
        $this->upserts = [];
        $this->removals = [];

        if ([] !== $removals) {
            $this->store->remove($removals);
        }

        if ([] === $upserts) {
            return;
        }

        $entityManager = $args->getObjectManager();

        try {
            $vectors = $this->vectorize($entityManager, $upserts);
        } catch (RetryableExceptionInterface|TransportExceptionInterface|ExceedContextSizeException|ContentFilterException $exception) {
            $this->logger->warning('Mirroring {count} entities failed, they stay as they are in the store until indexed again: {message}', [
                'count' => \count($upserts),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return;
        }

        $this->write($entityManager, $upserts, $vectors);
    }

    /**
     * Writes the entities as documents into the store, replacing the documents they were mirrored as before.
     *
     * @param iterable<object> $entities
     *
     * @throws InvalidArgumentException when an entity is not handled by this listener
     */
    public function mirror(EntityManagerInterface $entityManager, iterable $entities): void
    {
        $entities = array_values([...$entities]);

        $this->write($entityManager, $entities, $this->vectorize($entityManager, $entities));
    }

    /**
     * @param list<object> $entities
     *
     * @return \SplObjectStorage<object, VectorInterface|null>
     *
     * @throws InvalidArgumentException   when an entity is not handled by this listener
     * @throws ExceedContextSizeException
     * @throws ContentFilterException
     */
    private function vectorize(EntityManagerInterface $entityManager, array $entities): \SplObjectStorage
    {
        foreach ($entities as $entity) {
            if (null === $this->mapping($entityManager->getClassMetadata($entity::class))) {
                throw new InvalidArgumentException(\sprintf('The mirror listener does not handle "%s" entities.', $entity::class));
            }
        }

        return $this->embedder->vectorize($entityManager, $entities);
    }

    /**
     * @param list<object>                                    $entities
     * @param \SplObjectStorage<object, VectorInterface|null> $vectors
     */
    private function write(EntityManagerInterface $entityManager, array $entities, \SplObjectStorage $vectors): void
    {
        $documents = [];
        $removals = [];
        foreach ($entities as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);
            $mapping = $this->mapping($metadata);
            \assert(null !== $mapping);

            $id = $this->documentId($metadata, $entity);
            $vector = $vectors[$entity] ?? null;

            if (null === $vector) {
                $removals[] = $id;

                continue;
            }

            $documents[] = new VectorDocument($id, $vector, new Metadata([
                Metadata::KEY_TEXT => $mapping->getContent($entity),
                self::METADATA_CLASS => $metadata->getName(),
                self::METADATA_ID => $this->identifier($metadata, $entity),
            ]));
        }

        if ([] !== $removals) {
            $this->store->remove($removals);
        }

        if ([] !== $documents) {
            $this->store->add($documents);
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function mapping(ClassMetadata $metadata): ?EmbeddingMapping
    {
        if (!\array_key_exists($metadata->getName(), $this->mappings)) {
            $handled = null === $this->entityClass || is_a($metadata->getName(), $this->entityClass, true);
            $mapping = $handled ? EmbeddingMapping::fromClassMetadata($metadata) : null;
            $this->mappings[$metadata->getName()] = $mapping?->isMirrored() ? $mapping : null;
        }

        return $this->mappings[$metadata->getName()];
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function documentId(ClassMetadata $metadata, object $entity): string
    {
        return (string) Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_DNS), $metadata->getName().'#'.$this->identifier($metadata, $entity));
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function identifier(ClassMetadata $metadata, object $entity): string
    {
        if ($metadata->isIdentifierComposite) {
            throw new InvalidArgumentException(\sprintf('Entity "%s" has a composite identifier, mirroring needs a single one as document id.', $metadata->getName()));
        }

        $identifier = $metadata->getIdentifierValues($entity)[$metadata->getSingleIdentifierFieldName()] ?? null;

        if (!$identifier instanceof \Stringable && !\is_scalar($identifier)) {
            throw new InvalidArgumentException(\sprintf('Cannot mirror a "%s" entity with a "%s" identifier, it is neither scalar nor stringable.', $metadata->getName(), get_debug_type($identifier)));
        }

        return (string) $identifier;
    }
}
