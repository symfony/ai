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

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\RetryableExceptionInterface;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Embeds new entities and entities whose embedded fields changed, as part of the flush.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class EmbeddingListener
{
    /**
     * @var array<class-string, EmbeddingMapping|null>
     */
    private array $mappings = [];

    /**
     * @param class-string|null $entityClass embeds only entities of this class, instead of all entities with #[Embedding]
     */
    public function __construct(
        private readonly EntityEmbedder $embedder,
        private readonly ?string $entityClass = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entitiesByClass = [];
        // a vector set before the flush, e.g. by the indexer or the store, is kept
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);
            $mapping = $this->mapping($metadata);

            if (null !== $mapping && null === $metadata->getFieldValue($entity, $mapping->getVectorField())) {
                $entitiesByClass[$metadata->getName()][] = $entity;
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);
            $mapping = $this->mapping($metadata);
            $changeSet = $unitOfWork->getEntityChangeSet($entity);

            if (null !== $mapping && !\array_key_exists($mapping->getVectorField(), $changeSet) && $mapping->isAffectedBy($changeSet)) {
                $entitiesByClass[$metadata->getName()][] = $entity;
            }
        }

        foreach ($entitiesByClass as $class => $entities) {
            $metadata = $entityManager->getClassMetadata($class);

            try {
                $this->embedder->embed($entityManager, $entities);
            } catch (RetryableExceptionInterface|TransportExceptionInterface|ExceedContextSizeException|ContentFilterException $exception) {
                $this->clearEmbeddings($metadata, $entities, $exception);
            }

            foreach ($entities as $entity) {
                $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
            }
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<object>          $entities
     */
    private function clearEmbeddings(ClassMetadata $metadata, array $entities, RetryableExceptionInterface|TransportExceptionInterface|ExceedContextSizeException|ContentFilterException $exception): void
    {
        $mapping = $this->mapping($metadata);
        \assert(null !== $mapping);

        foreach ($entities as $entity) {
            $metadata->setFieldValue($entity, $mapping->getVectorField(), null);
        }

        $this->logger->warning('Embedding {count} "{class}" entities failed, they stay without embedding until indexed again: {message}', [
            'count' => \count($entities),
            'class' => $metadata->getName(),
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function mapping(ClassMetadata $metadata): ?EmbeddingMapping
    {
        if (!\array_key_exists($metadata->getName(), $this->mappings)) {
            $handled = null === $this->entityClass || is_a($metadata->getName(), $this->entityClass, true);
            $this->mappings[$metadata->getName()] = $handled ? EmbeddingMapping::fromClassMetadata($metadata) : null;
        }

        return $this->mappings[$metadata->getName()];
    }
}
