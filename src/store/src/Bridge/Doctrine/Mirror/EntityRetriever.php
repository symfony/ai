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

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\AI\Store\Bridge\Doctrine\EntityVectorDocument;
use Symfony\AI\Store\Bridge\Doctrine\Mapping\EmbeddingMapping;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\RetrieverInterface;

/**
 * Loads the entities for the hits of a mirror store, in the order of the hits.
 *
 * Hits without a matching entity are skipped, e.g. entities removed while the store was out of sync.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class EntityRetriever implements RetrieverInterface
{
    private readonly ?\Closure $onSkippedDocument;

    /**
     * @param (callable(VectorDocumentInterface): void)|null $onSkippedDocument called for every hit without an entity
     */
    public function __construct(
        private readonly RetrieverInterface $retriever,
        private readonly EntityManagerInterface $entityManager,
        ?callable $onSkippedDocument = null,
    ) {
        $this->onSkippedDocument = null === $onSkippedDocument ? null : $onSkippedDocument(...);
    }

    /**
     * @return list<EntityVectorDocument>
     */
    public function retrieve(string $query, array $options = []): iterable
    {
        $documents = [...$this->retriever->retrieve($query, $options)];

        $idsByClass = [];
        foreach ($documents as $document) {
            $class = $document->getMetadata()[MirrorListener::METADATA_CLASS] ?? null;
            $id = $document->getMetadata()[MirrorListener::METADATA_ID] ?? null;

            if (\is_string($class) && (\is_string($id) || \is_int($id))) {
                $idsByClass[$class][] = (string) $id;
            }
        }

        $entitiesByClass = [];
        foreach ($idsByClass as $class => $ids) {
            $entitiesByClass[$class] = $this->load($class, $ids);
        }

        $result = [];
        foreach ($documents as $document) {
            $class = $document->getMetadata()[MirrorListener::METADATA_CLASS] ?? null;
            $id = $document->getMetadata()[MirrorListener::METADATA_ID] ?? null;
            $entity = \is_string($class) && (\is_string($id) || \is_int($id)) ? ($entitiesByClass[$class][(string) $id] ?? null) : null;

            if (null === $entity) {
                if (null !== $this->onSkippedDocument) {
                    ($this->onSkippedDocument)($document);
                }

                continue;
            }

            $mapping = EmbeddingMapping::fromClassMetadata($this->entityManager->getClassMetadata($entity::class));
            \assert(null !== $mapping);

            $result[] = new EntityVectorDocument(
                entity: $entity,
                id: (string) $id,
                vector: $document->getVector(),
                metadata: new Metadata([Metadata::KEY_TEXT => $mapping->getContent($entity)]),
                score: $document->getScore(),
            );
        }

        return $result;
    }

    /**
     * @param class-string|string $class
     * @param list<string>        $ids
     *
     * @return array<string, object> the entities by their id
     */
    private function load(string $class, array $ids): array
    {
        try {
            $metadata = $this->entityManager->getClassMetadata($class);
            $field = $metadata->getSingleIdentifierFieldName();
        } catch (MappingException|\ReflectionException) {
            return [];
        }

        // the class comes from the store, so only entities mirrored by this bridge are loaded
        if (!EmbeddingMapping::fromClassMetadata($metadata)?->isMirrored()) {
            return [];
        }

        $typeName = $metadata->getTypeOfField($field);
        if (null === $typeName) {
            return [];
        }

        $type = Type::getType($typeName);
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();

        // find() converts the id like Doctrine does, an IN list of objects like ulids is not converted by every ORM version
        $entities = [];
        foreach (array_unique($ids) as $id) {
            try {
                $entity = $this->entityManager->find($metadata->getName(), $type->convertToPHPValue($id, $platform));
            } catch (DbalException) {
                continue;
            }

            if (null !== $entity) {
                $entities[$id] = $entity;
            }
        }

        return $entities;
    }
}
