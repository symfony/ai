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
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\Mapping\OwningSideMapping;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;

/**
 * Finds the entities whose embedding is built from an association that a flush changes.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class AssociationChanges
{
    /**
     * @param \Closure(ClassMetadata<object>): ?EmbeddingMapping $mapping the mapping handled by the caller, null to skip the class
     *
     * @return array<int, object> entities that are neither inserted nor removed by the flush, by their object id
     */
    public static function affectedEntities(EntityManagerInterface $entityManager, \Closure $mapping): array
    {
        $unitOfWork = $entityManager->getUnitOfWork();

        $affected = [];
        $mark = static function (object $entity, string $field) use ($entityManager, $unitOfWork, $mapping, &$affected): void {
            // inserted entities are embedded anyway, removed ones are not embedded at all
            if ($unitOfWork->isScheduledForInsert($entity) || $unitOfWork->isScheduledForDelete($entity)) {
                return;
            }

            if ($mapping($entityManager->getClassMetadata($entity::class))?->isBuiltFrom($field)) {
                $affected[spl_object_id($entity)] = $entity;
            }
        };

        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $association = $collection->getMapping();
            $owner = $collection->getOwner();

            if (null !== $owner) {
                $mark($owner, $association->fieldName);
            }

            if (null !== $otherSide = self::otherSide($association)) {
                foreach ([...$collection->getInsertDiff(), ...$collection->getDeleteDiff()] as $related) {
                    $mark($related, $otherSide);
                }
            }
        }

        foreach ($unitOfWork->getScheduledCollectionDeletions() as $collection) {
            $association = $collection->getMapping();
            $owner = $collection->getOwner();

            if (null !== $owner) {
                $mark($owner, $association->fieldName);
            }

            if (null === $owner || null === $otherSide = self::otherSide($association)) {
                continue;
            }

            if (!self::isBuiltFrom($entityManager, $mapping, $association->targetEntity, $otherSide)) {
                continue;
            }

            // a cleared or never loaded collection has no snapshot of the entities it held
            foreach (self::persistedMembers($entityManager, $association, $owner) as $related) {
                $mark($related, $otherSide);
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);

            foreach ($unitOfWork->getEntityChangeSet($entity) as $field => $change) {
                // a replaced collection is in the change set as well, the collection deletions cover it
                if (!$metadata->hasAssociation($field) || !$metadata->getAssociationMapping($field)->isToOne()) {
                    continue;
                }

                if (null === $otherSide = self::otherSide($metadata->getAssociationMapping($field))) {
                    continue;
                }

                foreach ($change as $related) {
                    if (\is_object($related)) {
                        $mark($related, $otherSide);
                    }
                }
            }
        }

        // a new entity joins the collections its to-one associations point to
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);

            foreach ($metadata->getAssociationMappings() as $field => $association) {
                if ($association->isToOneOwningSide() && null !== $otherSide = self::otherSide($association)) {
                    $related = $metadata->getFieldValue($entity, $field);

                    if (\is_object($related)) {
                        $mark($related, $otherSide);
                    }
                }
            }
        }

        // Doctrine deletes the join table rows of a removed entity without a collection change
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $metadata = $entityManager->getClassMetadata($entity::class);

            foreach ($metadata->getAssociationMappings() as $field => $association) {
                // reading a lazy collection loads it, so only for associations an embedding is built from
                if (null === $otherSide = self::otherSide($association)) {
                    continue;
                }

                if (!self::isBuiltFrom($entityManager, $mapping, $association->targetEntity, $otherSide)) {
                    continue;
                }

                // a removed entity gets no change set, so a link dropped before the removal is only persisted
                if ($association->isToMany()) {
                    $related = self::persistedMembers($entityManager, $association, $entity);
                } else {
                    $related = [$metadata->getFieldValue($entity, $field), $unitOfWork->getOriginalEntityData($entity)[$field] ?? null];
                }

                foreach ($related as $item) {
                    if (\is_object($item)) {
                        $mark($item, $otherSide);
                    }
                }
            }
        }

        return $affected;
    }

    /**
     * @param \Closure(ClassMetadata<object>): ?EmbeddingMapping $mapping
     * @param class-string                                       $class
     */
    private static function isBuiltFrom(EntityManagerInterface $entityManager, \Closure $mapping, string $class, string $field): bool
    {
        $metadata = $entityManager->getClassMetadata($class);

        foreach ([$metadata->getName(), ...$metadata->subClasses] as $candidate) {
            if ($mapping($entityManager->getClassMetadata($candidate))?->isBuiltFrom($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The entities the association links to the owner in the database, before the flush changes it.
     *
     * @return list<object>
     */
    private static function persistedMembers(EntityManagerInterface $entityManager, AssociationMapping $association, object $owner): array
    {
        $persister = $entityManager->getUnitOfWork()->getEntityPersister($association->targetEntity);

        if ($association->isManyToMany()) {
            return array_values($persister->getManyToManyCollection($association, $owner));
        }

        return array_values($persister->getOneToManyCollection($association, $owner));
    }

    /**
     * The field of the same association on the entities it points to, null when it is unidirectional.
     */
    private static function otherSide(AssociationMapping $association): ?string
    {
        return match (true) {
            $association instanceof OwningSideMapping => $association->inversedBy,
            $association instanceof InverseSideMapping => $association->mappedBy,
            default => null,
        };
    }
}
